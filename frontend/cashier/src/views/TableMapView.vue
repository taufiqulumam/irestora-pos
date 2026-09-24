<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, type Ref } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useTablesStore } from '@/stores/tables';
import { useShiftStore } from '@/stores/shift';
import { useReverb } from '@/composables/useReverb';
import { useSyncStore } from '@/stores/sync';
import {
  ORDER_STATUS_LABELS,
  TABLE_STATUS_LABELS,
  TABLE_STATUS_COLORS,
  SYNC_STATUS_LABELS,
} from '@shared/constants';
import { calculateOrderTotals } from '@shared/calculator';
import { formatCurrency, formatDate } from '@shared/utils';
import type { DiningTable, Order, OrderBatch, OrderItem } from '@shared/types';

const router = useRouter();
const authStore = useAuthStore();
const tablesStore = useTablesStore();
const shiftStore = useShiftStore();
const syncStore = useSyncStore();
const { initialize: initReverb, connected: reverbConnected, reconnect } = useReverb();

const selectedTable = ref<DiningTable | null>(null);
const showOrderTypeModal = ref(false);
const orderType = ref<'dine_in' | 'takeaway'>('dine_in') as Ref<'dine_in' | 'takeaway'>;
const loading = ref(false);
const actionError = ref<string | null>(null);
const tablePendingClose = ref<DiningTable | null>(null);
const orderDetailTable = ref<DiningTable | null>(null);
const orderDetail = ref<Order | null>(null);
const orderDetailLoading = ref(false);
const orderDetailError = ref<string | null>(null);
const batchActionLoading = ref<Record<string, boolean>>({});

function isDineIn(): boolean {
  return (orderType.value as 'dine_in' | 'takeaway') === 'dine_in';
}

function isTakeaway(): boolean {
  return (orderType.value as 'dine_in' | 'takeaway') === 'takeaway';
}

interface TableStats {
  total: number;
  available: number;
  occupied: number;
  reserved: number;
}

const stats = computed<TableStats>(() => ({
  total: tablesStore.counts.total,
  available: tablesStore.counts.available,
  occupied: tablesStore.counts.occupied,
  reserved: tablesStore.counts.reserved,
}));

const formatCurrencyLocal = formatCurrency;
const formatDateLocal = formatDate;
const orderStatusColors: Record<string, string> = {
  open: '#F59E0B',
  paid: '#10B981',
  void: '#EF4444',
  cancelled: '#EF4444',
};

function getOrderStatusLabel(status?: string | null): string {
  return status
    ? ORDER_STATUS_LABELS[status as keyof typeof ORDER_STATUS_LABELS] || status
    : 'Tidak diketahui';
}

function getOrderStatusColor(status?: string | null): string {
  return status ? orderStatusColors[status] || '#64748B' : '#64748B';
}

const selectedOrder = computed(() => orderDetail.value || orderDetailTable.value?.currentOrder || null);
const selectedOrderBatches = computed<OrderBatch[]>(() => selectedOrder.value?.batches || []);
const selectedOrderItems = computed<OrderItem[]>(() => {
  const order = selectedOrder.value;
  if (!order) return [];
  if (order.items?.length) return order.items;

  return (order.batches || []).flatMap((batch: OrderBatch) => batch.items || []);
});

const batchStatusLabels: Record<string, string> = {
  pending_confirmation: 'Menunggu diterima kasir',
  confirmed: 'Diterima',
  rejected: 'Ditolak',
};

const batchStatusClasses: Record<string, string> = {
  pending_confirmation: 'bg-amber-50 text-amber-700 border-amber-200',
  confirmed: 'bg-green-50 text-green-700 border-green-200',
  rejected: 'bg-red-50 text-red-700 border-red-200',
};

const selectedOrderTotals = computed(() => {
  const order = selectedOrder.value;
  const items = selectedOrderItems.value;

  if (!order) {
    return {
      subtotal: 0,
      discount_total: 0,
      service_charge_total: 0,
      pb1_total: 0,
      rounding_adjustment: 0,
      grand_total: 0,
    };
  }

  if ((Number(order.subtotal) || 0) > 0 || items.length === 0) {
    return {
      subtotal: Number(order.subtotal) || 0,
      discount_total: Number(order.discount_total) || 0,
      service_charge_total: Number(order.service_charge_total) || 0,
      pb1_total: Number(order.pb1_total) || 0,
      rounding_adjustment: Number(order.rounding_adjustment) || 0,
      grand_total: Number(order.grand_total) || 0,
    };
  }

  const outlet = (order as any).outlet || {};
  const activeItems = items.filter(item => item.status === 'active');
  const calculated = calculateOrderTotals(
    activeItems.map(item => ({
      priceSnapshot: Number(item.price_snapshot) || 0,
      qty: Number(item.qty) || 0,
    })),
    Number(order.discount_total) || 0,
    {
      serviceChargeRate: Number(outlet.service_charge_rate) || 5,
      pb1Rate: Number(outlet.pb1_rate) || 10,
      roundingEnabled: outlet.rounding_enabled ?? true,
    }
  );

  return {
    subtotal: calculated.subtotal,
    discount_total: calculated.discountTotal,
    service_charge_total: calculated.serviceChargeTotal,
    pb1_total: calculated.pb1Total,
    rounding_adjustment: calculated.roundingAdjustment,
    grand_total: calculated.grandTotal,
  };
});

async function showOrderDetail(table: DiningTable) {
  if (!table.current_order_id) return;

  orderDetailTable.value = table;
  orderDetail.value = table.currentOrder || null;
  orderDetailError.value = null;
  orderDetailLoading.value = true;

  try {
    const response = await axios.get(`/api/orders/${table.current_order_id}`);
    orderDetail.value = response.data.data;
  } catch (error: any) {
    orderDetailError.value =
      error.response?.data?.message || 'Detail order belum dapat dimuat. Menampilkan data yang tersedia.';
  } finally {
    orderDetailLoading.value = false;
  }
}

async function refreshOrderDetail() {
  const orderId = orderDetailTable.value?.current_order_id || selectedOrder.value?.id;
  if (!orderId) return;

  const response = await axios.get(`/api/orders/${orderId}`);
  orderDetail.value = response.data.data;
}

function getBatchSubtotal(batch: OrderBatch): number {
  return (batch.items || []).reduce(
    (sum, item) => sum + (Number(item.price_snapshot) || 0) * (Number(item.qty) || 0),
    0
  );
}

function getBatchStatusLabel(status: string): string {
  return batchStatusLabels[status] || status;
}

function getBatchStatusClass(status: string): string {
  return batchStatusClasses[status] || 'bg-surface-50 text-surface-600 border-surface-200';
}

async function updateBatchStatus(batch: OrderBatch, action: 'confirm' | 'reject') {
  if (batchActionLoading.value[batch.id]) return;

  batchActionLoading.value = { ...batchActionLoading.value, [batch.id]: true };
  orderDetailError.value = null;

  try {
    await axios.post(`/api/order-batches/${batch.id}/confirm`, {
      action,
      reason: action === 'reject' ? 'Pesanan ditolak dari kasir' : undefined,
    });
    await refreshOrderDetail();
    await refreshTables();
  } catch (error: any) {
    orderDetailError.value =
      error.response?.data?.message || 'Status pesanan belum dapat diperbarui.';
  } finally {
    const next = { ...batchActionLoading.value };
    delete next[batch.id];
    batchActionLoading.value = next;
  }
}

function closeOrderDetail() {
  if (orderDetailLoading.value) return;
  orderDetailTable.value = null;
  orderDetail.value = null;
  orderDetailError.value = null;
  batchActionLoading.value = {};
}

function openOrderPage() {
  const table = orderDetailTable.value;
  if (!table?.current_order_id) return;

  router.push(`/order/${table.id}?type=dine_in&orderId=${table.current_order_id}`);
}

async function handleTableClick(table: DiningTable) {
  if (table.status === 'occupied' && table.current_order_id) {
    router.push(`/order/${table.id}?type=dine_in&orderId=${table.current_order_id}`);
  } else if (table.status === 'available') {
    orderType.value = 'dine_in';
    selectedTable.value = table;
    showOrderTypeModal.value = true;
  }
}

async function confirmOrderType() {
  if (!selectedTable.value) return;

  const table = selectedTable.value;
  showOrderTypeModal.value = false;

  try {
    await shiftStore.fetchActiveShift(authStore.outletId || '');
    if (!shiftStore.isShiftOpen) {
      router.push('/shift/open');
      return;
    }

    router.push(`/order/${table.id}?type=${orderType.value}`);
  } catch {
    router.push('/shift/open');
  }
}

async function handleTakeaway() {
  try {
    await shiftStore.fetchActiveShift(authStore.outletId || '');
    if (!shiftStore.isShiftOpen) {
      router.push('/shift/open');
      return;
    }
    router.push('/order?type=takeaway');
  } catch {
    router.push('/shift/open');
  }
}

function selectDineIn() {
  orderType.value = 'dine_in';
  confirmOrderType();
}

function selectTakeaway() {
  orderType.value = 'takeaway';
  handleTakeaway();
}

function handleLogout() {
  authStore.logout();
  router.push('/login');
}

async function refreshTables() {
  if (authStore.outletId) {
    await tablesStore.fetchTables(authStore.outletId, true);
  }
}

function requestCloseTable(table: DiningTable) {
  if (!table.current_order_id || loading.value) return;
  actionError.value = null;
  tablePendingClose.value = table;
}

function cancelCloseTable() {
  if (!loading.value) tablePendingClose.value = null;
}

async function closeTable() {
  const table = tablePendingClose.value;
  if (!table?.current_order_id || table.currentOrder?.status !== 'paid') return;

  loading.value = true;
  actionError.value = null;
  try {
    await axios.post(`/api/tables/${table.id}/close`, { reason: 'Meja ditutup dari Peta Meja' });
    tablesStore.updateTableStatus(table.id, 'available', '');
    tablePendingClose.value = null;
    await refreshTables();
  } catch (error: any) {
    actionError.value =
      error.response?.data?.message || 'Meja belum dapat ditutup. Pastikan order sudah lunas.';
  } finally {
    loading.value = false;
  }
}

async function initialize() {
  if (!authStore.isAuthenticated) {
    router.push('/login');
    return;
  }

  await shiftStore.fetchActiveShift(authStore.outletId || '');

  if (!shiftStore.isShiftOpen) {
    router.push('/shift/open');
    return;
  }

  if (authStore.outletId) {
    await tablesStore.fetchTables(authStore.outletId);
  }

  initReverb();

  setInterval(refreshTables, 30000);
}

onMounted(() => {
  initialize();
});

onUnmounted(() => {
  // cleanup handled by composable
});
</script>

<template>
  <div class="h-screen flex flex-col bg-surface-50">
    <!-- Header -->
    <header class="bg-white border-b border-surface-200 px-4 py-3 sticky top-0 z-10">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-primary-500 flex items-center justify-center">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"
              />
            </svg>
          </div>
          <div>
            <h1 class="font-bold text-surface-900">iRestora POS</h1>
            <p class="text-xs text-surface-500">{{ authStore.user?.outlet?.name }}</p>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <!-- Sync Status -->
          <div
            :class="[
              'flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium',
              syncStore.statusColor === 'green' && 'bg-green-50 text-green-700',
              syncStore.statusColor === 'yellow' && 'bg-yellow-50 text-yellow-700',
              syncStore.statusColor === 'orange' && 'bg-orange-50 text-orange-700',
              syncStore.statusColor === 'red' && 'bg-red-50 text-red-700',
              syncStore.statusColor === 'blue' && 'bg-blue-50 text-blue-700',
            ]"
          >
            <span
              :class="[
                syncStore.statusColor === 'green' && 'bg-green-500',
                syncStore.statusColor === 'yellow' && 'bg-yellow-500',
                syncStore.statusColor === 'orange' && 'bg-orange-500',
                syncStore.statusColor === 'red' && 'bg-red-500',
                syncStore.statusColor === 'blue' && 'animate-pulse bg-blue-500',
              ]"
              class="w-2 h-2 rounded-full"
            ></span>
            {{ syncStore.statusText }}
          </div>

          <!-- Reverb Status -->
          <div
            class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium"
            :class="reverbConnected ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
          >
            <span
              :class="reverbConnected ? 'bg-green-500' : 'bg-red-500'"
              class="w-2 h-2 rounded-full"
            ></span>
            {{ reverbConnected ? 'WS Terhubung' : 'WS Putus' }}
          </div>

          <!-- Shift Info -->
          <div
            class="flex items-center gap-2 px-3 py-1.5 bg-primary-50 text-primary-700 rounded-full text-sm font-medium"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            <span>{{ authStore.user?.full_name }}</span>
          </div>

          <button
            @click="handleLogout"
            class="flex items-center gap-2 rounded-lg border border-surface-200 bg-white px-3 py-1.5 text-sm font-medium text-surface-600 transition-colors hover:bg-surface-50 hover:text-red-600"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M15 12H3m0 0l4-4m-4 4l4 4M9 4h8a2 2 0 012 2v12a2 2 0 01-2 2H9"
              />
            </svg>
            Logout
          </button>
        </div>
      </div>
    </header>

    <!-- Stats Bar -->
    <div class="px-4 py-3 bg-white border-b border-surface-200 flex gap-3 overflow-x-auto">
      <div class="flex items-center gap-2 px-3 py-2 bg-green-50 rounded-xl min-w-[140px]">
        <span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>
        <div>
          <p class="text-xs text-surface-500">Tersedia</p>
          <p class="font-bold text-green-700">{{ stats.available }}</p>
        </div>
      </div>
      <div class="flex items-center gap-2 px-3 py-2 bg-yellow-50 rounded-xl min-w-[140px]">
        <span class="w-2.5 h-2.5 rounded-full bg-yellow-500"></span>
        <div>
          <p class="text-xs text-surface-500">Terisi</p>
          <p class="font-bold text-yellow-700">{{ stats.occupied }}</p>
        </div>
      </div>
      <div class="flex items-center gap-2 px-3 py-2 bg-blue-50 rounded-xl min-w-[140px]">
        <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
        <div>
          <p class="text-xs text-surface-500">Reserved</p>
          <p class="font-bold text-blue-700">{{ stats.reserved }}</p>
        </div>
      </div>
    </div>

    <!-- Table Grid -->
    <main class="flex-1 overflow-y-auto p-4">
      <div
        v-if="actionError"
        class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
      >
        {{ actionError }}
      </div>

      <div
        v-if="tablesStore.loading && tablesStore.tables.length === 0"
        class="flex items-center justify-center h-64"
      >
        <div class="text-center">
          <svg class="animate-spin mx-auto h-8 w-8 text-primary-500" viewBox="0 0 24 24">
            <circle
              class="opacity-25"
              cx="12"
              cy="12"
              r="10"
              stroke="currentColor"
              stroke-width="4"
              fill="none"
            ></circle>
            <path
              class="opacity-75"
              fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
            ></path>
          </svg>
          <p class="mt-2 text-surface-500">Memuat peta meja...</p>
        </div>
      </div>

      <div
        v-else-if="tablesStore.tables.length === 0"
        class="flex items-center justify-center h-64"
      >
        <div class="text-center">
          <svg
            class="mx-auto h-12 w-12 text-surface-300"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"
            />
          </svg>
          <p class="mt-4 text-surface-500">Belum ada meja. Tambahkan meja dari Admin Panel.</p>
        </div>
      </div>

      <div
        v-else
        class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4"
      >
        <div
          v-for="table in tablesStore.tables"
          :key="table.id"
          @click="handleTableClick(table)"
          :class="[
            'relative p-4 rounded-2xl transition-all duration-200 cursor-pointer',
            'bg-white shadow-sm',
            table.status === 'available' &&
              'border-2 border-green-200 hover:border-green-400 hover:shadow-md',
            table.status === 'occupied' &&
              'border-2 border-yellow-300 hover:border-yellow-400 hover:shadow-md',
            table.status === 'reserved' &&
              'border-2 border-blue-300 hover:border-blue-400 hover:shadow-md',
          ]"
        >
          <!-- Status Badge -->
          <div class="absolute top-3 right-3">
            <span
              :style="{
                backgroundColor: TABLE_STATUS_COLORS[table.status] + '20',
                color: TABLE_STATUS_COLORS[table.status],
              }"
              class="px-2 py-0.5 text-xs font-semibold rounded-full"
            >
              {{ TABLE_STATUS_LABELS[table.status] }}
            </span>
          </div>

          <!-- Table Name -->
          <h3 class="text-lg font-bold text-surface-900 mb-2">{{ table.name }}</h3>

          <!-- Table Details -->
          <div v-if="table.status === 'occupied'" class="space-y-1.5 text-sm">
            <div class="flex items-center gap-1.5 text-surface-600">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
                />
              </svg>
              <span
                >{{
                  table.currentOrder?.items?.reduce((sum, i) => sum + i.qty, 0) || '—'
                }}
                Items</span
              >
            </div>
            <div class="flex items-center gap-1.5 text-primary-600 font-medium">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
              <span>{{ formatCurrencyLocal(table.currentOrder?.grand_total || 0) }}</span>
            </div>
          </div>

          <div v-else-if="table.status === 'reserved'" class="space-y-1.5 text-sm text-surface-600">
            <div class="flex items-center gap-1.5">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                />
              </svg>
              <span>{{ formatDateLocal(table.currentOrder?.created_at_client || '') }}</span>
            </div>
            <div class="font-medium text-surface-900">
              {{ table.currentOrder?.cashier?.full_name || 'Pelanggan' }}
            </div>
          </div>

          <div v-else class="text-surface-400 text-sm mt-2">Siap melayani order baru</div>

          <!-- Quick Action for occupied -->
          <div v-if="table.status === 'occupied'" class="mt-3 pt-3 border-t border-surface-100">
            <button
              @click.stop="showOrderDetail(table)"
              class="w-full py-2 bg-primary-50 text-primary-700 text-sm font-medium rounded-lg hover:bg-primary-100 transition-colors"
            >
              Kelola Order
            </button>
            <button
              @click.stop="requestCloseTable(table)"
              :disabled="loading"
              class="mt-2 w-full py-2 bg-green-50 text-green-700 text-sm font-medium rounded-lg hover:bg-green-100 transition-colors disabled:opacity-50"
            >
              Tutup Meja
            </button>
          </div>
        </div>
      </div>
    </main>

    <!-- FAB for Takeaway -->
    <button
      @click="handleTakeaway"
      class="fixed bottom-6 right-6 w-14 h-14 rounded-full bg-primary-500 text-white shadow-lg hover:bg-primary-600 transition-all flex items-center justify-center z-20"
      :disabled="loading"
    >
      <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"
        />
      </svg>
    </button>

    <!-- Order Type Modal -->
    <div
      v-if="showOrderTypeModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
    >
      <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
        <div class="text-center mb-6">
          <h2 class="text-xl font-bold text-surface-900">Pilih Tipe Order</h2>
          <p class="text-surface-500 mt-1">Meja: {{ selectedTable?.name }}</p>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-6">
          <button
            @click="selectDineIn"
            :class="[
              'p-4 rounded-xl border-2 transition-all text-center',
              isDineIn()
                ? 'border-primary-500 bg-primary-50'
                : 'border-surface-200 hover:border-surface-300',
            ]"
          >
            <svg
              class="w-8 h-8 mx-auto mb-2"
              :class="isDineIn() ? 'text-primary-500' : 'text-surface-400'"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
              />
            </svg>
            <p :class="isDineIn() ? 'text-primary-700 font-semibold' : 'text-surface-600'">
              Makan di Tempat
            </p>
          </button>

          <button
            @click="selectTakeaway"
            :class="[
              'p-4 rounded-xl border-2 transition-all text-center',
              isTakeaway()
                ? 'border-primary-500 bg-primary-50'
                : 'border-surface-200 hover:border-surface-300',
            ]"
          >
            <svg
              class="w-8 h-8 mx-auto mb-2"
              :class="isTakeaway() ? 'text-primary-500' : 'text-surface-400'"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"
              />
            </svg>
            <p :class="isTakeaway() ? 'text-primary-700 font-semibold' : 'text-surface-600'">
              Bawa Pulang
            </p>
          </button>
        </div>

        <button
          @click="showOrderTypeModal = false"
          class="w-full py-2.5 text-surface-600 hover:text-surface-900 font-medium transition-colors"
        >
          Batal
        </button>
      </div>
    </div>

    <!-- Order Detail Modal -->
    <div
      v-if="orderDetailTable"
      class="fixed inset-0 z-[60] flex items-center justify-center bg-surface-900/60 px-4"
      @click.self="closeOrderDetail"
    >
      <section
        class="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-2xl bg-white shadow-2xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="order-detail-title"
      >
        <header class="flex items-start justify-between gap-4 border-b border-surface-100 p-6">
          <div>
            <p class="text-sm font-medium text-surface-500">{{ orderDetailTable.name }}</p>
            <h2 id="order-detail-title" class="mt-1 text-xl font-bold text-surface-900">
              Detail Pesanan
            </h2>
            <p class="mt-1 text-sm text-surface-500">
              {{ selectedOrder?.order_number || orderDetailTable.current_order_id }}
            </p>
          </div>
          <button
            type="button"
            class="rounded-lg p-2 text-surface-400 transition-colors hover:bg-surface-100 hover:text-surface-700"
            @click="closeOrderDetail"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </header>

        <div class="overflow-y-auto p-6">
          <div v-if="orderDetailError" class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            {{ orderDetailError }}
          </div>

          <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl bg-surface-50 px-4 py-3">
              <p class="text-xs font-medium text-surface-500">Status</p>
              <span
                class="mt-2 inline-flex rounded-full px-3 py-1 text-sm font-semibold"
                :style="{
                  backgroundColor: getOrderStatusColor(selectedOrder?.status) + '20',
                  color: getOrderStatusColor(selectedOrder?.status),
                }"
              >
                {{ getOrderStatusLabel(selectedOrder?.status) }}
              </span>
            </div>
            <div class="rounded-xl bg-surface-50 px-4 py-3">
              <p class="text-xs font-medium text-surface-500">Kasir</p>
              <p class="mt-2 font-semibold text-surface-900">
                {{ selectedOrder?.cashier?.full_name || '-' }}
              </p>
            </div>
            <div class="rounded-xl bg-surface-50 px-4 py-3">
              <p class="text-xs font-medium text-surface-500">Waktu Order</p>
              <p class="mt-2 font-semibold text-surface-900">
                {{ selectedOrder?.created_at_client ? formatDateLocal(selectedOrder.created_at_client) : '-' }}
              </p>
            </div>
          </div>

          <div class="mt-6">
            <div class="mb-3 flex items-center justify-between">
              <h3 class="font-semibold text-surface-900">
                {{ selectedOrderBatches.length > 0 ? 'Batch Pesanan' : 'Item Pesanan' }}
              </h3>
              <span class="text-sm text-surface-500">{{ selectedOrderItems.length }} item</span>
            </div>

            <div v-if="orderDetailLoading" class="flex h-24 items-center justify-center">
              <svg class="h-6 w-6 animate-spin text-primary-500" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
              </svg>
            </div>

            <div v-else-if="selectedOrderItems.length === 0" class="rounded-xl border border-dashed border-surface-200 px-4 py-8 text-center text-sm text-surface-500">
              Belum ada item pesanan.
            </div>

            <div v-else-if="selectedOrderBatches.length > 0" class="space-y-3">
              <div
                v-for="batch in selectedOrderBatches"
                :key="batch.id"
                class="rounded-xl border border-surface-100 bg-white"
              >
                <div class="flex flex-col gap-3 border-b border-surface-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                  <div>
                    <p class="font-semibold text-surface-900">Batch ke-{{ batch.batch_number }}</p>
                    <p class="mt-0.5 text-xs text-surface-500">
                      {{ batch.submitted_at ? formatDateLocal(batch.submitted_at) : '-' }}
                    </p>
                  </div>
                  <div class="flex flex-wrap items-center gap-2">
                    <span
                      class="rounded-full border px-3 py-1 text-xs font-semibold"
                      :class="getBatchStatusClass(batch.status)"
                    >
                      {{ getBatchStatusLabel(batch.status) }}
                    </span>
                    <template v-if="batch.status === 'pending_confirmation'">
                      <button
                        type="button"
                        class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 transition-colors hover:bg-red-50 disabled:opacity-50"
                        :disabled="batchActionLoading[batch.id]"
                        @click="updateBatchStatus(batch, 'reject')"
                      >
                        Tolak
                      </button>
                      <button
                        type="button"
                        class="rounded-lg bg-primary-500 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-primary-600 disabled:opacity-50"
                        :disabled="batchActionLoading[batch.id]"
                        @click="updateBatchStatus(batch, 'confirm')"
                      >
                        Terima
                      </button>
                    </template>
                  </div>
                </div>
                <div class="divide-y divide-surface-100">
                  <div
                    v-for="item in batch.items || []"
                    :key="item.id"
                    class="flex items-start justify-between gap-4 px-4 py-3"
                    :class="item.status === 'voided' ? 'opacity-50' : ''"
                  >
                    <div class="min-w-0">
                      <p class="font-medium text-surface-900">{{ item.menu_name_snapshot || item.menu?.name }}</p>
                      <p v-if="item.notes" class="mt-1 text-xs text-surface-500">{{ item.notes }}</p>
                      <p class="mt-1 text-xs text-surface-400">{{ formatCurrencyLocal(item.price_snapshot) }} x {{ item.qty }}</p>
                    </div>
                    <p class="shrink-0 font-semibold text-surface-900">
                      {{ formatCurrencyLocal(item.price_snapshot * item.qty) }}
                    </p>
                  </div>
                </div>
                <div class="flex justify-between px-4 py-3 text-sm font-semibold text-surface-700">
                  <span>Subtotal Batch</span>
                  <span>{{ formatCurrencyLocal(getBatchSubtotal(batch)) }}</span>
                </div>
              </div>
            </div>

            <div v-else class="divide-y divide-surface-100 rounded-xl border border-surface-100">
              <div
                v-for="item in selectedOrderItems"
                :key="item.id"
                class="flex items-start justify-between gap-4 px-4 py-3"
              >
                <div class="min-w-0">
                  <p class="font-medium text-surface-900">{{ item.menu_name_snapshot || item.menu?.name }}</p>
                  <p v-if="item.notes" class="mt-1 text-xs text-surface-500">{{ item.notes }}</p>
                  <p class="mt-1 text-xs text-surface-400">{{ formatCurrencyLocal(item.price_snapshot) }} x {{ item.qty }}</p>
                </div>
                <p class="shrink-0 font-semibold text-surface-900">
                  {{ formatCurrencyLocal(item.price_snapshot * item.qty) }}
                </p>
              </div>
            </div>
          </div>

          <div class="mt-6 space-y-2 rounded-xl bg-surface-50 px-4 py-3 text-sm">
            <div class="flex justify-between text-surface-600">
              <span>Subtotal</span>
              <span class="font-medium">{{ formatCurrencyLocal(selectedOrderTotals.subtotal) }}</span>
            </div>
            <div class="flex justify-between text-surface-600">
              <span>Diskon</span>
              <span class="font-medium">-{{ formatCurrencyLocal(selectedOrderTotals.discount_total) }}</span>
            </div>
            <div class="flex justify-between text-surface-600">
              <span>Service</span>
              <span class="font-medium">{{ formatCurrencyLocal(selectedOrderTotals.service_charge_total) }}</span>
            </div>
            <div class="flex justify-between text-surface-600">
              <span>PB1</span>
              <span class="font-medium">{{ formatCurrencyLocal(selectedOrderTotals.pb1_total) }}</span>
            </div>
            <div class="flex justify-between text-surface-600">
              <span>Rounding</span>
              <span class="font-medium">{{ formatCurrencyLocal(selectedOrderTotals.rounding_adjustment) }}</span>
            </div>
            <div class="border-t border-surface-200 pt-2 flex justify-between text-lg font-bold text-surface-900">
              <span>Total</span>
              <span class="text-primary-600">{{ formatCurrencyLocal(selectedOrderTotals.grand_total) }}</span>
            </div>
          </div>
        </div>

        <footer class="flex gap-3 border-t border-surface-100 p-6">
          <button
            type="button"
            class="flex-1 rounded-xl border border-surface-200 px-4 py-3 text-sm font-semibold text-surface-700 transition-colors hover:bg-surface-50"
            @click="closeOrderDetail"
          >
            Tutup
          </button>
          <button
            type="button"
            class="flex-1 rounded-xl bg-primary-500 px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-600"
            @click="openOrderPage"
          >
            Lanjut Kelola
          </button>
        </footer>
      </section>
    </div>

    <!-- Close Table Modal -->
    <div
      v-if="tablePendingClose"
      class="fixed inset-0 z-[60] flex items-center justify-center bg-surface-900/60 px-4"
      @click.self="cancelCloseTable"
    >
      <section
        class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="close-table-title"
      >
        <div class="flex items-center gap-4">
          <div
            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700"
          >
            <svg
              class="h-6 w-6"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 9v4m0 4h.01M10.3 3.8L2.9 17a2 2 0 001.7 3h14.8a2 2 0 001.7-3L13.7 3.8a2 2 0 00-3.4 0z"
              />
            </svg>
          </div>
          <h2 id="close-table-title" class="text-xl font-bold text-surface-900">Tutup meja</h2>
        </div>
        <div class="mt-4 flex items-center justify-between rounded-xl bg-surface-50 px-4 py-3">
          <span class="text-sm font-medium text-surface-500">Status Order</span>
          <span
            class="rounded-full px-3 py-1 text-sm font-semibold"
            :style="{
              backgroundColor: getOrderStatusColor(tablePendingClose.currentOrder?.status) + '20',
              color: getOrderStatusColor(tablePendingClose.currentOrder?.status),
            }"
          >
            {{ getOrderStatusLabel(tablePendingClose.currentOrder?.status) }}
          </span>
        </div>
        <p class="mt-2 text-sm leading-6 text-surface-500">
          {{ tablePendingClose.name }} akan dikembalikan menjadi tersedia dan order aktifnya
          dilepas. Pastikan pembayaran order sudah lunas.
        </p>
        <div class="mt-6 flex gap-3">
          <button
            type="button"
            class="flex-1 rounded-xl border border-surface-200 px-4 py-3 text-sm font-semibold text-surface-700 hover:bg-surface-50 disabled:opacity-50"
            :disabled="loading"
            @click="cancelCloseTable"
          >
            Batal
          </button>
          <button
            type="button"
            class="flex-1 rounded-xl bg-primary-500 px-4 py-3 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-50"
            :disabled="loading || tablePendingClose.currentOrder?.status !== 'paid'"
            @click="closeTable"
          >
            {{
              loading
                ? 'Menutup...' : 'Tutup Meja'
            }}
          </button>
        </div>
      </section>
    </div>
  </div>
</template>
