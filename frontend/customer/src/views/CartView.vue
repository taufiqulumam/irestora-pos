<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useTableStore } from '@/stores/table';
import { useCartStore } from '@/stores/cart';
import { useOrderStore } from '@/stores/order';
import { formatCurrency } from '@shared/utils';
import type { OrderBatchStatus } from '@shared/types';

const route = useRoute();
const router = useRouter();
const tableStore = useTableStore();
const cartStore = useCartStore();
const orderStore = useOrderStore();

const tableId = ref(route.params['tableId'] as string);
const showNotesModal = ref<{ localId: string; currentNotes: string } | null>(null);
const submitting = ref(false);

const existingBatches = computed(() => orderStore.orderBatches);
const hasDraftItems = computed(() => cartStore.items.length > 0);
const hasExistingOrder = computed(() => existingBatches.value.length > 0);

const batchStatusLabels: Record<OrderBatchStatus, string> = {
  pending_confirmation: 'Menunggu diterima kasir',
  confirmed: 'Diterima',
  rejected: 'Ditolak',
};

function getBatchStatusClass(status: OrderBatchStatus): string {
  const classes: Record<OrderBatchStatus, string> = {
    pending_confirmation: 'bg-amber-50 text-amber-700 border-amber-200',
    confirmed: 'bg-green-50 text-green-700 border-green-200',
    rejected: 'bg-red-50 text-red-700 border-red-200',
  };
  return classes[status] || 'bg-surface-50 text-surface-600 border-surface-200';
}

function getBatchSubtotal(batch: any): number {
  return (batch.items || []).reduce(
    (sum: number, item: any) => sum + (Number(item.price_snapshot) || 0) * (Number(item.qty) || 0),
    0
  );
}

async function initialize() {
  if (!tableId.value) return;
  
  await Promise.all([
    tableStore.fetchTable(tableId.value),
    orderStore.fetchOrderStatus(tableId.value),
    cartStore.loadFromStorage(),
  ]);
}

async function handleUpdateQty(localId: string, qty: number) {
  cartStore.updateQty(localId, qty);
}

async function handleRemoveItem(localId: string) {
  cartStore.removeItem(localId);
}

async function handleOpenNotes(localId: string, currentNotes: string | null) {
  showNotesModal.value = { localId, currentNotes: currentNotes || '' };
}

async function handleSaveNotes() {
  if (showNotesModal.value) {
    cartStore.updateNotes(showNotesModal.value.localId, showNotesModal.value.currentNotes);
    showNotesModal.value = null;
  }
}

async function handleSubmitOrder() {
  if (cartStore.items.length === 0) return;
  
  submitting.value = true;
  
  try {
    const batch = await orderStore.submitCart(tableId.value, cartStore.getItemsForSubmit());
    cartStore.clearCart();
    router.push(`/${tableId.value}/order/${batch.order_id || ''}`);
  } catch (err) {
    // Error handled in store
  } finally {
    submitting.value = false;
  }
}
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
            <h1 class="font-bold text-surface-900">Keranjang Pesanan</h1>
            <p class="text-xs text-surface-500">{{ tableStore.table?.name }}</p>
          </div>
        </div>
      </div>
    </header>

    <main class="flex-1 overflow-y-auto p-4">
      <div v-if="!hasDraftItems && !hasExistingOrder" class="flex flex-col items-center justify-center h-64">
        <svg class="w-16 h-16 mb-4 text-surface-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
        </svg>
        <p class="text-lg text-surface-600">Keranjang kosong</p>
        <p class="text-sm text-surface-400">Tambahkan menu untuk memulai pesanan</p>
        <button @click="router.push(`/${tableId}`)" class="mt-4 px-6 py-2 bg-primary-500 text-white rounded-xl font-medium hover:bg-primary-600 transition-colors">
          Kembali ke Menu
        </button>
      </div>

      <div v-else class="space-y-4">
        <section v-if="hasExistingOrder" class="space-y-3">
          <div class="flex items-center justify-between">
            <h2 class="font-semibold text-surface-900">Pesanan Sudah Dikirim</h2>
            <button
              @click="router.push(`/${tableId}/order/${orderStore.currentOrder?.id || ''}`)"
              class="text-sm font-medium text-primary-600"
            >
              Lihat Status
            </button>
          </div>

          <div
            v-for="batch in existingBatches"
            :key="batch.id"
            class="rounded-xl border border-surface-200 bg-white p-3 shadow-sm"
          >
            <div class="mb-3 flex items-start justify-between gap-3">
              <div>
                <p class="font-semibold text-surface-900">Batch ke-{{ batch.batch_number }}</p>
                <p class="mt-0.5 text-xs text-surface-500">Pesanan yang sudah dikirim</p>
              </div>
              <span
                class="rounded-full border px-3 py-1 text-xs font-semibold"
                :class="getBatchStatusClass(batch.status)"
              >
                {{ batchStatusLabels[batch.status] }}
              </span>
            </div>

            <div class="divide-y divide-surface-100">
              <div
                v-for="item in batch.items || []"
                :key="item.id"
                class="flex justify-between gap-3 py-2 text-sm"
                :class="item.status === 'voided' ? 'opacity-50' : ''"
              >
                <div class="min-w-0">
                  <p class="font-medium text-surface-900">{{ item.menu_name_snapshot }}</p>
                  <p class="text-surface-500">{{ formatCurrency(item.price_snapshot) }} x {{ item.qty }}</p>
                  <p v-if="item.notes" class="mt-1 text-xs text-surface-500">{{ item.notes }}</p>
                </div>
                <span class="font-semibold text-surface-900">
                  {{ formatCurrency(item.price_snapshot * item.qty) }}
                </span>
              </div>
            </div>

            <div class="mt-3 flex justify-between border-t border-surface-100 pt-3 text-sm font-semibold">
              <span>Subtotal Batch</span>
              <span>{{ formatCurrency(getBatchSubtotal(batch)) }}</span>
            </div>
          </div>
        </section>

        <section class="space-y-3">
          <div class="flex items-center justify-between">
            <h2 class="font-semibold text-surface-900">Pesanan Baru</h2>
            <button
              @click="router.push(`/${tableId}`)"
              class="text-sm font-medium text-primary-600"
            >
              Tambah Menu
            </button>
          </div>

          <div v-if="!hasDraftItems" class="rounded-xl border border-dashed border-surface-200 bg-white p-6 text-center">
            <p class="text-sm text-surface-500">Belum ada menu baru di keranjang.</p>
          </div>

        <div v-for="item in cartStore.items" :key="item.localId" class="bg-white rounded-xl border border-surface-200 p-3 shadow-sm">
          <div class="flex gap-3">
            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-2">
                <h4 class="font-medium text-surface-900 truncate">{{ item.menu_name_snapshot }}</h4>
                <button
                  @click="handleRemoveItem(item.localId)"
                  class="p-1 text-surface-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
              <p class="text-sm text-surface-500 mt-0.5">{{ formatCurrency(item.price_snapshot) }} × {{ item.qty }}</p>
              <p v-if="item.notes" class="text-xs text-surface-500 mt-1 bg-surface-50 px-2 py-1 rounded">{{ item.notes }}</p>
            </div>

            <div class="flex items-center gap-2">
              <button
                @click="handleUpdateQty(item.localId, item.qty - 1)"
                :disabled="item.qty <= 1"
                class="w-9 h-9 rounded-lg bg-surface-100 text-surface-600 hover:bg-surface-200 disabled:opacity-50 flex items-center justify-center transition-colors"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                </svg>
              </button>
              <span class="w-10 text-center font-semibold text-surface-900">{{ item.qty }}</span>
              <button
                @click="handleUpdateQty(item.localId, item.qty + 1)"
                class="w-9 h-9 rounded-lg bg-surface-100 text-surface-600 hover:bg-surface-200 flex items-center justify-center transition-colors"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
              </button>
            </div>
          </div>

          <div class="mt-2 flex gap-2">
            <button
              @click="handleOpenNotes(item.localId, item.notes)"
              class="flex-1 py-1.5 text-xs text-surface-600 hover:text-primary-600 font-medium"
            >
              <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
              Catatan
            </button>
          </div>
        </div>

        </section>

        <!-- Order Summary -->
        <div v-if="hasDraftItems" class="bg-white rounded-xl border border-surface-200 p-4 shadow-sm sticky bottom-0">
          <div class="space-y-2 text-sm mb-4">
            <div class="flex justify-between text-surface-600">
              <span>Subtotal</span>
              <span class="font-medium">{{ formatCurrency(cartStore.subtotal) }}</span>
            </div>
            <div v-if="cartStore.discountTotal > 0" class="flex justify-between text-red-600">
              <span>Diskon</span>
              <span class="font-medium">-{{ formatCurrency(cartStore.discountTotal) }}</span>
            </div>
            <div class="flex justify-between text-surface-600">
              <span>Service (5%)</span>
              <span class="font-medium">{{ formatCurrency(cartStore.totals.serviceChargeTotal) }}</span>
            </div>
            <div class="flex justify-between text-surface-600">
              <span>PB1 (10%)</span>
              <span class="font-medium">{{ formatCurrency(cartStore.totals.pb1Total) }}</span>
            </div>
            <div v-if="cartStore.totals.roundingAdjustment !== 0" class="flex justify-between text-surface-600">
              <span>Pembulatan</span>
              <span class="font-medium">{{ cartStore.totals.roundingAdjustment > 0 ? '+' : '' }}{{ formatCurrency(cartStore.totals.roundingAdjustment) }}</span>
            </div>
            <div class="border-t border-surface-200 pt-2 flex justify-between text-lg font-bold text-surface-900">
              <span>Total</span>
              <span class="text-primary-600">{{ formatCurrency(cartStore.grandTotal) }}</span>
            </div>
          </div>

          <button
            @click="handleSubmitOrder"
            :disabled="submitting"
            class="w-full py-3.5 bg-primary-500 text-white font-semibold rounded-xl hover:bg-primary-600 disabled:opacity-50 transition-colors"
          >
            <span v-if="submitting" class="flex items-center justify-center gap-2">
              <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
              </svg>
              Mengirim Pesanan...
            </span>
            <span v-else>Kirim Pesanan ({{ cartStore.itemCount }} item)</span>
          </button>
        </div>
      </div>
    </main>

    <!-- Notes Modal -->
    <div v-if="showNotesModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
      <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
        <h2 class="text-xl font-bold text-surface-900 mb-4">Catatan Item</h2>
        <textarea
          v-model="showNotesModal.currentNotes"
          rows="4"
          class="w-full px-4 py-3 bg-surface-50 border border-surface-200 rounded-lg text-surface-900 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
          placeholder="Tambahkan catatan (misal: pedas sedang, kurang manis, dll)"
        ></textarea>
        <div class="flex gap-2 mt-4">
          <button
            @click="showNotesModal = null"
            class="flex-1 py-2.5 border border-surface-300 text-surface-700 rounded-lg font-medium hover:bg-surface-50 transition-colors"
          >
            Batal
          </button>
          <button
            @click="handleSaveNotes"
            class="flex-1 py-2.5 bg-primary-500 text-white rounded-lg font-medium hover:bg-primary-600 transition-colors"
          >
            Simpan
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
