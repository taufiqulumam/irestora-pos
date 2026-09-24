<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useShiftStore } from '@/stores/shift';
import { formatCurrency, formatDate } from '@shared/utils';

const router = useRouter();
const authStore = useAuthStore();
const shiftStore = useShiftStore();

const openingCash = ref(0);
const loading = ref(false);
const error = ref<string | null>(null);

const formattedOpeningCash = computed(() => formatCurrency(openingCash.value));
const outletName = computed(() => authStore.user?.outlet?.name || 'Outlet belum terhubung');
const cashierName = computed(() => authStore.user?.full_name || 'Kasir belum terhubung');
const canSubmit = computed(() => openingCash.value > 0 && !!authStore.outletId && !loading.value);

async function checkActiveShift() {
  if (authStore.outletId) {
    const activeShift = await shiftStore.fetchActiveShift(authStore.outletId);
    if (activeShift) {
      router.push('/tables');
    }
  }
}

async function handleOpenShift() {
  if (!authStore.outletId) {
    error.value = 'Outlet kasir belum valid. Silakan login ulang atau hubungi admin untuk memastikan akun kasir memiliki outlet.';
    return;
  }

  if (openingCash.value <= 0) {
    error.value = 'Modal awal kas wajib diisi lebih dari 0.';
    return;
  }

  if (!canSubmit.value) return;
  
  loading.value = true;
  error.value = null;
  
  try {
    await shiftStore.openShift(authStore.outletId, openingCash.value);
    router.push('/tables');
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Gagal membuka shift';
  } finally {
    loading.value = false;
  }
}

function handleLogout() {
  authStore.logout();
  router.push('/login');
}

onMounted(() => {
  checkActiveShift();
});
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-surface-50 px-4">
    <button
      @click="handleLogout"
      class="absolute right-4 top-4 flex items-center gap-2 rounded-lg border border-surface-200 bg-white px-3 py-2 text-sm font-medium text-surface-600 shadow-sm transition-colors hover:bg-surface-50 hover:text-red-600"
    >
      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H3m0 0l4-4m-4 4l4 4M9 4h8a2 2 0 012 2v12a2 2 0 01-2 2H9" />
      </svg>
      Logout
    </button>
    <div class="w-full max-w-md">
      <div class="bg-white rounded-2xl shadow-xl p-8">
        <div class="text-center mb-8">
          <div class="inline-flex items-center justify-center w-16 h-16 rounded-xl bg-primary-500 mb-4">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h1 class="text-2xl font-bold text-surface-900">Buka Shift Kasir</h1>
          <p class="text-surface-500 mt-1">Persiapkan modal awal untuk transaksi harian Anda</p>
        </div>

        <div class="mb-6 p-4 bg-surface-100 rounded-xl space-y-3">
          <div class="flex justify-between">
            <span class="text-surface-500 text-sm">Nama Outlet</span>
            <span class="font-semibold text-surface-900 text-right">{{ outletName }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-surface-500 text-sm">Nama Kasir</span>
            <span class="font-semibold text-surface-900 text-right">{{ cashierName }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-surface-500 text-sm">Waktu Buka</span>
            <span class="font-semibold text-surface-900">{{ formatDate(new Date()) }}</span>
          </div>
        </div>

        <div v-if="error" class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
          {{ error }}
        </div>

        <div class="space-y-4">
          <label class="block text-sm font-semibold text-surface-700">Modal Awal Kas (IDR)</label>
          
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-primary-600 font-bold text-2xl">
              Rp
            </div>
            <input
              type="number"
              v-model.number="openingCash"
              min="0"
              step="1000"
              @keydown.enter="handleOpenShift"
              class="w-full pl-12 pr-4 py-4 bg-white border-2 border-surface-200 rounded-xl text-2xl font-semibold text-surface-900 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"
              placeholder="500.000"
              :disabled="loading"
              inputmode="numeric"
            />
          </div>
          
          <p class="text-xs text-surface-500 text-center">*Pastikan jumlah uang di laci kasir sesuai dengan modal awal.</p>
        </div>

        <button
          @click="handleOpenShift"
          :disabled="loading"
          class="w-full mt-6 py-3.5 bg-primary-500 text-white font-semibold rounded-xl hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
        >
          <span v-if="loading" class="flex items-center justify-center gap-2">
            <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            Membuka Shift...
          </span>
          <span v-else>Buka Shift Sekarang</span>
        </button>
      </div>
    </div>
  </div>
</template>
