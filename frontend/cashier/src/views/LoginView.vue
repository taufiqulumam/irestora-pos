<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { validateEmail } from '@shared/utils';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const email = ref('');
const password = ref('');
const loading = ref(false);
const error = ref<string | null>(null);

const isValidEmail = computed(() => validateEmail(email.value));
const canSubmit = computed(() => isValidEmail.value && password.value.length >= 8 && !loading.value);

async function handleLogin() {
  if (!canSubmit.value) return;
  
  loading.value = true;
  error.value = null;
  
  try {
    await authStore.login(email.value, password.value);
    const redirect = route.query['redirect'] as string || '/dashboard';
    router.push(redirect);
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Email atau password salah';
  } finally {
    loading.value = false;
  }
}

function handleKeyDown(e: KeyboardEvent) {
  if (e.key === 'Enter') handleLogin();
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-surface-50 px-4">
    <div class="w-full max-w-md">
      <div class="bg-white rounded-2xl shadow-xl p-8">
        <div class="text-center mb-8">
          <div class="inline-flex items-center justify-center w-16 h-16 rounded-xl bg-primary-500 mb-4">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
            </svg>
          </div>
          <h1 class="text-2xl font-bold text-surface-900">iRestora POS</h1>
          <p class="text-surface-500 mt-1">Point of Sale Kasir Terminal</p>
        </div>

        <div v-if="error" class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
          {{ error }}
        </div>

        <form @submit.prevent="handleLogin" class="space-y-5">
          <div>
            <label for="email" class="block text-sm font-medium text-surface-700 mb-1.5">E-mail Kasir</label>
            <input
              id="email"
              v-model="email"
              type="email"
              autocomplete="email"
              @keydown="handleKeyDown"
              class="w-full px-4 py-3 bg-surface-100 border border-surface-200 rounded-xl text-surface-900 placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
              placeholder="budi.santoso@irestora.com"
              :disabled="loading"
            />
          </div>

          <div>
            <label for="password" class="block text-sm font-medium text-surface-700 mb-1.5">Password</label>
            <input
              id="password"
              v-model="password"
              type="password"
              autocomplete="current-password"
              @keydown="handleKeyDown"
              class="w-full px-4 py-3 bg-surface-100 border border-surface-200 rounded-xl text-surface-900 placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
              placeholder="••••••••"
              :disabled="loading"
            />
          </div>

          <button
            type="submit"
            :disabled="!canSubmit"
            class="w-full py-3.5 bg-primary-500 text-white font-semibold rounded-xl hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
          >
            <span v-if="loading" class="flex items-center justify-center gap-2">
              <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
              </svg>
              Memproses...
            </span>
            <span v-else>Masuk Ke Aplikasi</span>
          </button>
        </form>

        <p class="mt-6 text-center text-xs text-surface-400">
          iRestora POS v1.0.0 • Keamanan SSL Terjamin
        </p>
      </div>
    </div>
  </div>
</template>