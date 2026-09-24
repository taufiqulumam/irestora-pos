<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useShiftStore } from '@/stores/shift';
import { formatCurrency, formatDate } from '@shared/utils';

const router = useRouter();
const authStore = useAuthStore();
const shiftStore = useShiftStore();

const closingCash = ref<number | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
const completed = ref(false);

const shift = computed(() => shiftStore.currentShift);
const expectedCash = computed(() => shift.value?.closing_cash_expected || shiftStore.openingCash);
const difference = computed(() => (closingCash.value ?? 0) - expectedCash.value);
const canSubmit = computed(() => closingCash.value !== null && closingCash.value >= 0 && !loading.value && !!shift.value);

async function initialize() {
  if (!authStore.isAuthenticated) {
    router.push('/login');
    return;
  }

  if (authStore.outletId) {
    const activeShift = await shiftStore.fetchActiveShift(authStore.outletId);
    if (!activeShift) {
      error.value = 'Tidak ada shift aktif yang dapat ditutup.';
      return;
    }
  }
}

async function handleCloseShift() {
  if (!canSubmit.value || !shift.value || closingCash.value === null) return;

  loading.value = true;
  error.value = null;

  try {
    await shiftStore.closeShift(shift.value.id, closingCash.value);
    completed.value = true;
    shiftStore.clearShift();
  } catch (err: any) {
    error.value = err.response?.data?.message || shiftStore.error || 'Gagal menutup shift';
  } finally {
    loading.value = false;
  }
}

function returnToDashboard() {
  router.push('/dashboard');
}

onMounted(initialize);
</script>

<template>
  <div class="min-h-screen bg-surface-50">
    <header class="border-b border-surface-200 bg-white px-5 py-4 md:px-8">
      <div class="mx-auto flex max-w-4xl items-center justify-between gap-4">
        <div>
          <p class="text-sm text-surface-500">{{ authStore.user?.outlet?.name || 'Outlet utama' }}</p>
          <h1 class="mt-1 text-2xl font-bold text-surface-900">Tutup Shift</h1>
        </div>
        <button @click="router.push('/dashboard')" class="rounded-lg px-3 py-2 text-sm font-semibold text-surface-500 hover:bg-surface-100 hover:text-surface-900">Kembali</button>
      </div>
    </header>

    <main class="mx-auto max-w-4xl space-y-6 p-5 md:p-8">
      <div v-if="completed" class="rounded-2xl border border-primary-200 bg-primary-50 p-8 text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-primary-500 text-white">
          <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
        </div>
        <h2 class="mt-4 text-2xl font-bold text-primary-900">Shift berhasil ditutup</h2>
        <p class="mt-2 text-sm text-primary-700">Rekap kas telah disimpan dan selisih sudah dicatat oleh sistem.</p>
        <button @click="returnToDashboard" class="mt-6 rounded-xl bg-primary-500 px-5 py-3 text-sm font-bold text-white hover:bg-primary-600">Kembali ke Dashboard</button>
      </div>

      <template v-else>
        <section class="rounded-2xl bg-surface-900 p-6 text-white shadow-lg md:p-8">
          <p class="text-sm text-surface-400">Rekonsiliasi kas harian</p>
          <h2 class="mt-2 text-2xl font-bold">Pastikan kas fisik sesuai sebelum menutup shift.</h2>
          <p class="mt-2 text-sm leading-6 text-surface-300">Sistem akan menghitung selisih antara kas seharusnya dan jumlah uang di laci.</p>
        </section>

        <section v-if="error" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ error }}</section>

        <section v-if="shift" class="grid gap-4 md:grid-cols-3">
          <div class="rounded-2xl border border-surface-200 bg-white p-5 shadow-sm"><p class="text-sm text-surface-500">Modal awal</p><p class="mt-3 text-xl font-bold text-surface-900">{{ formatCurrency(shift.opening_cash) }}</p><p class="mt-1 text-xs text-surface-400">Dibuka {{ formatDate(shift.opened_at) }}</p></div>
          <div class="rounded-2xl border border-surface-200 bg-white p-5 shadow-sm"><p class="text-sm text-surface-500">Kas seharusnya</p><p class="mt-3 text-xl font-bold text-primary-600">{{ formatCurrency(expectedCash) }}</p><p class="mt-1 text-xs text-surface-400">Modal awal + pembayaran tunai</p></div>
          <div class="rounded-2xl border border-surface-200 bg-white p-5 shadow-sm"><p class="text-sm text-surface-500">Preview selisih</p><p class="mt-3 text-xl font-bold" :class="difference === 0 ? 'text-primary-600' : difference > 0 ? 'text-sky-600' : 'text-red-600'">{{ formatCurrency(difference) }}</p><p class="mt-1 text-xs text-surface-400">Dihitung dari kas fisik</p></div>
        </section>

        <section v-if="shift" class="rounded-2xl border border-surface-200 bg-white p-6 shadow-sm md:p-8">
          <label for="closing-cash" class="block text-sm font-semibold text-surface-700">Kas fisik akhir (IDR)</label>
          <p class="mt-1 text-sm text-surface-500">Hitung seluruh uang tunai di laci kasir, lalu masukkan jumlahnya.</p>
          <div class="relative mt-5 max-w-xl">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-2xl font-bold text-primary-600">Rp</span>
            <input id="closing-cash" v-model.number="closingCash" type="number" min="0" step="1000" inputmode="numeric" placeholder="0" :disabled="loading" class="w-full rounded-xl border-2 border-surface-200 py-4 pl-12 pr-4 text-2xl font-bold text-surface-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" />
          </div>
          <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-surface-500">Shift akan ditutup dan tidak dapat dibuka kembali.</p>
            <button @click="handleCloseShift" :disabled="!canSubmit" class="rounded-xl bg-primary-500 px-6 py-3.5 text-sm font-bold text-white hover:bg-primary-600 disabled:cursor-not-allowed disabled:opacity-50">
              <span v-if="loading">Menyimpan...</span>
              <span v-else>Tutup Shift Sekarang</span>
            </button>
          </div>
        </section>
      </template>
    </main>
  </div>
</template>
