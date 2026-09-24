import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';
import type { Shift } from '@shared/types';

const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

export const useShiftStore = defineStore('shift', () => {
  const currentShift = ref<Shift | null>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);

  const isShiftOpen = computed(() => currentShift.value !== null && !currentShift.value.closed_at);
  const openingCash = computed(() => currentShift.value?.opening_cash || 0);
  const expectedClosingCash = computed(() => currentShift.value?.closing_cash_expected || 0);
  const actualClosingCash = computed(() => currentShift.value?.closing_cash_actual || 0);
  const cashDifference = computed(() => currentShift.value?.cash_difference || 0);

  async function openShift(outletId: string, openingCashAmount: number): Promise<Shift | null> {
    loading.value = true;
    error.value = null;
    
    try {
      const response = await axios.post('/api/shifts/open', {
        outlet_id: outletId,
        opening_cash: openingCashAmount,
      });
      
      currentShift.value = response.data.data;
      return currentShift.value;
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Gagal membuka shift';
      throw err;
    } finally {
      loading.value = false;
    }
  }

  async function closeShift(shiftId: string, closingCashActual: number): Promise<Shift | null> {
    loading.value = true;
    error.value = null;
    
    try {
      const response = await axios.post(`/api/shifts/${shiftId}/close`, {
        closing_cash_actual: closingCashActual,
      });
      
      currentShift.value = response.data.data;
      return currentShift.value;
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Gagal menutup shift';
      throw err;
    } finally {
      loading.value = false;
    }
  }

  async function fetchActiveShift(outletId: string): Promise<Shift | null> {
    if (!UUID_PATTERN.test(outletId)) {
      currentShift.value = null;
      return null;
    }

    try {
      const response = await axios.get('/api/shifts/active', {
        params: { outlet_id: outletId },
      });
      currentShift.value = response.data.data || null;
      return currentShift.value;
    } catch {
      currentShift.value = null;
      return null;
    }
  }

  function clearShift(): void {
    currentShift.value = null;
  }

  return {
    currentShift,
    loading,
    error,
    isShiftOpen,
    openingCash,
    expectedClosingCash,
    actualClosingCash,
    cashDifference,
    openShift,
    closeShift,
    fetchActiveShift,
    clearShift,
  };
});
