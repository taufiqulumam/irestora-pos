<script setup lang="ts">
import { onMounted } from 'vue';
import { useTableStore } from '@/stores/table';
import { useCartStore } from '@/stores/cart';
import router from '@/router';
import { RouterView } from 'vue-router';

const tableStore = useTableStore();
const cartStore = useCartStore();

onMounted(() => {
  // Initialize cart from localStorage
  cartStore.loadFromStorage();
});
</script>

<template>
  <div id="app" class="min-h-screen bg-surface-50">
    <RouterView v-slot="{ Component }">
      <transition name="slide" mode="out-in">
        <component :is="Component" />
      </transition>
    </RouterView>
  </div>
</template>

<style>
.slide-enter-active,
.slide-leave-active {
  transition: all 0.2s ease;
}
.slide-enter-from,
.slide-leave-to {
  opacity: 0;
  transform: translateX(20px);
}
</style>