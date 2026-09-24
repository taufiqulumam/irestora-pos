import { defineComponent } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';

const LandingView = defineComponent({
  name: 'LandingView',
  template: `
    <main class="min-h-screen bg-surface-50 flex items-center justify-center px-6">
      <section class="w-full max-w-md rounded-2xl bg-white border border-surface-200 p-6 text-center shadow-sm">
        <div class="mx-auto mb-4 h-12 w-12 rounded-2xl bg-primary-100 text-primary-600 flex items-center justify-center">
          <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
        </div>
        <h1 class="text-xl font-bold text-surface-900">Scan QR meja</h1>
        <p class="mt-2 text-sm leading-6 text-surface-500">
          Halaman customer harus dibuka melalui QR meja atau URL yang berisi ID meja.
        </p>
      </section>
    </main>
  `,
});

const routes = [
  {
    path: '/',
    name: 'Landing',
    component: LandingView,
  },
  {
    path: '/:tableId',
    name: 'Menu',
    component: () => import('@/views/MenuView.vue'),
    props: true,
  },
  {
    path: '/:tableId/cart',
    name: 'Cart',
    component: () => import('@/views/CartView.vue'),
    props: true,
  },
  {
    path: '/:tableId/order/:orderId?',
    name: 'OrderStatus',
    component: () => import('@/views/OrderStatusView.vue'),
    props: true,
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/',
  },
];

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
});

export default router;
