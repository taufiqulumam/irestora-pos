<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useTableStore } from '@/stores/table';
import { useMenuStore } from '@/stores/menu';
import { useCartStore } from '@/stores/cart';
import { formatCurrency } from '@shared/utils';
import type { Menu, Category } from '@shared/types';

const route = useRoute();
const router = useRouter();
const tableStore = useTableStore();
const menuStore = useMenuStore();
const cartStore = useCartStore();

const tableId = ref(route.params['tableId'] as string);
const activeCategory = ref<string | null>(null);
const searchQuery = ref('');
const showCart = ref(false);
const pageError = ref<string | null>(null);

const filteredMenus = computed(() => {
  let menus = menuStore.menus;
  if (activeCategory.value) {
    menus = menus.filter(m => m.category_id === activeCategory.value);
  }
  if (searchQuery.value) {
    menus = menuStore.searchMenus(searchQuery.value);
  }
  return menus;
});

const categories = computed(() => menuStore.activeCategories);

const cartItemCount = computed(() => cartStore.itemCount);

async function initialize() {
  if (!tableId.value) {
    pageError.value = 'ID meja tidak ditemukan. Silakan scan ulang QR meja.';
    return;
  }

  pageError.value = null;

  try {
    const table = await tableStore.fetchTable(tableId.value);
    if (!table?.outlet_id) {
      throw new Error('Outlet meja tidak ditemukan.');
    }

    await menuStore.fetchMenus(table.outlet_id, true);
    cartStore.loadFromStorage();
  } catch (error: any) {
    pageError.value = error.response?.data?.message || error.message || 'Gagal memuat menu customer.';
  }
}

async function handleAddItem(menu: Menu) {
  cartStore.addItem(menu, 1, '');
  showCart.value = true;
}

function formatCategoryName(category: Category): string {
  return category.name;
}

onMounted(initialize);

watch(
  () => route.params['tableId'],
  value => {
    tableId.value = value as string;
    initialize();
  }
);
</script>

<template>
  <div class="min-h-screen flex flex-col bg-surface-50">
    <!-- Header -->
    <header class="bg-white border-b border-surface-200 px-4 py-3 sticky top-0 z-10">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div v-if="tableStore.table" class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-primary-500 flex items-center justify-center">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </div>
            <div>
              <h1 class="font-bold text-surface-900">{{ tableStore.table.name }}</h1>
              <p class="text-xs text-surface-500">{{ tableStore.table.outlet?.name }}</p>
            </div>
          </div>
        </div>
        
        <button
          @click="router.push(`/${tableId}/cart`)"
          :class="cartItemCount > 0 ? 'bg-primary-500 text-white' : 'bg-surface-100 text-surface-600'"
          class="relative px-4 py-2 rounded-xl font-medium transition-colors flex items-center gap-2"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
          </svg>
          <span>Keranjang</span>
          <span v-if="cartItemCount > 0" class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center">
            {{ cartItemCount > 9 ? '9+' : cartItemCount }}
          </span>
        </button>
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
              : 'text-surface-600 hover:bg-surface-100'
          ]"
          role="tab"
        >
          {{ formatCategoryName(category) }}
        </button>
      </div>
    </div>

    <!-- Menu Grid -->
    <main class="flex-1 overflow-y-auto p-4">
      <div v-if="pageError" class="flex items-center justify-center h-64 px-4">
        <div class="max-w-sm rounded-2xl border border-red-200 bg-red-50 p-4 text-center">
          <p class="font-semibold text-red-700">Menu belum dapat dimuat</p>
          <p class="mt-2 text-sm leading-6 text-red-600">{{ pageError }}</p>
          <button
            @click="initialize"
            class="mt-4 rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white"
          >
            Coba Lagi
          </button>
        </div>
      </div>

      <div v-else-if="(tableStore.loading || menuStore.loading) && menuStore.menus.length === 0" class="flex items-center justify-center h-64">
        <svg class="animate-spin h-8 w-8 text-primary-500" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
      </div>

      <div v-else-if="filteredMenus.length === 0" class="flex items-center justify-center h-64">
        <div class="text-center">
          <svg class="mx-auto h-10 w-10 text-surface-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p class="mt-2 text-surface-500">Tidak ada menu ditemukan</p>
        </div>
      </div>

      <div v-else class="grid grid-cols-2 gap-3">
        <button
          v-for="menu in filteredMenus"
          :key="menu.id"
          @click="handleAddItem(menu)"
          class="group p-3 bg-white border border-surface-200 rounded-xl hover:border-primary-200 hover:bg-primary-50 hover:shadow-sm transition-all text-left"
        >
          <div class="aspect-square w-full bg-surface-100 rounded-lg mb-2 overflow-hidden relative">
            <img 
              v-if="menu.image_url" 
              :src="menu.image_url" 
              :alt="menu.name"
              class="w-full h-full object-cover"
            />
            <div v-else class="w-full h-full flex items-center justify-center text-surface-300">
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
          </div>
          <h4 class="font-medium text-surface-900 text-sm line-clamp-1">{{ menu.name }}</h4>
          <p class="text-primary-600 font-semibold text-sm mt-0.5">{{ formatCurrency(menu.price || 0) }}</p>
        </button>
      </div>
    </main>

    <!-- Cart Sidebar -->
    <div 
      v-if="showCart" 
      class="fixed inset-0 z-50"
      @click.self="showCart = false"
    >
      <div class="absolute inset-0 bg-black/50" @click="showCart = false"></div>
      <div class="absolute right-0 top-0 bottom-0 w-full max-w-md bg-white shadow-xl flex flex-col z-10">
        <div class="p-4 border-b border-surface-200 flex items-center justify-between">
          <h2 class="font-bold text-surface-900">Keranjang ({{ cartItemCount }})</h2>
          <button @click="showCart = false" class="p-2 text-surface-400 hover:text-surface-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="flex-1 overflow-y-auto p-4" v-if="cartStore.items.length > 0">
          <div class="space-y-3">
            <div v-for="item in cartStore.items" :key="item.localId" class="bg-surface-50 rounded-xl p-3">
              <div class="flex gap-3">
                <div class="flex-1 min-w-0">
                  <h4 class="font-medium text-surface-900">{{ item.menu_name_snapshot }}</h4>
                  <p class="text-sm text-surface-500">{{ formatCurrency(item.price_snapshot) }} × {{ item.qty }}</p>
                </div>
                <div class="flex items-center gap-2">
                  <button
                    @click="cartStore.updateQty(item.localId, item.qty - 1)"
                    :disabled="item.qty <= 1"
                    class="w-8 h-8 rounded-lg bg-surface-100 text-surface-600 disabled:opacity-50"
                  >
                    <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                    </svg>
                  </button>
                  <span class="w-8 text-center font-semibold">{{ item.qty }}</span>
                  <button
                    @click="cartStore.updateQty(item.localId, item.qty + 1)"
                    class="w-8 h-8 rounded-lg bg-surface-100 text-surface-600"
                  >
                    <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="flex-1 flex items-center justify-center p-8">
          <div class="text-center">
            <p class="text-surface-400">Belum ada menu baru</p>
            <p class="mt-1 text-xs text-surface-400">Buka keranjang untuk melihat pesanan yang sudah dikirim</p>
          </div>
        </div>

        <div class="p-4 border-t border-surface-200 bg-white">
          <div v-if="cartStore.items.length > 0" class="space-y-2 text-sm mb-4">
            <div class="flex justify-between">
              <span class="text-surface-600">Subtotal</span>
              <span class="font-medium">{{ formatCurrency(cartStore.subtotal) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-surface-600">Total</span>
              <span class="font-bold text-primary-600">{{ formatCurrency(cartStore.grandTotal) }}</span>
            </div>
          </div>
          <button
            @click="() => { showCart = false; router.push(`/${tableId}/cart`); }"
            class="w-full py-3 bg-primary-500 text-white font-semibold rounded-xl hover:bg-primary-600 transition-colors"
          >
            {{ cartStore.items.length > 0 ? 'Lihat & Pesan' : 'Lihat Keranjang' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
