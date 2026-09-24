<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useCalculator } from '@/composables/useCalculator';
import { useOrder } from '@/composables/useOrder';
import { formatCurrency } from '@shared/utils';
import { PAYMENT_METHOD_LABELS } from '@shared/constants';
import type { PaymentMethod } from '@shared/types';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const { totals, grandTotal, discountTotal, subtotal, items, clearCalculator } = useCalculator();
const { addPayment, loading: paymentLoading, error: paymentError } = useOrder();

const orderId = ref(route.params['orderId'] as string);
const selectedMethod = ref<PaymentMethod>('cash');
const receivedAmount = ref(0);
const changeAmount = computed(() => Math.max(0, receivedAmount.value - grandTotal.value));
const quickAmounts = computed(() => [
  grandTotal.value,
  Math.ceil(grandTotal.value / 50000) * 50000,
  Math.ceil(grandTotal.value / 100000) * 100000,
  Math.ceil(grandTotal.value / 200000) * 200000,
]);

const canProceed = computed(() => {
  if (selectedMethod.value === 'cash') {
    return receivedAmount.value >= grandTotal.value;
  }
  return true;
});

async function initialize() {
  if (!authStore.isAuthenticated) {
    router.push('/login');
    return;
  }
}

async function handlePayment() {
  if (!canProceed.value) return;

  try {
    const amount = selectedMethod.value === 'cash' ? receivedAmount.value : grandTotal.value;
    await addPayment(orderId.value, selectedMethod.value, amount);
    localStorage.setItem(
      `receipt_${orderId.value}`,
      JSON.stringify({
        subtotal: subtotal.value,
        discount_total: discountTotal.value,
        service_charge_total: totals.value.serviceChargeTotal,
        pb1_total: totals.value.pb1Total,
        rounding_adjustment: totals.value.roundingAdjustment,
        grand_total: grandTotal.value,
        items: items.value,
        payments: [
          {
            method: selectedMethod.value,
            amount,
            reference_number: null,
          },
        ],
      })
    );
    clearCalculator();
    sessionStorage.removeItem('active_takeaway_order_id');
    router.push('/receipt/' + orderId.value);
  } catch (err) {
    // Error handled in composable
  }
}

function setQuickAmount(amount: number) {
  receivedAmount.value = amount;
}
</script>

<template>
  <div class="h-screen flex flex-col bg-surface-900">
    <!-- Header -->
    <header class="bg-surface-800 border-b border-surface-700 px-4 py-3 sticky top-0 z-10">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <button
            @click="router.back()"
            class="p-2 rounded-lg hover:bg-surface-700 transition-colors"
          >
            <svg
              class="w-5 h-5 text-surface-300"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M15 19l-7-7 7-7"
              />
            </svg>
          </button>
          <div>
            <h1 class="font-bold text-white">Proses Pembayaran</h1>
            <p class="text-xs text-surface-400">Meja {{ orderId }}</p>
          </div>
        </div>
        <button class="text-red-400 hover:text-red-300 text-sm font-medium">Batal</button>
      </div>
    </header>

    <!-- Bill Summary -->
    <div class="px-4 py-4 bg-surface-800/50 border-b border-surface-700">
      <div class="max-w-md mx-auto space-y-2 text-sm">
        <div class="flex justify-between text-surface-300">
          <span>Subtotal</span>
          <span class="font-medium text-white">{{ formatCurrency(subtotal) }}</span>
        </div>
        <div v-if="discountTotal > 0" class="flex justify-between text-red-400">
          <span>Diskon</span>
          <span class="font-medium">-{{ formatCurrency(discountTotal) }}</span>
        </div>
        <div class="flex justify-between text-surface-300">
          <span>Service (5%)</span>
          <span class="font-medium text-white">{{
            formatCurrency(totals.serviceChargeTotal)
          }}</span>
        </div>
        <div class="flex justify-between text-surface-300">
          <span>PB1 (10%)</span>
          <span class="font-medium text-white">{{ formatCurrency(totals.pb1Total) }}</span>
        </div>
        <div v-if="totals.roundingAdjustment !== 0" class="flex justify-between text-surface-300">
          <span>Pembulatan</span>
          <span class="font-medium"
            >{{ totals.roundingAdjustment > 0 ? '+' : ''
            }}{{ formatCurrency(totals.roundingAdjustment) }}</span
          >
        </div>
        <div
          class="border-t border-surface-700 pt-2 flex justify-between text-xl font-bold text-white"
        >
          <span>Total Akhir</span>
          <span class="text-green-400">{{ formatCurrency(grandTotal) }}</span>
        </div>
      </div>
    </div>

    <div class="flex-1 overflow-y-auto p-4">
      <div class="max-w-md mx-auto space-y-6">
        <!-- Payment Method -->
        <div>
          <h2 class="text-sm font-semibold text-surface-400 uppercase tracking-wider mb-3">
            Pilih Metode Pembayaran
          </h2>
          <div class="grid grid-cols-3 gap-3">
            <button
              v-for="method in ['cash', 'qris', 'card'] as PaymentMethod[]"
              :key="method"
              @click="selectedMethod = method"
              :class="[
                'flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-all',
                selectedMethod === method
                  ? 'border-green-500 bg-green-500/10'
                  : 'border-surface-700 hover:border-surface-600',
              ]"
            >
              <div
                :class="selectedMethod === method ? 'text-green-500' : 'text-surface-500'"
                class="w-10 h-10 rounded-xl flex items-center justify-center"
              >
                <svg
                  v-if="method === 'cash'"
                  class="w-6 h-6"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                  />
                </svg>
                <svg
                  v-if="method === 'qris'"
                  class="w-6 h-6"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                  />
                </svg>
                <svg
                  v-if="method === 'card'"
                  class="w-6 h-6"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                  />
                </svg>
              </div>
              <span
                :class="
                  selectedMethod === method ? 'text-green-500 font-semibold' : 'text-surface-300'
                "
                class="text-sm"
              >
                {{ PAYMENT_METHOD_LABELS[method] }}
              </span>
            </button>
          </div>
        </div>

        <!-- Cash Input -->
        <div v-if="selectedMethod === 'cash'" class="space-y-4">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-semibold text-surface-300 mb-1">Uang Diterima</label>
              <div class="relative">
                <span
                  class="absolute left-3 top-1/2 -translate-y-1/2 text-surface-400 text-xl font-bold"
                  >Rp</span
                >
                <input
                  v-model.number="receivedAmount"
                  type="number"
                  :min="grandTotal"
                  step="1000"
                  @keydown.enter="handlePayment"
                  class="w-full pl-10 pr-4 py-3 bg-surface-800 border border-surface-600 rounded-lg text-2xl font-bold text-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                  placeholder="150.000"
                  inputmode="numeric"
                />
              </div>
            </div>
            <div>
              <label class="block text-sm font-semibold text-surface-300 mb-1">Kembalian</label>
              <div
                class="w-full py-3 bg-green-500/10 border border-green-500/30 rounded-lg flex items-center justify-center"
              >
                <span class="text-2xl font-bold text-green-400"
                  >{{ formatCurrency(changeAmount) }}</span
                >
              </div>
            </div>
          </div>

          <div class="space-y-2">
            <label class="text-xs font-semibold text-surface-500 uppercase tracking-wider"
              >Uang Pas / Nominal Cepat</label
            >
            <div class="grid grid-cols-4 gap-2">
              <button
                v-for="amount in quickAmounts"
                :key="amount"
                @click="setQuickAmount(amount)"
                class="py-2.5 bg-surface-800 border border-surface-600 rounded-lg text-white font-medium hover:bg-surface-700 transition-colors"
              >
                {{ formatCurrency(amount) }}
              </button>
            </div>
          </div>
        </div>

        <!-- Non-Cash Info -->
        <div v-else class="space-y-4">
          <div class="p-4 bg-surface-800 border border-surface-700 rounded-xl text-center">
            <svg
              class="w-12 h-12 mx-auto mb-2 text-surface-500"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <svg
                v-if="selectedMethod === 'qris'"
                class="w-12 h-12 mx-auto mb-2 text-surface-500"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="1.5"
                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
              <svg
                v-if="selectedMethod === 'card'"
                class="w-12 h-12 mx-auto mb-2 text-surface-500"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="1.5"
                  d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                />
              </svg>
            </svg>
            <p class="text-white font-medium">Silakan scan QRIS / tap kartu</p>
            <p class="text-surface-400 text-sm mt-1">Nominal: {{ formatCurrency(grandTotal) }}</p>
          </div>
        </div>

        <!-- Process Button -->
        <button
          @click="handlePayment"
          :disabled="!canProceed || paymentLoading"
          class="w-full py-4 bg-green-500 text-white font-bold text-lg rounded-xl hover:bg-green-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          <span v-if="paymentLoading" class="flex items-center justify-center gap-2">
            <svg class="animate-spin h-6 w-6" viewBox="0 0 24 24">
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
            Memproses...
          </span>
          <span v-else>Proses Pembayaran & Cetak (Enter)</span>
        </button>
      </div>
    </div>
  </div>
</template>
