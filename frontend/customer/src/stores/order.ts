import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';
import type { Order, OrderBatch } from '@shared/types';

export const useOrderStore = defineStore('order', () => {
  const currentOrder = ref<Order | null>(null);
  const orderBatches = ref<OrderBatch[]>([]);
  const loading = ref(false);
  const error = ref<string | null>(null);

  async function submitCart(tableId: string, items: Array<{ menuId: string; qty: number; notes: string | null }>): Promise<OrderBatch> {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.post(`/api/tables/${tableId}/cart/submit`, { items });
      const batch = response.data.data;
      orderBatches.value.unshift(batch);
      return batch;
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Gagal mengirim pesanan';
      throw err;
    } finally {
      loading.value = false;
    }
  }

  async function fetchOrderStatus(tableId: string): Promise<Order | null> {
    try {
      const response = await axios.get(`/api/tables/${tableId}/order`);
      currentOrder.value = response.data.data || null;
      orderBatches.value = currentOrder.value?.batches || [];
      return currentOrder.value;
    } catch {
      currentOrder.value = null;
      orderBatches.value = [];
      return null;
    }
  }

  async function fetchOrderBatches(orderId: string): Promise<OrderBatch[]> {
    try {
      if (currentOrder.value?.id === orderId && currentOrder.value.batches) {
        orderBatches.value = currentOrder.value.batches;
        return orderBatches.value;
      }

      const response = await axios.get(`/api/orders/${orderId}/batches`);
      orderBatches.value = response.data.data;
      return orderBatches.value;
    } catch {
      return [];
    }
  }

  function updateBatchStatus(batchId: string, status: OrderBatch['status']): void {
    const batch = orderBatches.value.find(b => b.id === batchId);
    if (batch) {
      batch.status = status;
    }
  }

  function setCurrentOrder(order: Order | null): void {
    currentOrder.value = order;
  }

  function clearOrder(): void {
    currentOrder.value = null;
    orderBatches.value = [];
  }

  return {
    currentOrder,
    orderBatches,
    loading,
    error,
    submitCart,
    fetchOrderStatus,
    fetchOrderBatches,
    updateBatchStatus,
    setCurrentOrder,
    clearOrder,
  };
});
