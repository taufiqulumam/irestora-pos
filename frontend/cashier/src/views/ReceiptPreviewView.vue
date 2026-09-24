<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import axios from 'axios';
import { formatCurrency, formatDate, getInitials } from '@shared/utils';
import type { Order } from '@shared/types';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const orderId = ref(route.params['orderId'] as string);
const order = ref<Order | null>(null);
const loading = ref(true);
const loadError = ref<string | null>(null);
const printed = ref(false);

const orderNumber = computed(() => order.value?.order_number || '—');
const orderDate = computed(() =>
  order.value?.created_at_client ? formatDate(order.value.created_at_client) : '—'
);
const cashierName = computed(
  () => order.value?.cashier?.full_name || authStore.user?.full_name || '—'
);
const tableName = computed(
  () => order.value?.table?.name || (order.value?.order_type === 'takeaway' ? 'Takeaway' : '—')
);

const subtotal = computed(() => order.value?.subtotal || 0);
const discountTotal = computed(() => order.value?.discount_total || 0);
const serviceChargeTotal = computed(() => order.value?.service_charge_total || 0);
const pb1Total = computed(() => order.value?.pb1_total || 0);
const roundingAdjustment = computed(() => order.value?.rounding_adjustment || 0);
const grandTotal = computed(() => order.value?.grand_total || 0);

const paymentMethod = computed(() => {
  const payment = order.value?.payments?.[0];
  return payment?.method ? payment.method : 'cash';
});

const paymentMethodLabel = computed(() => {
  const labels: Record<string, string> = {
    cash: 'Tunai (Cash)',
    qris: 'QRIS',
    card: 'Kartu Debit/Kredit',
    other: 'Lainnya',
  };
  return labels[paymentMethod.value] || 'Tunai (Cash)';
});

const receivedAmount = computed(() => {
  const payment = order.value?.payments?.[0];
  return payment?.amount || grandTotal.value;
});

const changeAmount = computed(() => {
  if (paymentMethod.value !== 'cash') return 0;
  return Math.max(0, receivedAmount.value - grandTotal.value);
});

async function fetchOrder() {
  try {
    const response = await axios.get(`/api/orders/${orderId.value}`);
    const serverOrder = response.data.data as Order;
    const snapshot = localStorage.getItem(`receipt_${orderId.value}`);
    const localOrder = snapshot ? JSON.parse(snapshot) : null;
    const totalFields = [
      'subtotal',
      'discount_total',
      'service_charge_total',
      'pb1_total',
      'rounding_adjustment',
      'grand_total',
    ] as const;
    const receiptOrder = { ...serverOrder } as Order;

    for (const field of totalFields) {
      if (localOrder?.[field] > 0 && Number(serverOrder[field]) === 0) {
        receiptOrder[field] = localOrder[field];
      }
    }

    order.value = {
      ...localOrder,
      ...receiptOrder,
      items: serverOrder.items?.length ? serverOrder.items : localOrder?.items,
      payments: serverOrder.payments?.length ? serverOrder.payments : localOrder?.payments,
    };
    localStorage.removeItem(`receipt_${orderId.value}`);
  } catch (error: any) {
    loadError.value = error.response?.data?.message || 'Gagal memuat data struk';
  } finally {
    loading.value = false;
  }
}

async function handlePrint() {
  printed.value = true;
  window.print();

  // Navigate back after print
  setTimeout(() => {
    router.push('/tables');
  }, 1000);
}

function handleBackToTables() {
  router.push('/tables');
}

onMounted(() => {
  fetchOrder();
});
</script>

<template>
  <div class="receipt-page min-h-screen bg-surface-50 flex flex-col">
    <!-- Header -->
    <header class="no-print bg-white border-b border-surface-200 px-4 py-3 sticky top-0 z-10">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <h1 class="font-bold text-surface-900">Struk Preview</h1>
        </div>
        <div class="flex items-center gap-2">
          <span class="px-2 py-1 bg-green-50 text-green-700 text-xs font-medium rounded-full">
            Tersinkronisasi
          </span>
        </div>
      </div>
    </header>

    <main class="receipt-content flex-1 overflow-y-auto p-4 md:p-8">
      <div v-if="loading" class="flex items-center justify-center h-64">
        <svg class="animate-spin h-8 w-8 text-primary-500" viewBox="0 0 24 24">
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
      </div>

      <div v-else-if="loadError" class="max-w-md mx-auto py-16 text-center">
        <p class="text-red-600 font-medium">{{ loadError }}</p>
        <button
          @click="fetchOrder"
          class="mt-4 px-4 py-2 bg-primary-500 text-white rounded-lg font-medium hover:bg-primary-600"
        >
          Coba Lagi
        </button>
      </div>

      <div v-else class="max-w-3xl mx-auto">
        <!-- Receipt Preview -->
        <div
          class="receipt-paper bg-white shadow-lg rounded-lg overflow-hidden print-only:shadow-none print-only:rounded-none"
        >
          <div class="receipt-inner p-6 md:p-8">
            <!-- Header -->
            <div class="receipt-store text-center mb-6 border-b border-surface-200 pb-6">
              <h2 class="text-2xl font-bold text-surface-900">iRestora Café</h2>
              <p class="text-surface-500 text-sm mt-1">Jl. Asia Afrika No.10, Bandung</p>
              <p class="text-surface-500 text-xs mt-0.5">Telp: 022-4213324</p>
            </div>

            <!-- Order Info -->
            <div class="receipt-meta mb-6 border-b border-surface-200 pb-4">
              <div class="grid grid-cols-2 gap-2 text-sm">
                <div class="flex justify-between">
                  <span class="text-surface-500">No. Transaksi:</span>
                  <span class="font-semibold text-surface-900">{{ orderNumber }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-surface-500">Tanggal:</span>
                  <span class="font-semibold text-surface-900">{{ orderDate }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-surface-500">Kasir / Meja:</span>
                  <span class="font-semibold text-surface-900"
                    >{{ cashierName }} / {{ tableName }}</span
                  >
                </div>
              </div>
            </div>

            <!-- Items -->
            <div class="receipt-items mb-6 border-b border-surface-200 pb-4">
              <div v-for="item in order?.items || []" :key="item.id" class="receipt-item mb-4">
                <div class="flex justify-between mb-1">
                  <span class="font-semibold text-surface-900">{{ item.menu_name_snapshot }}</span>
                  <span class="font-bold text-surface-900">{{
                    formatCurrency(item.price_snapshot * item.qty)
                  }}</span>
                </div>
                <div class="flex justify-between text-sm text-surface-500">
                  <span>{{ item.qty }} x {{ formatCurrency(item.price_snapshot) }}</span>
                  <span v-if="item.notes" class="text-right">{{ item.notes }}</span>
                </div>
              </div>
            </div>

            <!-- Totals -->
            <div class="receipt-totals mb-6 border-b border-surface-200 pb-4 space-y-2 text-sm">
              <div class="flex justify-between text-surface-500">
                <span>Subtotal:</span>
                <span class="font-medium text-surface-900">{{ formatCurrency(subtotal) }}</span>
              </div>
              <div v-if="discountTotal > 0" class="flex justify-between text-red-600">
                <span>Diskon:</span>
                <span class="font-medium">-{{ formatCurrency(discountTotal) }}</span>
              </div>
              <div class="flex justify-between text-surface-500">
                <span>Service (5%):</span>
                <span class="font-medium text-surface-900">{{
                  formatCurrency(serviceChargeTotal)
                }}</span>
              </div>
              <div class="flex justify-between text-surface-500">
                <span>PB1 (10%):</span>
                <span class="font-medium text-surface-900">{{ formatCurrency(pb1Total) }}</span>
              </div>
              <div v-if="roundingAdjustment !== 0" class="flex justify-between text-surface-500">
                <span>Pembulatan:</span>
                <span class="font-medium"
                  >{{ roundingAdjustment > 0 ? '+' : ''
                  }}{{ formatCurrency(roundingAdjustment) }}</span
                >
              </div>
              <div
                class="receipt-grand-total border-t border-surface-200 pt-2 flex justify-between text-lg font-bold text-surface-900"
              >
                <span>TOTAL AKHIR:</span>
                <span>{{ formatCurrency(grandTotal) }}</span>
              </div>
              <div class="flex justify-between text-surface-500 text-sm">
                <span>Metode:</span>
                <span class="font-semibold text-surface-900">{{ paymentMethodLabel }}</span>
              </div>
              <div
                v-if="paymentMethod === 'cash'"
                class="flex justify-between text-surface-500 text-sm"
              >
                <span>Uang Bayar:</span>
                <span class="font-semibold text-surface-900">{{
                  formatCurrency(receivedAmount)
                }}</span>
              </div>
              <div
                v-if="paymentMethod === 'cash'"
                class="flex justify-between text-green-600 text-sm"
              >
                <span>Kembalian:</span>
                <span class="font-bold">{{ formatCurrency(changeAmount) }}</span>
              </div>
            </div>

            <!-- Footer -->
            <div
              class="receipt-footer text-center text-surface-500 text-xs border-t border-surface-200 pt-4"
            >
              Terima kasih atas kunjungan Anda!<br />
              Powered by iRestora
            </div>
          </div>
        </div>

        <!-- Success Message -->
        <div class="no-print mt-6 p-4 bg-green-50 border border-green-200 rounded-xl">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-green-500 flex items-center justify-center">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M5 13l4 4L19 7"
                />
              </svg>
            </div>
            <div>
              <p class="font-semibold text-green-800">Transaksi Selesai & Berhasil!</p>
              <p class="text-green-700 text-sm">
                Data transaksi telah disimpan dan disinkronkan ke server pusat iRestora secara
                real-time.
              </p>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="no-print mt-6 space-y-3">
          <button
            @click="handlePrint"
            class="w-full py-3.5 bg-primary-500 text-white font-semibold rounded-xl hover:bg-primary-600 transition-colors flex items-center justify-center gap-2"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"
              />
            </svg>
            Cetak Struk (F12)
          </button>
          <button
            @click="handleBackToTables"
            class="w-full py-3.5 bg-white border border-surface-300 text-surface-700 font-semibold rounded-xl hover:bg-surface-50 transition-colors"
          >
            Kembali ke Peta Meja
          </button>
        </div>
      </div>
    </main>
  </div>
</template>
