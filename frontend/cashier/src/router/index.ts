import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/LoginView.vue'),
    meta: { public: true },
  },
  {
    path: '/shift/open',
    name: 'ShiftOpen',
    component: () => import('@/views/ShiftOpenView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: () => import('@/views/DashboardView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/tables',
    name: 'TableMap',
    component: () => import('@/views/TableMapView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/history',
    name: 'History',
    component: () => import('@/views/DashboardView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/shift/close',
    name: 'ShiftClose',
    component: () => import('@/views/ShiftCloseView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/order/:tableId?',
    name: 'Order',
    component: () => import('@/views/OrderView.vue'),
    meta: { requiresAuth: true },
    props: true,
  },
  {
    path: '/payment/:orderId',
    name: 'Payment',
    component: () => import('@/views/PaymentView.vue'),
    meta: { requiresAuth: true },
    props: true,
  },
  {
    path: '/receipt/:orderId',
    name: 'ReceiptPreview',
    component: () => import('@/views/ReceiptPreviewView.vue'),
    meta: { requiresAuth: true },
    props: true,
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/dashboard',
  },
];

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
});

let authInitialized = false;

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore();
  
  // Wait for auth initialization if not done yet
  if (!authInitialized) {
    await authStore.initializeAuth();
    authInitialized = true;
  }
  
  if (to.meta['requiresAuth'] && !authStore.isAuthenticated) {
    next({ name: 'Login', query: { redirect: to.fullPath } });
    return;
  }
  
  if (to.meta['public'] && authStore.isAuthenticated && to.name === 'Login') {
    next({ name: 'Dashboard' });
    return;
  }
  
  next();
});

export default router;