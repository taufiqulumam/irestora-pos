import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';
import type { DiningTable, Order } from '@shared/types';

type RawDiningTable = Partial<DiningTable> & {
  current_order?: Order | null;
};

export const useTablesStore = defineStore('tables', () => {
  const tables = ref<DiningTable[]>([]);
  const loading = ref(false);
  const error = ref<string | null>(null);
  const lastFetched = ref<number | null>(null);

  const availableTables = computed(() => tables.value.filter(t => t.status === 'available'));
  const occupiedTables = computed(() => tables.value.filter(t => t.status === 'occupied'));
  const reservedTables = computed(() => tables.value.filter(t => t.status === 'reserved'));

  const counts = computed(() => ({
    total: tables.value.length,
    available: availableTables.value.length,
    occupied: occupiedTables.value.length,
    reserved: reservedTables.value.length,
  }));

  function normalizeTable(rawTable: RawDiningTable): DiningTable {
    return {
      ...rawTable,
      currentOrder: rawTable.currentOrder || rawTable.current_order || undefined,
    } as DiningTable;
  }

  async function fetchTables(outletId: string, force = false): Promise<DiningTable[]> {
    const now = Date.now();
    if (!force && lastFetched.value && now - lastFetched.value < 30000) {
      return tables.value;
    }

    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get(`/api/tables?outlet_id=${outletId}`);
      tables.value = response.data.data.map(normalizeTable);
      lastFetched.value = now;
      return tables.value;
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Gagal memuat meja';
      throw err;
    } finally {
      loading.value = false;
    }
  }

  function updateTableStatus(tableId: string, status: DiningTable['status'], currentOrderId?: string): void {
    const table = tables.value.find(t => t.id === tableId);
    if (table) {
      table.status = status;
      if (currentOrderId !== undefined) {
        table.current_order_id = currentOrderId;
      }
    }
  }

  function setTables(newTables: DiningTable[]): void {
    tables.value = newTables.map(normalizeTable);
    lastFetched.value = Date.now();
  }

  return {
    tables,
    loading,
    error,
    availableTables,
    occupiedTables,
    reservedTables,
    counts,
    fetchTables,
    updateTableStatus,
    setTables,
  };
});
