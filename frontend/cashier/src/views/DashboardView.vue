<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useTablesStore } from '@/stores/tables';
import { useShiftStore } from '@/stores/shift';
import { useSyncStore } from '@/stores/sync';
import { formatCurrency } from '@shared/utils';

const router = useRouter();
const authStore = useAuthStore();
const tablesStore = useTablesStore();
const shiftStore = useShiftStore();
const syncStore = useSyncStore();

const userName = computed(() => authStore.user?.full_name?.split(' ')[0] || 'Kasir');
const outletName = computed(() => authStore.user?.outlet?.name || 'Outlet utama');
const activeShift = computed(() => shiftStore.currentShift);
const tableStats = computed(() => [
  { label: 'Total Meja', value: tablesStore.counts.total, color: 'text-surface-900', icon: 'table' },
  { label: 'Tersedia', value: tablesStore.counts.available, color: 'text-primary-600', icon: 'check' },
  { label: 'Terisi', value: tablesStore.counts.occupied, color: 'text-amber-600', icon: 'cart' },
  { label: 'Reservasi', value: tablesStore.counts.reserved, color: 'text-sky-600', icon: 'clock' },
]);

async function initialize() {
  if (authStore.outletId) {
    await Promise.all([
      tablesStore.fetchTables(authStore.outletId, true),
      shiftStore.fetchActiveShift(authStore.outletId),
    ]);
  }
}

onMounted(initialize);
</script>

<template>
  <div class="min-h-screen bg-surface-50">
    <header class="border-b border-surface-200 bg-white px-5 py-4 md:px-8">
      <div class="flex items-center justify-between gap-4">
        <div>
          <p class="text-sm text-surface-500">{{ outletName }}</p>
          <h1 class="mt-1 text-2xl font-bold tracking-tight text-surface-900">Dashboard Kasir</h1>
        </div>
        <div class="flex items-center gap-2 rounded-full bg-primary-50 px-3 py-2 text-xs font-semibold text-primary-700">
          <span class="h-2 w-2 rounded-full bg-primary-500"></span>
          {{ syncStore.statusText }}
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-7xl space-y-6 p-5 md:p-8">
      <section class="rounded-2xl bg-surface-900 p-6 text-white shadow-lg md:p-8">
        <div class="flex flex-col justify-between gap-6 md:flex-row md:items-end">
          <div>
            <p class="text-sm text-surface-400">Selamat datang kembali</p>
            <h2 class="mt-2 text-3xl font-bold">Halo, {{ userName }}.</h2>
            <p class="mt-2 max-w-lg text-sm leading-6 text-surface-300">Pantau kondisi outlet dan lanjutkan pekerjaan kasir Anda dari satu tempat.</p>
          </div>
          <button @click="router.push('/order?type=takeaway')" class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-500 px-5 py-3 text-sm font-bold text-white transition-colors hover:bg-primary-400">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h2l2 12h10l3-8H6m3 13h.01M17 21h.01" /></svg>
            Ambil Order Baru
          </button>
        </div>
      </section>

      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div v-for="stat in tableStats" :key="stat.label" class="rounded-2xl border border-surface-200 bg-white p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-surface-500">{{ stat.label }}</p>
            <div class="rounded-lg bg-surface-100 p-2" :class="stat.color">
              <svg v-if="stat.icon === 'table'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5h16M4 12h16M4 19h16M5 5v14M19 5v14" /></svg>
              <svg v-else-if="stat.icon === 'check'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12l4 4L19 6" /></svg>
              <svg v-else-if="stat.icon === 'cart'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h2l2 12h10l3-8H6m3 13h.01M17 21h.01" /></svg>
              <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
          </div>
          <p class="mt-5 text-3xl font-bold" :class="stat.color">{{ stat.value }}</p>
        </div>
      </section>

      <section class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <div class="rounded-2xl border border-surface-200 bg-white p-6 shadow-sm">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-lg font-bold text-surface-900">Akses Cepat</h2>
              <p class="mt-1 text-sm text-surface-500">Menu yang paling sering digunakan.</p>
            </div>
            <svg class="h-6 w-6 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
          </div>
          <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <button @click="router.push('/tables')" class="flex items-center gap-3 rounded-xl border border-surface-200 p-4 text-left transition-colors hover:border-primary-300 hover:bg-primary-50">
              <span class="rounded-lg bg-primary-100 p-2 text-primary-700"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5h16M4 12h16M4 19h16M5 5v14M19 5v14" /></svg></span>
              <span><strong class="block text-sm text-surface-900">Peta Meja</strong><small class="text-xs text-surface-500">Lihat status meja</small></span>
            </button>
            <button @click="router.push('/order?type=takeaway')" class="flex items-center gap-3 rounded-xl border border-surface-200 p-4 text-left transition-colors hover:border-primary-300 hover:bg-primary-50">
              <span class="rounded-lg bg-primary-100 p-2 text-primary-700"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h2l2 12h10l3-8H6" /></svg></span>
              <span><strong class="block text-sm text-surface-900">Order Takeaway</strong><small class="text-xs text-surface-500">Buat transaksi baru</small></span>
            </button>
          </div>
        </div>

        <div class="rounded-2xl border border-surface-200 bg-white p-6 shadow-sm">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-lg font-bold text-surface-900">Shift Aktif</h2>
              <p class="mt-1 text-sm text-surface-500">Status operasional saat ini.</p>
            </div>
            <span class="rounded-full bg-primary-50 px-3 py-1 text-xs font-bold text-primary-700">Aktif</span>
          </div>
          <div class="mt-6 space-y-4">
            <div class="flex justify-between border-b border-surface-100 pb-3 text-sm"><span class="text-surface-500">Kas awal</span><strong class="text-surface-900">{{ formatCurrency(activeShift?.opening_cash || 0) }}</strong></div>
            <div class="flex justify-between border-b border-surface-100 pb-3 text-sm"><span class="text-surface-500">Status sinkronisasi</span><strong class="text-primary-600">{{ syncStore.statusText }}</strong></div>
            <button @click="router.push('/tables')" class="w-full rounded-xl bg-surface-900 py-3 text-sm font-bold text-white transition-colors hover:bg-surface-800">Buka Peta Meja</button>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>
