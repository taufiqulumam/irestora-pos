import { defineStore } from 'pinia';
import { ref, computed, shallowRef, type ShallowRef } from 'vue';
import axios from 'axios';
import type { DiningTable } from '@shared/types';

export const useTableStore = defineStore('table', () => {
  const _table = shallowRef<DiningTable | null>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);

  const isValid = computed(() => !!_table.value);

  async function fetchTable(tableId: string): Promise<DiningTable | null> {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get(`/api/tables/${tableId}`);
      _table.value = response.data.data;
      return _table.value;
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Meja tidak ditemukan';
      _table.value = null;
      throw err;
    } finally {
      loading.value = false;
    }
  }

  function clearTable(): void {
    _table.value = null;
  }

  return {
    table: _table as ShallowRef<DiningTable | null>,
    loading,
    error,
    isValid,
    fetchTable,
    clearTable,
  };
});