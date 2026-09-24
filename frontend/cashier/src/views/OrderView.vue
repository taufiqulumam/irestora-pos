<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useMenuStore } from '@/stores/menu';
import { useTablesStore } from '@/stores/tables';
import { useShiftStore } from '@/stores/shift';
import { useCalculator } from '@/composables/useCalculator';
import { useOrder } from '@/composables/useOrder';
import { formatCurrency } from '@shared/utils';
import type { Menu, Category } from '@shared/types';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const menuStore = useMenuStore();
const tablesStore = useTablesStore();
const shiftStore = useShiftStore();
const {
  items,
  grandTotal,
  subtotal,
  discountTotal,
  totals,
  addMenuItem,
  updateItemQty,
  removeItem,
  updateItemNotes,
  setDiscount,
  applyDiscountPercent,
  clearCalculator,
  hydrateFromOrder,
  getCalculationForSync,
} = useCalculator();
const {
  openOrder,
  fetchOrder,
  submitOrder,
  currentOrder,
  loading: orderLoading,
  error: orderError,
} = useOrder();

const tableId = ref(route.params['tableId'] as string | undefined);
const orderType = ref((route.query['type'] as 'dine_in' | 'takeaway') || 'dine_in');
const existingOrderId = route.query['orderId'] as string | undefined;
const initialized = ref(false);
const activeCategory = ref<string | null>(null);
const searchQuery = ref('');
const showDiscountModal = ref(false);
const discountInput = ref(0);
const discountType = ref<'amount' | 'percent'>('amount');
const showNotesModal = ref<{ itemId: string; currentNotes: string } | null>(null);
const menuLoadError = ref<string | null>(null);

const effectiveOutletId = computed(
  () =>
    authStore.outletId ||
    authStore.user?.outlet?.id ||
    authStore.user?.outlet_id ||
    shiftStore.currentShift?.outlet_id ||
    null
);

const filteredMenus = computed(() => {
  let menus = menuStore.menus;
  if (activeCategory.value) {
    menus = menus.filter((m) => m.category_id === activeCategory.value);
  }
  if (searchQuery.value) {
    menus = menuStore.searchMenus(searchQuery.value);
  }
  return menus;
});

const categories = computed(() => menuStore.activeCategories);
const tableName = computed(() => {
  if (orderType.value !== 'dine_in') return 'Takeaway';

  return (
    currentOrder.value?.table?.name ||
    tablesStore.tables.find((table) => table.id === tableId.value)?.name ||
    'Meja'
  );
});

async function initialize() {
  if (initialized.value) return;
  initialized.value = true;

  if (!authStore.isAuthenticated) {
    router.push('/login');
    return;
  }

  menuLoadError.value = null;

  const outletId = effectiveOutletId.value;
  if (!outletId) {
    menuLoadError.value = 'Outlet kasir tidak ditemukan. Silakan logout lalu login ulang.';
    return;
  }

  await shiftStore.fetchActiveShift(outletId);
  if (!shiftStore.isShiftOpen) {
    router.push('/shift/open');
    return;
  }

  await Promise.all([
    menuStore.fetchMenus(effectiveOutletId.value || outletId, true),
    tablesStore.fetchTables(effectiveOutletId.value || outletId, true),
  ]);

  const storedTakeawayOrderId =
    orderType.value === 'takeaway'
      ? sessionStorage.getItem('active_takeaway_order_id') || undefined
      : undefined;
  const activeOrderId =
    existingOrderId ||
    storedTakeawayOrderId ||
    (tableId.value
      ? tablesStore.tables.find((table) => table.id === tableId.value)?.current_order_id ||
        undefined
      : undefined);

  if (activeOrderId) {
    try {
      const order = await fetchOrder(activeOrderId);
      hydrateFromOrder(order);
      if (order.status !== 'open' && orderType.value === 'takeaway') {
        sessionStorage.removeItem('active_takeaway_order_id');
        clearCalculator();
        const newOrder = await openOrder('takeaway');
        hydrateFromOrder(newOrder);
      }
    } catch {
      if (orderType.value === 'takeaway') sessionStorage.removeItem('active_takeaway_order_id');
      clearCalculator();
      const newOrder = await openOrder('takeaway');
      hydrateFromOrder(newOrder);
    }
  } else if (tableId.value && orderType.value === 'dine_in') {
    clearCalculator();
    const order = await openOrder('dine_in', tableId.value);
    hydrateFromOrder(order);
  } else if (orderType.value === 'takeaway') {
    clearCalculator();
    const order = await openOrder('takeaway');
    hydrateFromOrder(order);
  }

  if (orderType.value === 'takeaway' && currentOrder.value?.id) {
    sessionStorage.setItem('active_takeaway_order_id', currentOrder.value.id);
  }

  if (shiftStore.currentShift?.outlet) {
    // Set outlet config for calculator
  }
}

async function handleAddItem(menu: Menu) {
  addMenuItem(menu, 1, '');
}

async function handleItemQtyChange(itemId: string, qty: number) {
  updateItemQty(itemId, qty);
}

async function handleRemoveItem(itemId: string) {
  removeItem(itemId);
}

async function handleOpenNotes(itemId: string, currentNotes: string | null) {
  showNotesModal.value = { itemId, currentNotes: currentNotes || '' };
}

async function handleSaveNotes() {
  if (showNotesModal.value) {
    updateItemNotes(showNotesModal.value.itemId, showNotesModal.value.currentNotes);
    showNotesModal.value = null;
  }
}

async function handleApplyDiscount() {
  if (discountType.value === 'percent') {
    applyDiscountPercent(discountInput.value);
  } else {
    setDiscount(discountInput.value);
  }
  showDiscountModal.value = false;
  discountInput.value = 0;
}

async function handleProceedToPayment() {
  if (items.value.length === 0) return;

  try {
    const activeOrderId = currentOrder.value?.id;
    if (!activeOrderId) return;
    await submitOrder(activeOrderId);
    router.push('/payment/' + activeOrderId);
  } catch (err) {
    // Error handled in composable
  }
}

onMounted(initialize);

function handleLogout() {
  authStore.logout();
  clearCalculator();
  router.push('/login');
}

function formatCategoryName(category: Category): string {
  return category.name;
}
</script>

<template>
  <div class="h-screen flex flex-col bg-surface-50">
    <!-- Header -->
    <header class="bg-white border-b border-surface-200 px-4 py-3 sticky top-0 z-10">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <button
            @click="router.push('/tables')"
            class="p-2 rounded-lg hover:bg-surface-100 transition-colors"
          >
            <svg
              class="w-5 h-5 text-surface-600"
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
            <h1 class="font-bold text-surface-900">
              {{ tableName }}
            </h1>
            <p class="text-xs text-surface-500">
              {{ orderType === 'dine_in' ? 'Makan di Tempat' : 'Bawa Pulang' }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <span class="text-sm font-medium text-surface-700">{{ formatCurrency(grandTotal) }}</span>
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
      <div
        v-if="orderError"
        class="mx-4 mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
      >
        {{ orderError }}
      </div>
    </header>

    <!-- Category Tabs -->
    <div class="px-4 py-2 bg-white border-b border-surface-200 sticky top-14 z-10">
      <div class="flex gap-2 overflow-x-auto pb-2" role="tablist">
        <button
          v-for="category in categories"
          :key="category.id"
          @click="activeCategory = activeCategory === category.id ? null : category.id"
          :class="[
            'px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap transition-all',
            activeCategory === category.id
              ? 'bg-primary-500 text-white shadow-md'
              : 'text-surface-600 hover:bg-surface-100',
          ]"
          role="tab"
        >
          {{ formatCategoryName(category) }}
        </button>
      </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 flex overflow-hidden">
      <!-- Menu Panel -->
      <aside class="w-72 lg:w-80 flex flex-col border-r border-surface-200 bg-white">
        <div class="p-3 border-b border-surface-200">
          <input
            v-model="searchQuery"
            type="search"
            placeholder="Cari menu..."
            class="w-full px-3 py-2 bg-surface-100 border border-surface-200 rounded-lg text-sm text-surface-900 placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          />
        </div>

        <div class="flex-1 overflow-y-auto p-3">
          <div v-if="menuStore.loading" class="flex items-center justify-center h-32">
            <svg class="animate-spin h-6 w-6 text-primary-500" viewBox="0 0 24 24">
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

          <div v-else-if="filteredMenus.length === 0" class="text-center py-8">
            <svg
              class="mx-auto h-10 w-10 text-surface-300"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            <p class="mt-2 text-surface-500 text-sm">
              {{ menuLoadError || menuStore.error || 'Tidak ada menu ditemukan' }}
            </p>
          </div>

          <div v-else class="grid grid-cols-2 gap-2">
            <button
              v-for="menu in filteredMenus"
              :key="menu.id"
              @click="handleAddItem(menu)"
              class="group p-3 bg-surface-50 border border-surface-200 rounded-xl hover:border-primary-200 hover:bg-primary-50 hover:shadow-sm transition-all text-left"
            >
              <div
                class="aspect-square w-full bg-surface-100 rounded-lg mb-2 overflow-hidden relative"
              >
                <img
                  v-if="menu.image_url"
                  :src="menu.image_url"
                  :alt="menu.name"
                  class="w-full h-full object-cover"
                />
                <div v-else class="w-full h-full flex items-center justify-center text-surface-300">
                  <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                    />
                  </svg>
                </div>
              </div>
              <h4 class="font-medium text-surface-900 text-sm line-clamp-1">{{ menu.name }}</h4>
              <p class="text-primary-600 font-semibold text-sm mt-0.5">
                {{ formatCurrency(menu.price || 0) }}
              </p>
            </button>
          </div>
        </div>
      </aside>

      <!-- Cart Panel -->
      <div class="flex-1 flex flex-col min-w-0">
        <div class="flex-1 overflow-y-auto p-4">
          <div
            v-if="items.length === 0"
            class="flex flex-col items-center justify-center h-full text-surface-400"
          >
            <svg class="w-16 h-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="1.5"
                d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"
              />
            </svg>
            <p class="text-lg">Keranjang kosong</p>
            <p class="text-sm">Tambahkan menu dari daftar di sebelah kiri</p>
          </div>

          <div v-else class="space-y-3">
            <div
              v-for="item in items"
              :key="item.id"
              class="bg-white rounded-xl border border-surface-200 p-3 shadow-sm"
            >
              <div class="flex gap-3">
                <div class="flex-1 min-w-0">
                  <div class="flex items-start justify-between gap-2">
                    <h4 class="font-medium text-surface-900 truncate">
                      {{ item.menu_name_snapshot }}
                    </h4>
                    <button
                      @click="handleRemoveItem(item.id)"
                      class="p-1 text-surface-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M6 18L18 6M6 6l12 12"
                        />
                      </svg>
                    </button>
                  </div>
                  <p class="text-sm text-surface-500 mt-0.5">
                    {{ formatCurrency(item.price_snapshot) }} × {{ item.qty }}
                  </p>
                  <p
                    v-if="item.notes"
                    class="text-xs text-surface-500 mt-1 bg-surface-50 px-2 py-1 rounded"
                  >
                    {{ item.notes }}
                  </p>
                </div>

                <div class="flex items-center gap-2">
                  <button
                    @click="handleItemQtyChange(item.id, item.qty - 1)"
                    :disabled="item.qty <= 1"
                    class="w-9 h-9 rounded-lg bg-surface-100 text-surface-600 hover:bg-surface-200 disabled:opacity-50 flex items-center justify-center transition-colors"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M20 12H4"
                      />
                    </svg>
                  </button>
                  <span class="w-10 text-center font-semibold text-surface-900">{{
                    item.qty
                  }}</span>
                  <button
                    @click="handleItemQtyChange(item.id, item.qty + 1)"
                    class="w-9 h-9 rounded-lg bg-surface-100 text-surface-600 hover:bg-surface-200 flex items-center justify-center transition-colors"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 4v16m8-8H4"
                      />
                    </svg>
                  </button>
                </div>
              </div>

              <div class="mt-2 flex gap-2">
                <button
                  @click="handleOpenNotes(item.id, item.notes)"
                  class="flex-1 py-1.5 text-xs text-surface-600 hover:text-primary-600 font-medium"
                >
                  <svg
                    class="w-4 h-4 inline mr-1"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                    />
                  </svg>
                  Catatan
                </button>
              </div>
            </div>

            <!-- Discount -->
            <div
              class="bg-white rounded-xl border border-surface-200 p-3 shadow-sm flex items-center justify-between"
            >
              <div class="flex items-center gap-2">
                <svg
                  class="w-5 h-5 text-surface-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"
                  />
                </svg>
                <span class="font-medium text-surface-900">Diskon</span>
              </div>
              <button
                @click="showDiscountModal = true"
                class="text-primary-600 font-medium text-sm hover:text-primary-700"
              >
                {{ discountTotal > 0 ? formatCurrency(discountTotal) : 'Tambah' }}
              </button>
            </div>
          </div>
        </div>

        <!-- Order Summary -->
        <div class="border-t border-surface-200 bg-white p-4 sticky bottom-0">
          <div class="space-y-2 text-sm">
            <div class="flex justify-between text-surface-600">
              <span>Subtotal</span>
              <span class="font-medium">{{ formatCurrency(subtotal) }}</span>
            </div>
            <div v-if="discountTotal > 0" class="flex justify-between text-red-600">
              <span>Diskon</span>
              <span class="font-medium">-{{ formatCurrency(discountTotal) }}</span>
            </div>
            <div class="flex justify-between text-surface-600">
              <span>Service ({{ totals.serviceChargeTotal > 0 ? '5%' : '0%' }})</span>
              <span class="font-medium">{{ formatCurrency(totals.serviceChargeTotal) }}</span>
            </div>
            <div class="flex justify-between text-surface-600">
              <span>PB1 ({{ totals.pb1Total > 0 ? '10%' : '0%' }})</span>
              <span class="font-medium">{{ formatCurrency(totals.pb1Total) }}</span>
            </div>
            <div
              v-if="totals.roundingAdjustment !== 0"
              class="flex justify-between text-surface-600"
            >
              <span>Pembulatan</span>
              <span class="font-medium"
                >{{ totals.roundingAdjustment > 0 ? '+' : ''
                }}{{ formatCurrency(totals.roundingAdjustment) }}</span
              >
            </div>
            <div
              class="border-t border-surface-200 pt-2 flex justify-between text-lg font-bold text-surface-900"
            >
              <span>Total</span>
              <span class="text-primary-600">{{ formatCurrency(grandTotal) }}</span>
            </div>
          </div>

          <div class="mt-4 space-y-2">
            <div class="flex gap-2">
              <button
                @click="showDiscountModal = true"
                class="flex-1 py-2.5 border border-surface-300 text-surface-700 rounded-lg font-medium hover:bg-surface-50 transition-colors"
              >
                Diskon
              </button>
              <button
                @click="clearCalculator"
                class="flex-1 py-2.5 border border-red-300 text-red-600 rounded-lg font-medium hover:bg-red-50 transition-colors"
              >
                Void
              </button>
            </div>
            <button
              @click="handleProceedToPayment"
              :disabled="items.length === 0 || orderLoading"
              class="w-full py-3.5 bg-primary-500 text-white font-semibold rounded-xl hover:bg-primary-600 disabled:opacity-50 transition-colors"
            >
              <span v-if="orderLoading" class="flex items-center justify-center gap-2">
                <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
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
              <span v-else>Bayar Sekarang (F10)</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Discount Modal -->
    <div
      v-if="showDiscountModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
    >
      <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
        <h2 class="text-xl font-bold text-surface-900 mb-4">Tambah Diskon</h2>

        <div class="space-y-4">
          <div class="flex gap-2">
            <button
              @click="discountType = 'amount'"
              :class="[
                'flex-1 py-2.5 rounded-lg font-medium transition-colors',
                discountType === 'amount'
                  ? 'bg-primary-500 text-white'
                  : 'bg-surface-100 text-surface-700 hover:bg-surface-200',
              ]"
            >
              Nominal
            </button>
            <button
              @click="discountType = 'percent'"
              :class="[
                'flex-1 py-2.5 rounded-lg font-medium transition-colors',
                discountType === 'percent'
                  ? 'bg-primary-500 text-white'
                  : 'bg-surface-100 text-surface-700 hover:bg-surface-200',
              ]"
            >
              Persen (%)
            </button>
          </div>

          <div>
            <label class="block text-sm font-medium text-surface-700 mb-1">
              {{ discountType === 'amount' ? 'Jumlah Diskon (Rp)' : 'Persentase Diskon (%)' }}
            </label>
            <input
              v-model.number="discountInput"
              type="number"
              :min="0"
              :max="discountType === 'amount' ? subtotal : 100"
              :step="discountType === 'amount' ? 1000 : 1"
              @keydown.enter="handleApplyDiscount"
              class="w-full px-4 py-3 bg-surface-50 border border-surface-200 rounded-lg text-lg font-semibold text-surface-900 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              placeholder="0"
              inputmode="numeric"
            />
          </div>

          <div class="flex gap-2 pt-2">
            <button
              @click="
                showDiscountModal = false;
                discountInput = 0;
              "
              class="flex-1 py-2.5 border border-surface-300 text-surface-700 rounded-lg font-medium hover:bg-surface-50 transition-colors"
            >
              Batal
            </button>
            <button
              @click="handleApplyDiscount"
              class="flex-1 py-2.5 bg-primary-500 text-white rounded-lg font-medium hover:bg-primary-600 transition-colors"
            >
              Terapkan
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Notes Modal -->
    <div
      v-if="showNotesModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
    >
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
