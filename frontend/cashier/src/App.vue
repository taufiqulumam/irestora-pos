<script setup lang="ts">
import { onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useReverb } from '@/composables/useReverb';
import { useSyncStore } from '@/stores/sync';
import router from '@/router';
import { RouterView } from 'vue-router';
import { computed } from 'vue';

const authStore = useAuthStore();
const { initialize: initReverb } = useReverb();
const syncStore = useSyncStore();
const showShell = computed(
  () =>
    authStore.isAuthenticated &&
    router.currentRoute.value.name !== 'Login' &&
    !router.currentRoute.value.path.startsWith('/receipt/')
);

const navigation = [
  { name: 'Dashboard', path: '/dashboard', icon: 'grid' },
  { name: 'Peta Meja', path: '/tables', icon: 'table' },
  { name: 'Ambil Order', path: '/order?type=takeaway', icon: 'cart' },
  { name: 'Riwayat Transaksi', path: '/history', icon: 'history' },
  { name: 'Tutup Shift', path: '/shift/close', icon: 'lock' },
];

function logout() {
  authStore.logout();
  router.push('/login');
}

onMounted(async () => {
  const restored = await authStore.initializeAuth();
  if (restored) {
    initReverb();
    syncStore.initializeOnlineStatus();
  }
});
</script>

<template>
  <div id="app" class="min-h-screen bg-surface-50">
    <div v-if="showShell" class="min-h-screen md:flex">
      <aside class="w-full shrink-0 bg-surface-900 text-white md:min-h-screen md:w-56 lg:w-64">
        <div class="flex items-center gap-3 border-b border-white/10 px-5 py-5">
          <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-500">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 3l8 4v5c0 4.5-3.2 7.8-8 9-4.8-1.2-8-4.5-8-9V7l8-4zM9 12l2 2 4-4"
              />
            </svg>
          </div>
          <div>
            <p class="text-lg font-bold leading-tight">iRestora</p>
            <p class="text-[10px] font-semibold uppercase tracking-widest text-primary-400">
              POS System
            </p>
          </div>
        </div>

        <nav
          class="flex gap-1 overflow-x-auto p-3 md:block md:space-y-1 md:overflow-visible"
          aria-label="Navigasi utama"
        >
          <RouterLink
            v-for="item in navigation"
            :key="item.name"
            :to="item.path"
            class="flex min-w-max items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-surface-300 transition-colors hover:bg-white/10 hover:text-white"
            active-class="!bg-primary-500 !text-white shadow-lg shadow-primary-500/20"
          >
            <svg
              v-if="item.icon === 'grid'"
              class="h-5 w-5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"
              />
            </svg>
            <svg
              v-else-if="item.icon === 'table'"
              class="h-5 w-5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M4 5h16M4 12h16M4 19h16M5 5v14M19 5v14"
              />
            </svg>
            <svg
              v-else-if="item.icon === 'cart'"
              class="h-5 w-5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M3 4h2l2 12h10l3-8H6m3 13h.01M17 21h.01"
              />
            </svg>
            <svg
              v-else-if="item.icon === 'history'"
              class="h-5 w-5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M4 12a8 8 0 108-8 8 8 0 00-6 2.7M4 4v5h5m3-1v4l3 2"
              />
            </svg>
            <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M7 10V7a5 5 0 0110 0v3m-12 0h14v10H5V10z"
              />
            </svg>
            <span>{{ item.name }}</span>
          </RouterLink>
        </nav>

        <div
          class="hidden border-t border-white/10 p-4 md:fixed md:bottom-0 md:block md:w-56 lg:w-64"
        >
          <button
            @click="logout"
            class="flex w-full items-center gap-3 rounded-xl p-2 text-left hover:bg-white/10"
          >
            <div
              class="flex h-9 w-9 items-center justify-center rounded-full bg-surface-700 text-xs font-bold"
            >
              {{ authStore.user?.full_name?.slice(0, 2).toUpperCase() || 'KS' }}
            </div>
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold">
                {{ authStore.user?.full_name || 'Kasir' }}
              </p>
              <p class="truncate text-xs text-surface-400">
                {{ authStore.user?.role?.name || 'Kasir Utama' }}
              </p>
            </div>
          </button>
        </div>
      </aside>

      <main class="min-w-0 flex-1">
        <RouterView v-slot="{ Component }">
          <transition name="fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </RouterView>
      </main>
    </div>

    <RouterView v-else v-slot="{ Component }">
      <transition name="fade" mode="out-in">
        <component :is="Component" />
      </transition>
    </RouterView>
  </div>
</template>

<style>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
