import { ref } from 'vue';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';
import { useSyncStore } from '@/stores/sync';
import { useTablesStore } from '@/stores/tables';
import { useShiftStore } from '@/stores/shift';
import { generateUUID } from '@shared/utils';
import type { Order, OrderType, OrderItem } from '@shared/types';

export function useOrder() {
  const authStore = useAuthStore();
  const cartStore = useCartStore();
  const syncStore = useSyncStore();
  const tablesStore = useTablesStore();
  const shiftStore = useShiftStore();

  const loading = ref(false);
  const error = ref<string | null>(null);
  const currentOrder = ref<Order | null>(null);

  async function openOrder(orderType: OrderType, tableId?: string): Promise<Order> {
    if (loading.value && currentOrder.value) {
      return currentOrder.value;
    }

    if (currentOrder.value?.status === 'open'
      && currentOrder.value.order_type === orderType
      && (orderType === 'takeaway' || currentOrder.value.table_id === tableId)) {
      return currentOrder.value;
    }

    loading.value = true;
    error.value = null;

    try {
      const payload: { orderType: OrderType; tableId?: string } = { orderType };
      if (orderType === 'dine_in' && tableId) {
        payload.tableId = tableId;
      }

      const response = await axios.post('/api/orders', payload);
      const order = response.data.data;
      const normalizedOrder = {
        ...order,
        id: order.orderId,
        order_type: order.orderType,
        table_id: order.tableId,
      } as Order;

      currentOrder.value = normalizedOrder;

      if (orderType === 'dine_in' && tableId) {
        tablesStore.updateTableStatus(
          tableId,
          order.tableStatus || 'occupied',
          order.currentOrderId || order.orderId
        );
      }

      shiftStore.fetchActiveShift(authStore.outletId || '');

      return normalizedOrder;
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Gagal membuka order';
      throw err;
    } finally {
      loading.value = false;
    }
  }

  async function fetchOrder(orderId: string): Promise<Order> {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get(`/api/orders/${orderId}`);
      currentOrder.value = response.data.data;
      return currentOrder.value!;
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Gagal memuat order';
      throw err;
    } finally {
      loading.value = false;
    }
  }

  async function submitOrder(orderId: string): Promise<Order> {
    loading.value = true;
    error.value = null;

    try {
      const now = new Date().toISOString();
      const syncItems = cartStore.getItemsForSync();
      
      // Create proper OrderItem objects for the Order type
      const orderItems: OrderItem[] = syncItems.map((item) => ({
        id: generateUUID(),
        order_id: orderId,
        order_batch_id: '',
        menu_id: item.menuId,
        menu_name_snapshot: item.menuNameSnapshot,
        price_snapshot: item.priceSnapshot,
        qty: item.qty,
        notes: item.notes,
        status: 'active' as const,
        voided_by: null,
        void_reason: null,
        created_at: now,
        updated_at: now,
      }));

      const orderData = {
        id: orderId,
        outlet_id: authStore.outletId || '',
        order_type: currentOrder.value?.order_type || 'dine_in',
        table_id: currentOrder.value?.table_id || null,
        shift_id: currentOrder.value?.shift_id || shiftStore.currentShift?.id || '',
        cashier_id: authStore.user?.id || '',
        order_number: currentOrder.value?.order_number || generateOrderNumber(),
        status: 'open' as const,
        source: 'cashier' as const,
        device_id: authStore.deviceId,
        created_at_client: now,
        synced_at: null,
        sync_status: 'pending' as const,
        subtotal: cartStore.totals.subtotal,
        discount_total: cartStore.discountTotal,
        service_charge_total: cartStore.totals.serviceChargeTotal,
        pb1_total: cartStore.totals.pb1Total,
        rounding_adjustment: cartStore.totals.roundingAdjustment,
        grand_total: cartStore.totals.grandTotal,
        items: orderItems,
        payments: [],
        created_at: now,
        updated_at: now,
      };

      if (syncStore.isOnline) {
        const syncPayload = {
          id: orderData.id,
          outletId: orderData.outlet_id,
          orderType: orderData.order_type,
          tableId: orderData.table_id,
          shiftId: orderData.shift_id,
          cashierId: orderData.cashier_id,
          orderNumber: orderData.order_number,
          deviceId: orderData.device_id,
          createdAtClient: orderData.created_at_client,
          subtotal: orderData.subtotal,
          discountTotal: orderData.discount_total,
          serviceChargeTotal: orderData.service_charge_total,
          pb1Total: orderData.pb1_total,
          roundingAdjustment: orderData.rounding_adjustment,
          grandTotal: orderData.grand_total,
          items: orderData.items.map(item => ({
            menuId: item.menu_id,
            menuNameSnapshot: item.menu_name_snapshot,
            priceSnapshot: item.price_snapshot,
            qty: item.qty,
            notes: item.notes,
          })),
          payments: [],
        };
        const response = await axios.post('/api/orders/sync', { orders: [syncPayload] });
        const result = response.data.data[0];
        
        if (result.status === 'synced' || result.status === 'already_synced') {
          currentOrder.value = { ...currentOrder.value, ...result, sync_status: 'synced' } as Order;
        } else if (result.status === 'conflict') {
          currentOrder.value = { ...currentOrder.value, sync_status: 'conflict' } as Order;
          throw new Error('Konflik sinkronisasi');
        }
      } else {
        await syncStore.queueOrderForSync(orderData);
        currentOrder.value = { ...orderData, sync_status: 'pending' } as Order;
      }

      return currentOrder.value!;
    } catch (err: any) {
      error.value = err.message || 'Gagal memproses order';
      throw err;
    } finally {
      loading.value = false;
    }
  }

  async function addPayment(orderId: string, method: 'cash' | 'qris' | 'card' | 'other', amount: number, referenceNumber?: string): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
      const paymentData = {
        method,
        amount,
        reference_number: referenceNumber ?? null,
      };

      if (syncStore.isOnline) {
        await axios.post(`/api/orders/${orderId}/payments`, paymentData);
      } else {
        await syncStore.queuePaymentForSync({
          id: generateUUID(),
          order_id: orderId,
          method,
          amount,
          reference_number: referenceNumber ?? null,
          orderLocalId: currentOrder.value?.id || orderId,
        });
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Gagal menambahkan pembayaran';
      throw err;
    } finally {
      loading.value = false;
    }
  }

  async function voidOrder(orderId: string, reason: string, approvedByPin: string): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
      await axios.post(`/api/orders/${orderId}/void`, {
        reason,
        approved_by_pin: approvedByPin,
      });
      
      if (currentOrder.value?.table_id) {
        tablesStore.updateTableStatus(currentOrder.value.table_id, 'available');
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Gagal membatalkan order';
      throw err;
    } finally {
      loading.value = false;
    }
  }

  function generateOrderNumber(): string {
    const now = new Date();
    const dateStr = now.toISOString().slice(0, 10).replace(/-/g, '');
    const random = Math.random().toString(36).substring(2, 8).toUpperCase();
    return `${authStore.user?.outlet?.code || 'OUT'}/${dateStr}/${random}`;
  }

  function clearCurrentOrder(): void {
    currentOrder.value = null;
  }

  return {
    loading,
    error,
    currentOrder,
    openOrder,
    fetchOrder,
    submitOrder,
    addPayment,
    voidOrder,
    clearCurrentOrder,
  };
}