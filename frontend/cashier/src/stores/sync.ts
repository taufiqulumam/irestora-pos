import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';
import Dexie, { Table } from 'dexie';
import { generateUUID } from '@shared/utils';
import type { Order, OrderItem, Payment, OrderBatch, OutletTaxConfig } from '@shared/types';

// Local interfaces for offline storage (extends server types with local fields)
interface OfflineOrder {
  localId: string;
  id: string;
  outlet_id: string;
  order_type: 'dine_in' | 'takeaway';
  table_id: string | null;
  shift_id: string;
  cashier_id: string;
  order_number: string;
  status: 'open' | 'paid' | 'void' | 'cancelled';
  subtotal: number;
  discount_total: number;
  service_charge_total: number;
  pb1_total: number;
  rounding_adjustment: number;
  grand_total: number;
  source: 'cashier' | 'customer_self_order';
  device_id: string;
  created_at_client: string;
  synced_at: string | null;
  sync_status: 'pending' | 'synced' | 'conflict' | 'failed';
  syncAttempts: number;
  createdAtLocal: number;
  lastSyncAttempt?: number;
}

interface OfflineOrderItem {
  localId: string;
  orderLocalId: string;
  id: string;
  order_id: string;
  order_batch_id: string;
  menu_id: string;
  menu_name_snapshot: string;
  price_snapshot: number;
  qty: number;
  notes: string | null;
  status: 'active' | 'voided';
  voided_by: string | null;
  void_reason: string | null;
}

interface OfflinePayment {
  localId: string;
  orderLocalId: string;
  id: string;
  order_id: string;
  method: 'cash' | 'qris' | 'card' | 'other';
  amount: number;
  reference_number: string | null;
}

interface SyncQueueItem {
  id: string;
  type: 'order' | 'payment' | 'batch';
  payload: any;
  status: 'pending' | 'processing' | 'done' | 'failed';
  attempts: number;
  createdAt: number;
  updatedAt: number;
}

class OfflineDB extends Dexie {
  orders!: Table<OfflineOrder, string>;
  orderItems!: Table<OfflineOrderItem, string>;
  payments!: Table<OfflinePayment, string>;
  batches!: Table<OrderBatch, string>;
  syncQueue!: Table<SyncQueueItem, string>;

  constructor() {
    super('irestora-offline-db');
    this.version(1).stores({
      orders: 'localId, id, outlet_id, status, sync_status, createdAtLocal',
      orderItems: 'localId, orderLocalId, menu_id',
      payments: 'localId, orderLocalId',
      batches: 'id, order_id, status',
      syncQueue: 'id, type, status, createdAt',
    });
  }
}

export const offlineDB = new OfflineDB();

export const useSyncStore = defineStore('sync', () => {
  const isOnline = ref(true);
  const syncStatus = ref<'idle' | 'syncing' | 'error'>('idle');
  const lastSyncTime = ref<number | null>(null);
  const pendingCount = ref(0);
  const conflictCount = ref(0);
  const failedCount = ref(0);

  const statusText = computed(() => {
    if (syncStatus.value === 'syncing') return 'Menyinkronkan...';
    if (!isOnline.value) return 'Offline';
    if (conflictCount.value > 0) return `${conflictCount.value} konflik`;
    if (failedCount.value > 0) return `${failedCount.value} gagal`;
    if (pendingCount.value > 0) return `${pendingCount.value} menunggu`;
    return 'Tersinkronisasi';
  });

  const statusColor = computed(() => {
    if (!isOnline.value) return 'orange';
    if (syncStatus.value === 'syncing') return 'blue';
    if (conflictCount.value > 0 || failedCount.value > 0) return 'red';
    if (pendingCount.value > 0) return 'yellow';
    return 'green';
  });

  async function initializeOnlineStatus(): Promise<void> {
    try {
      await axios.head('/api/auth/me', {
        headers: { 'Cache-Control': 'no-cache' },
      });
      isOnline.value = true;
    } catch {
      isOnline.value = false;
    }
    
    window.addEventListener('online', () => {
      isOnline.value = true;
      processSyncQueue();
    });
    
    window.addEventListener('offline', () => {
      isOnline.value = false;
    });

    await updateCounts();
  }

  async function updateCounts(): Promise<void> {
    pendingCount.value = await offlineDB.syncQueue.where('status').equals('pending').count();
    conflictCount.value = await offlineDB.orders.where('sync_status').equals('conflict').count();
    failedCount.value = await offlineDB.syncQueue.where('status').equals('failed').count();
  }

  async function queueOrderForSync(
    order: Omit<OfflineOrder, 'localId' | 'createdAtLocal' | 'sync_status' | 'syncAttempts'>
  ): Promise<string> {
    const localId = generateUUID();
    const now = Date.now();

    const offlineOrder: OfflineOrder = {
      ...order,
      localId,
      createdAtLocal: now,
      sync_status: 'pending',
      syncAttempts: 0,
    };

    await offlineDB.orders.add(offlineOrder);
    await addToSyncQueue('order', offlineOrder);
    await updateCounts();
    
    return localId;
  }

  async function queuePaymentForSync(
    payment: Omit<OfflinePayment, 'localId'> & { orderLocalId: string }
  ): Promise<string> {
    const localId = generateUUID();
    
    const offlinePayment: OfflinePayment = {
      ...payment,
      localId,
    };

    await offlineDB.payments.add(offlinePayment);
    await addToSyncQueue('payment', offlinePayment);
    await updateCounts();
    
    return localId;
  }

  async function addToSyncQueue(type: 'order' | 'payment' | 'batch', payload: any): Promise<void> {
    await offlineDB.syncQueue.add({
      id: generateUUID(),
      type,
      payload,
      status: 'pending',
      attempts: 0,
      createdAt: Date.now(),
      updatedAt: Date.now(),
    });
  }

  async function processSyncQueue(): Promise<void> {
    if (!isOnline.value || syncStatus.value === 'syncing') return;
    
    syncStatus.value = 'syncing';
    
    try {
      const pendingItems = await offlineDB.syncQueue
        .where('status')
        .anyOf(['pending', 'failed'])
        .sortBy('createdAt');
      
      for (const item of pendingItems) {
        if (!isOnline.value) break;
        
        await processSyncItem(item);
      }
    } catch (error) {
      console.error('Sync error:', error);
    } finally {
      syncStatus.value = 'idle';
      lastSyncTime.value = Date.now();
      await updateCounts();
    }
  }

  async function processSyncItem(item: SyncQueueItem): Promise<void> {
    await offlineDB.syncQueue.update(item.id, { 
      status: 'processing', 
      attempts: item.attempts + 1,
      updatedAt: Date.now(),
    });

    try {
      let response: any;
      
      switch (item.type) {
        case 'order':
          response = await syncOrder(item.payload);
          break;
        case 'payment':
          response = await syncPayment(item.payload);
          break;
        case 'batch':
          response = await syncBatch(item.payload);
          break;
      }
      
      if (response?.success) {
        await offlineDB.syncQueue.delete(item.id);
        await markOrderSynced(item.payload.localId || item.payload.id);
      } else if (response?.conflict) {
        await handleConflict(item.payload.localId || item.payload.id, response);
      } else {
        throw new Error(response?.message || 'Sync failed');
      }
    } catch (error: any) {
      await offlineDB.syncQueue.update(item.id, { 
        status: item.attempts >= 3 ? 'failed' : 'pending',
        updatedAt: Date.now(),
      });
      throw error;
    }
  }

  async function syncOrder(order: OfflineOrder): Promise<any> {
    const orderItems = await offlineDB.orderItems.where('orderLocalId').equals(order.localId).toArray();
    const payments = await offlineDB.payments.where('orderLocalId').equals(order.localId).toArray();
    
    const payload = {
      orders: [{
        id: order.id,
        outletId: order.outlet_id,
        orderType: order.order_type,
        tableId: order.table_id,
        shiftId: order.shift_id,
        orderNumber: order.order_number,
        deviceId: order.device_id,
        createdAtClient: order.created_at_client,
        subtotal: order.subtotal,
        discountTotal: order.discount_total,
        serviceChargeTotal: order.service_charge_total,
        pb1Total: order.pb1_total,
        roundingAdjustment: order.rounding_adjustment,
        grandTotal: order.grand_total,
        items: orderItems,
        payments: payments,
      }],
    };
    
    return await fetch('/api/orders/sync', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    }).then(r => r.json());
  }

  async function syncPayment(payment: OfflinePayment): Promise<any> {
    return await fetch(`/api/orders/${payment.order_id}/payments`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        method: payment.method,
        amount: payment.amount,
        referenceNumber: payment.reference_number,
      }),
    }).then(r => r.json());
  }

  async function syncBatch(batch: OrderBatch): Promise<any> {
    return await fetch(`/api/order-batches/${batch.id}/confirm`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'confirm' }),
    }).then(r => r.json());
  }

  async function markOrderSynced(localId: string): Promise<void> {
    const order = await offlineDB.orders.get(localId);
    if (order) {
      await offlineDB.orders.update(localId, {
        sync_status: 'synced',
        synced_at: new Date().toISOString(),
      });
    }
  }

  async function handleConflict(localId: string, response: any): Promise<void> {
    await offlineDB.orders.update(localId, {
      sync_status: 'conflict',
    });
    conflictCount.value++;
  }

  async function resolveConflict(localId: string, useServer: boolean): Promise<void> {
    if (useServer) {
      await offlineDB.orders.update(localId, { sync_status: 'synced' });
    } else {
      await offlineDB.orders.update(localId, { sync_status: 'pending' });
      await processSyncQueue();
    }
    await updateCounts();
  }

  async function getPendingOrders(): Promise<OfflineOrder[]> {
    return offlineDB.orders.where('sync_status').anyOf(['pending', 'conflict']).toArray();
  }

  async function getUnsyncedCount(): Promise<number> {
    return offlineDB.syncQueue.where('status').anyOf(['pending', 'failed']).count();
  }

  return {
    isOnline,
    syncStatus,
    lastSyncTime,
    pendingCount,
    conflictCount,
    failedCount,
    statusText,
    statusColor,
    initializeOnlineStatus,
    updateCounts,
    queueOrderForSync,
    queuePaymentForSync,
    processSyncQueue,
    resolveConflict,
    getPendingOrders,
    getUnsyncedCount,
    offlineDB,
  };
});
