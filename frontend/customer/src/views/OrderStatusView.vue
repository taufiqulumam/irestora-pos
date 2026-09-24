<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, h } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useTableStore } from '@/stores/table';
import { useOrderStore } from '@/stores/order';
import { useCartStore } from '@/stores/cart';
import { calculateOrderTotals } from '@shared/calculator';
import { formatCurrency, formatDate, formatTimeOnly } from '@shared/utils';
import type { Order, OrderBatch, OrderBatchStatus } from '@shared/types';

const route = useRoute();
const router = useRouter();
const tableStore = useTableStore();
const orderStore = useOrderStore();
const cartStore = useCartStore();

const tableId = ref(route.params['tableId'] as string);
const orderId = ref(route.params['orderId'] as string | undefined);

const loading = ref(false);
const pollingInterval = ref<number | null>(null);
const showAddMore = ref(false);
const showPaymentChoice = ref(false);
const waitingCashierPayment = ref(false);

const order = computed(() => orderStore.currentOrder);
const batches = computed(() => orderStore.orderBatches);
const paymentStorageKey = computed(() => (order.value?.id ? `customer_payment_mode_${order.value.id}` : ''));

const orderStatusLabel = computed(() => {
  if (!order.value) return 'Memuat...';
  const labels: Record<string, string> = {
    open: 'Terbuka',
    paid: 'Lunas',
    void: 'Dibatalkan',
    cancelled: 'Dibatalkan',
  };
  return labels[order.value.status] || order.value.status;
});

const orderStatusColor = computed(() => {
  if (!order.value) return 'gray';
  const colors: Record<string, string> = {
    open: 'blue',
    paid: 'green',
    void: 'red',
    cancelled: 'red',
  };
  return colors[order.value.status] || 'gray';
});

const batchStatusLabels: Record<OrderBatchStatus, string> = {
  pending_confirmation: 'Menunggu diterima kasir',
  confirmed: 'Diterima',
  rejected: 'Ditolak',
};

const batchStatusColors: Record<OrderBatchStatus, string> = {
  pending_confirmation: 'yellow',
  confirmed: 'green',
  rejected: 'red',
};

const orderTotals = computed(() => {
  if (!order.value) {
    return {
      subtotal: 0,
      discountTotal: 0,
      serviceChargeTotal: 0,
      pb1Total: 0,
      roundingAdjustment: 0,
      grandTotal: 0,
    };
  }

  if ((Number(order.value.subtotal) || 0) > 0) {
    return {
      subtotal: Number(order.value.subtotal) || 0,
      discountTotal: Number(order.value.discount_total) || 0,
      serviceChargeTotal: Number(order.value.service_charge_total) || 0,
      pb1Total: Number(order.value.pb1_total) || 0,
      roundingAdjustment: Number(order.value.rounding_adjustment) || 0,
      grandTotal: Number(order.value.grand_total) || 0,
    };
  }

  const items = batches.value
    .flatMap(batch => batch.items || [])
    .filter(item => item.status === 'active');
  const outlet = (order.value as any).outlet || {};

  return calculateOrderTotals(
    items.map(item => ({
      priceSnapshot: Number(item.price_snapshot) || 0,
      qty: Number(item.qty) || 0,
    })),
    Number(order.value.discount_total) || 0,
    {
      serviceChargeRate: Number(outlet.service_charge_rate) || 5,
      pb1Rate: Number(outlet.pb1_rate) || 10,
      roundingEnabled: outlet.rounding_enabled ?? true,
    }
  );
});

async function initialize() {
  if (!tableId.value) return;

  await Promise.all([
    tableStore.fetchTable(tableId.value),
    cartStore.loadFromStorage(),
  ]);

  if (orderId.value) {
    await fetchOrderData();
  } else {
    await orderStore.fetchOrderStatus(tableId.value);
  }

  loadPaymentMode();

  startPolling();
}

async function fetchOrderData() {
  loading.value = true;
  try {
    if (orderId.value) {
      await orderStore.fetchOrderBatches(orderId.value);
    }
    await orderStore.fetchOrderStatus(tableId.value);
    loadPaymentMode();
  } catch (err) {
    console.error('Failed to fetch order:', err);
  } finally {
    loading.value = false;
  }
}

function loadPaymentMode() {
  if (!order.value?.id) return;

  if (order.value.status === 'paid') {
    localStorage.removeItem(`customer_payment_mode_${order.value.id}`);
    waitingCashierPayment.value = false;
    return;
  }

  waitingCashierPayment.value =
    localStorage.getItem(`customer_payment_mode_${order.value.id}`) === 'cashier';
}

function startPolling() {
  // Poll every 5 seconds for status updates
  pollingInterval.value = window.setInterval(async () => {
    if (!loading.value) {
      await fetchOrderData();
    }
  }, 5000);
}

function stopPolling() {
  if (pollingInterval.value) {
    clearInterval(pollingInterval.value);
    pollingInterval.value = null;
  }
}

function getBatchStatusColor(status: OrderBatchStatus): string {
  const colorMap: Record<OrderBatchStatus, string> = {
    pending_confirmation: 'bg-yellow-100 text-yellow-800 border-yellow-200',
    confirmed: 'bg-green-100 text-green-800 border-green-200',
    rejected: 'bg-red-100 text-red-800 border-red-200',
  };
  return colorMap[status] || 'bg-gray-100 text-gray-800 border-gray-200';
}

function getBatchStatusIcon(status: OrderBatchStatus) {
  if (status === 'confirmed') {
    return h('svg', { class: 'w-5 h-5', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
      h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M5 13l4 4L19 7' })
    ]);
  }
  if (status === 'rejected') {
    return h('svg', { class: 'w-5 h-5', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
      h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M6 18L18 6M6 6l12 12' })
    ]);
  }
  return h('svg', { class: 'w-5 h-5', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' })
  ]);
}

async function handleAddMore() {
  showAddMore.value = true;
  cartStore.loadFromStorage();
  router.push(`/${tableId.value}`);
}

async function handlePayNow() {
  if (!order.value?.id || order.value.status === 'paid') return;
  showPaymentChoice.value = true;
}

function chooseCashierPayment() {
  if (!order.value?.id) return;
  localStorage.setItem(`customer_payment_mode_${order.value.id}`, 'cashier');
  waitingCashierPayment.value = true;
  showPaymentChoice.value = false;
}

onMounted(() => {
  initialize();
});

onUnmounted(() => {
  stopPolling();
});
</script>

<template>
  <div class="min-h-screen flex flex-col bg-surface-50">
    <!-- Header -->
    <header class="bg-white border-b border-surface-200 px-4 py-3 sticky top-0 z-10">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <button @click="router.push(`/${tableId}`)" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
            <svg class="w-5 h-5 text-surface-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </button>
          <div>
            <h1 class="font-bold text-surface-900">Status Pesanan</h1>
            <p class="text-xs text-surface-500">{{ tableStore.table?.name }}</p>
          </div>
        </div>
      </div>
    </header>

    <main class="flex-1 overflow-y-auto p-4">
      <div v-if="loading && !order" class="flex items-center justify-center h-64">
        <svg class="animate-spin h-8 w-8 text-primary-500" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
      </div>

      <div v-else-if="!order" class="flex flex-col items-center justify-center h-64">
        <svg class="w-16 h-16 mb-4 text-surface-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-lg text-surface-600">Belum ada pesanan aktif</p>
        <p class="text-sm text-surface-400">Pesanan akan muncul di sini setelah dikirim</p>
        <button @click="router.push(`/${tableId}`)" class="mt-4 px-6 py-2 bg-primary-500 text-white rounded-xl font-medium hover:bg-primary-600 transition-colors">
          Buat Pesanan Baru
        </button>
      </div>

      <div v-else class="space-y-4">
        <!-- Order Header Card -->
        <div class="bg-white rounded-xl border border-surface-200 p-4 shadow-sm">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h2 class="font-bold text-surface-900">Pesanan #{{ order?.order_number || '—' }}</h2>
              <p class="text-sm text-surface-500 mt-1">{{ formatDate(order?.created_at_client || '') }} • {{ formatTimeOnly(order?.created_at_client || '') }}</p>
            </div>
            <span 
              :class="[
                'px-3 py-1 rounded-full text-xs font-semibold border',
                getBatchStatusColor('confirmed') // Use order status color
              ].join(' ')"
            >
              {{ orderStatusLabel }}
            </span>
          </div>

          <div class="flex items-center justify-between text-sm text-surface-600">
            <span>Total: <span class="font-bold text-primary-600 ml-1">{{ formatCurrency(orderTotals.grandTotal) }}</span></span>
            <span v-if="order?.source === 'customer_self_order'">Self Order</span>
          </div>
        </div>

        <div
          v-if="order?.status === 'paid'"
          class="rounded-xl border border-green-200 bg-green-50 p-4 shadow-sm"
        >
          <div class="flex gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-700">
              <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <div>
              <h3 class="font-bold text-green-800">Pembayaran diterima</h3>
              <p class="mt-1 text-sm leading-6 text-green-700">Terima kasih. Pembayaran pesanan Anda sudah diterima.</p>
            </div>
          </div>
        </div>

        <div
          v-else-if="waitingCashierPayment"
          class="rounded-xl border border-primary-200 bg-primary-50 p-4 shadow-sm"
        >
          <div class="flex gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary-700">
              <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a5 5 0 00-10 0v2M5 9h14l1 11H4L5 9z" />
              </svg>
            </div>
            <div>
              <h3 class="font-bold text-primary-800">Menunggu pembayaran di kasir</h3>
              <p class="mt-1 text-sm leading-6 text-primary-700">
                Tunjukkan nomor pesanan <strong>#{{ order?.order_number }}</strong> ke kasir untuk menyelesaikan pembayaran.
              </p>
              <p class="mt-2 text-sm font-semibold text-primary-800">Total: {{ formatCurrency(orderTotals.grandTotal) }}</p>
            </div>
          </div>
        </div>

        <!-- Batches / Rounds -->
        <div class="space-y-4">
          <h3 class="font-semibold text-surface-900">Riwayat Pesanan ({{ batches.length }} Batch)</h3>
          
          <div v-for="batch in batches" :key="batch.id" class="bg-white rounded-xl border border-surface-200 p-4 shadow-sm">
            <div class="flex items-start justify-between mb-3">
              <div>
                <h4 class="font-medium text-surface-900">Batch ke-{{ batch.batch_number }}</h4>
                <p class="text-xs text-surface-500 mt-0.5">Dikirim: {{ formatDate(batch.submitted_at) }} • {{ formatTimeOnly(batch.submitted_at) }}</p>
              </div>
              <span :class="getBatchStatusColor(batch.status)">
                <component :is="getBatchStatusIcon(batch.status)" class="mr-1 inline" />
                {{ batchStatusLabels[batch.status] }}
              </span>
            </div>

            <div class="space-y-2">
              <template v-for="item in batch.items" :key="item.id">
                <div class="flex justify-between text-sm py-1 border-b border-surface-100 last:border-0">
                  <div>
                    <span class="font-medium text-surface-900">{{ item.menu_name_snapshot }}</span>
                    <span class="text-surface-500 ml-2">{{ item.qty }} × {{ formatCurrency(item.price_snapshot) }}</span>
                  </div>
                  <span class="font-semibold text-surface-900">{{ formatCurrency(item.price_snapshot * item.qty) }}</span>
                </div>
                <div v-if="item.notes" class="text-xs text-surface-500 ml-6 pb-1">{{ item.notes }}</div>
              </template>
            </div>

            <div class="mt-3 pt-3 border-t border-surface-200 flex justify-between text-sm font-medium">
              <span>Subtotal Batch</span>
              <span>{{ formatCurrency(batch.items?.reduce((sum, i) => sum + i.price_snapshot * i.qty, 0) || 0) }}</span>
            </div>
          </div>

          <div v-if="batches.length === 0" class="text-center py-8 text-surface-400">
            Belum ada Batch pesanan
          </div>
        </div>

        <!-- Order Total Summary -->
        <div v-if="order" class="bg-white rounded-xl border border-surface-200 p-4 shadow-sm">
          <h3 class="font-semibold text-surface-900 mb-3">Ringkasan Total</h3>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between text-surface-600">
              <span>Subtotal</span>
              <span class="font-medium">{{ formatCurrency(orderTotals.subtotal) }}</span>
            </div>
            <div v-if="orderTotals.discountTotal > 0" class="flex justify-between text-red-600">
              <span>Diskon</span>
              <span class="font-medium">-{{ formatCurrency(orderTotals.discountTotal) }}</span>
            </div>
            <div class="flex justify-between text-surface-600">
              <span>Service (5%)</span>
              <span class="font-medium">{{ formatCurrency(orderTotals.serviceChargeTotal) }}</span>
            </div>
            <div class="flex justify-between text-surface-600">
              <span>PB1 (10%)</span>
              <span class="font-medium">{{ formatCurrency(orderTotals.pb1Total) }}</span>
            </div>
            <div v-if="orderTotals.roundingAdjustment !== 0" class="flex justify-between text-surface-600">
              <span>Pembulatan</span>
              <span class="font-medium">{{ orderTotals.roundingAdjustment > 0 ? '+' : '' }}{{ formatCurrency(orderTotals.roundingAdjustment) }}</span>
            </div>
            <div class="border-t border-surface-200 pt-2 flex justify-between text-lg font-bold text-surface-900">
              <span>Grand Total</span>
              <span class="text-primary-600">{{ formatCurrency(orderTotals.grandTotal) }}</span>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="space-y-3 pt-4">
          <button
            @click="handleAddMore"
            class="w-full py-3 border border-surface-300 text-surface-700 rounded-xl font-medium hover:bg-surface-50 transition-colors flex items-center justify-center gap-2"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Tambah Pesanan
          </button>

          <button
            v-if="order?.status === 'open'"
            @click="handlePayNow"
            class="w-full py-3.5 bg-primary-500 text-white font-semibold rounded-xl hover:bg-primary-600 transition-colors"
          >
            {{ waitingCashierPayment ? 'Ubah Metode Pembayaran' : 'Bayar Sekarang' }}
          </button>
        </div>
      </div>
    </main>

    <div
      v-if="showPaymentChoice"
      class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 px-4"
      @click.self="showPaymentChoice = false"
    >
      <section class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
        <div class="mb-4 flex items-start justify-between gap-4">
          <div>
            <h2 class="text-xl font-bold text-surface-900">Pilih Metode Pembayaran</h2>
            <p class="mt-1 text-sm text-surface-500">Total tagihan {{ formatCurrency(orderTotals.grandTotal) }}</p>
          </div>
          <button @click="showPaymentChoice = false" class="rounded-lg p-2 text-surface-400 hover:bg-surface-100">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="space-y-3">
          <button
            @click="chooseCashierPayment"
            class="w-full rounded-xl border border-primary-200 bg-primary-50 p-4 text-left transition-colors hover:bg-primary-100"
          >
            <p class="font-bold text-primary-800">Bayar di Kasir</p>
            <p class="mt-1 text-sm leading-6 text-primary-700">Datang ke kasir dan tunjukkan nomor pesanan untuk pembayaran.</p>
          </button>

          <button
            disabled
            class="w-full rounded-xl border border-surface-200 bg-surface-50 p-4 text-left opacity-60"
          >
            <div class="flex items-center justify-between gap-3">
              <div>
                <p class="font-bold text-surface-700">QRIS</p>
                <p class="mt-1 text-sm leading-6 text-surface-500">Pembayaran QRIS segera hadir.</p>
              </div>
              <span class="rounded-full bg-surface-200 px-3 py-1 text-xs font-semibold text-surface-600">Segera hadir</span>
            </div>
          </button>
        </div>
      </section>
    </div>

    <!-- Refresh indicator -->
    <div class="fixed bottom-4 right-4 z-50">
      <div class="bg-surface-900 text-white text-xs px-3 py-2 rounded-lg shadow-lg flex items-center gap-1.5">
        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
        Auto-refresh aktif
      </div>
    </div>
  </div>
</template>
