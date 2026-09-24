<script setup lang="ts">
import axios from 'axios';
import { computed, onMounted, reactive, ref } from 'vue';

type User = {
  id: string;
  fullName: string;
  email: string;
  phone: string;
  roleName: string | null;
  role_id: string | null;
  outlet_id: string | null;
  outlet?: { name: string } | null;
  isActive: boolean;
};
type Alert = {
  id: string;
  rule_code: string;
  severity: string;
  status: string;
  details?: Record<string, number>;
  outlet?: { name: string };
};
type Report = {
  orderCount: number;
  paidOrderCount: number;
  voidOrderCount: number;
  grossSales: number;
  discountTotal: number;
  netSales: number;
  paymentTotals: Record<string, number>;
};
type Menu = {
  id: string;
  name: string;
  description?: string;
  image_url?: string;
  category_id?: string;
  is_active: boolean;
  category?: { name: string };
  prices?: Array<{ price: string; outlet_id?: string; outlet?: { name: string } }>;
};
type Category = { id: string; name: string; sort_order: number };
type Audit = {
  id: string;
  action: string;
  created_at: string;
  user?: { full_name: string };
  outlet?: { name: string };
  reason?: string;
};
type Option = { id: string; name: string };

const api = axios.create({
  baseURL: import.meta.env['VITE_API_URL'] || 'http://localhost:8000/api',
});
const token = ref(localStorage.getItem('admin_token') || '');
const currentUser = ref<User | null>(null);
const loginForm = reactive({ email: '', password: '' });
const report = ref<Report | null>(null);
const alerts = ref<Alert[]>([]);
const users = ref<User[]>([]);
const menus = ref<Menu[]>([]);
const audits = ref<Audit[]>([]);
const orders = ref<any[]>([]);
const activeView = ref('dashboard');
const loading = ref(false);
const error = ref('');
const notice = ref('');
const date = ref(new Date().toISOString().slice(0, 10));
const dateFrom = ref(date.value);
const dateTo = ref(date.value);
const outletId = ref('');
const auditAction = ref('');
const auditOutletId = ref('');
const auditActions = [
  'create_order',
  'payment_completed',
  'void_order',
  'apply_discount',
  'edit_price',
  'create_menu',
  'update_menu',
  'create_category',
  'update_category',
  'delete_category',
  'login',
];
const approvalForm = reactive({ orderId: '', type: 'discount', amount: 0, reason: '', pin: '' });
const roles = ref<Option[]>([]);
const outlets = ref<Option[]>([]);
const categories = ref<Category[]>([]);
const showEmployeeForm = ref(false);
const editingEmployeeId = ref<string | null>(null);
const savingEmployee = ref(false);
const employeeForm = reactive({
  fullName: '',
  email: '',
  phone: '',
  password: '',
  roleId: '',
  outletId: '',
  isActive: true,
});
const showMenuForm = ref(false);
const editingMenuId = ref<string | null>(null);
const savingMenu = ref(false);
const showCategoryForm = ref(false);
const editingCategoryId = ref<string | null>(null);
const savingCategory = ref(false);
const showPriceForm = ref(false);
const selectedMenu = ref<Menu | null>(null);
const menuForm = reactive({
  categoryId: '',
  name: '',
  description: '',
  imageUrl: '',
  isActive: true,
});
const categoryForm = reactive({ name: '', sortOrder: 0 });
const priceForm = reactive({ outletId: '', price: 0, isActive: true });
const role = computed(() => currentUser.value?.roleName || '');
const isAdmin = computed(() => role.value === 'admin');
const isManager = computed(() => role.value === 'manager' || isAdmin.value);
const permissions = computed(() => ({
  approval: ['supervisor', 'manager', 'admin'].includes(role.value),
  shift: ['supervisor', 'manager', 'admin'].includes(role.value),
  report: ['supervisor', 'manager', 'admin'].includes(role.value),
  fraud: ['supervisor', 'manager', 'admin'].includes(role.value),
  menu: isManager.value,
  people: isManager.value,
}));
const navItems = computed(() =>
  [
    { id: 'dashboard', label: 'Dashboard', icon: '◫', show: true },
    { id: 'approval', label: 'Approval', icon: '✓', show: permissions.value.approval },
    { id: 'shift', label: 'Shift', icon: '◷', show: permissions.value.shift },
    { id: 'report', label: 'Laporan', icon: '▤', show: permissions.value.report },
    { id: 'fraud', label: 'Fraud alert', icon: '!', show: permissions.value.fraud },
    { id: 'menu', label: 'Menu & harga', icon: '⌁', show: permissions.value.menu },
    { id: 'audit', label: 'Audit log', icon: '#', show: isManager.value },
    { id: 'people', label: 'Pegawai', icon: '♙', show: permissions.value.people },
  ].filter((item) => item.show)
);
const pageTitle = computed(
  () => navItems.value.find((item) => item.id === activeView.value)?.label || 'Dashboard'
);
const formatMoney = (value: number) =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(value || 0);
const headers = () => ({ headers: { Authorization: `Bearer ${token.value}` } });

async function login() {
  error.value = '';
  try {
    const response = await api.post('/auth/login', loginForm);
    token.value = response.data.data.accessToken;
    currentUser.value = response.data.data.user;
    localStorage.setItem('admin_token', token.value);
    await loadDashboard();
  } catch (cause: any) {
    error.value = cause.response?.data?.message || 'Login gagal.';
  }
}
async function loadDashboard() {
  loading.value = true;
  error.value = '';
  try {
    const config = headers();
    const [me, daily, alertResponse, orderResponse] = await Promise.all([
      api.get('/auth/me', config),
      api.get('/reports/daily', {
        ...config,
        params: {
          date_from: dateFrom.value,
          date_to: dateTo.value,
          outlet_id: outletId.value || undefined,
        },
      }),
      api.get('/fraud-alerts', { ...config, params: { outlet_id: outletId.value || undefined } }),
      api.get('/orders', {
        ...config,
        params: {
          date_from: dateFrom.value,
          date_to: dateTo.value,
          outlet_id: outletId.value || undefined,
        },
      }),
    ]);
    currentUser.value = me.data.data;
    report.value = daily.data.data;
    alerts.value = alertResponse.data.data?.data || alertResponse.data.data || [];
    orders.value = orderResponse.data.data?.data || [];
    if (['manager', 'admin'].includes(currentUser.value?.roleName || '')) {
      const userResponse = await api.get('/users', config);
      users.value = userResponse.data.data || [];
    }
  } catch (cause: any) {
    if ([401, 403].includes(cause.response?.status)) logout();
    error.value = cause.response?.data?.message || 'Dashboard belum dapat dimuat.';
  } finally {
    loading.value = false;
  }
}
async function loadMenuData() {
  try {
    const config = headers();
    const [menuResponse, categoryResponse, outletResponse] = await Promise.all([
      api.get('/managed-menus', config),
      api.get('/menu-categories', config),
      api.get('/outlets', config),
    ]);
    menus.value = menuResponse.data.data || [];
    categories.value = categoryResponse.data.data || [];
    outlets.value = (outletResponse.data.data || []).map((outlet: Option) => ({
      id: outlet.id,
      name: outlet.name,
    }));
  } catch (cause: any) {
    error.value = cause.response?.data?.message || 'Data menu belum dapat dimuat.';
  }
}
function openMenuCreate() {
  editingMenuId.value = null;
  Object.assign(menuForm, {
    categoryId: categories.value[0]?.id || '',
    name: '',
    description: '',
    imageUrl: '',
    isActive: true,
  });
  showMenuForm.value = true;
  error.value = '';
}
function openMenuEdit(menu: Menu) {
  editingMenuId.value = menu.id;
  Object.assign(menuForm, {
    categoryId: menu.category_id || '',
    name: menu.name,
    description: menu.description || '',
    imageUrl: menu.image_url || '',
    isActive: menu.is_active,
  });
  showMenuForm.value = true;
  error.value = '';
}
async function saveMenu() {
  savingMenu.value = true;
  error.value = '';
  try {
    if (editingMenuId.value) await api.put(`/menus/${editingMenuId.value}`, menuForm, headers());
    else await api.post('/menus', menuForm, headers());
    showMenuForm.value = false;
    notice.value = editingMenuId.value ? 'Menu diperbarui.' : 'Menu berhasil dibuat.';
    await loadMenuData();
  } catch (cause: any) {
    error.value = cause.response?.data?.message || 'Menu belum tersimpan.';
  } finally {
    savingMenu.value = false;
  }
}
async function toggleMenu(menu: Menu) {
  try {
    await api.put(
      `/menus/${menu.id}`,
      {
        categoryId: menu.category_id,
        name: menu.name,
        description: menu.description || '',
        imageUrl: menu.image_url || '',
        isActive: !menu.is_active,
      },
      headers()
    );
    notice.value = `${menu.name} ${menu.is_active ? 'dinonaktifkan' : 'diaktifkan'}.`;
    await loadMenuData();
  } catch (cause: any) {
    error.value = cause.response?.data?.message || 'Status menu belum dapat diubah.';
  }
}
function openCategoryCreate() {
  editingCategoryId.value = null;
  Object.assign(categoryForm, { name: '', sortOrder: 0 });
  showCategoryForm.value = true;
  error.value = '';
}
function openCategoryEdit(category: Category) {
  editingCategoryId.value = category.id;
  Object.assign(categoryForm, { name: category.name, sortOrder: category.sort_order });
  showCategoryForm.value = true;
  error.value = '';
}
async function saveCategory() {
  savingCategory.value = true;
  error.value = '';
  try {
    if (editingCategoryId.value)
      await api.put(`/categories/${editingCategoryId.value}`, categoryForm, headers());
    else await api.post('/categories', categoryForm, headers());
    showCategoryForm.value = false;
    notice.value = editingCategoryId.value ? 'Kategori diperbarui.' : 'Kategori berhasil dibuat.';
    await loadMenuData();
  } catch (cause: any) {
    error.value = cause.response?.data?.message || 'Kategori belum tersimpan.';
  } finally {
    savingCategory.value = false;
  }
}
async function deleteCategory(category: Category) {
  if (!window.confirm(`Hapus kategori ${category.name}?`)) return;
  try {
    await api.delete(`/categories/${category.id}`, headers());
    notice.value = 'Kategori dihapus.';
    await loadMenuData();
  } catch (cause: any) {
    error.value = cause.response?.data?.message || 'Kategori tidak dapat dihapus.';
  }
}
function openPriceEdit(menu: Menu) {
  selectedMenu.value = menu;
  const existing = menu.prices?.[0];
  Object.assign(priceForm, {
    outletId: existing?.outlet_id || currentUser.value?.outlet_id || outlets.value[0]?.id || '',
    price: Number(existing?.price || 0),
    isActive: true,
  });
  showPriceForm.value = true;
  error.value = '';
}
async function savePrice() {
  if (!selectedMenu.value) return;
  try {
    await api.put(`/menus/${selectedMenu.value.id}/price`, priceForm, headers());
    showPriceForm.value = false;
    notice.value = 'Harga menu diperbarui.';
    await loadMenuData();
  } catch (cause: any) {
    error.value = cause.response?.data?.message || 'Harga belum tersimpan.';
  }
}
async function loadEmployeeOptions() {
  try {
    const config = headers();
    const [roleResponse, outletResponse] = await Promise.all([
      api.get('/roles', config),
      api.get('/outlets', config),
    ]);
    roles.value = roleResponse.data.data || [];
    outlets.value = (outletResponse.data.data || []).map((outlet: Option) => ({
      id: outlet.id,
      name: outlet.name,
    }));
  } catch (cause: any) {
    error.value = cause.response?.data?.message || 'Role dan outlet belum dapat dimuat.';
  }
}
function openEmployeeCreate() {
  editingEmployeeId.value = null;
  Object.assign(employeeForm, {
    fullName: '',
    email: '',
    phone: '',
    password: '',
    roleId: roles.value.find((item) => item.name === 'cashier')?.id || '',
    outletId: currentUser.value?.outlet_id || outlets.value[0]?.id || '',
    isActive: true,
  });
  showEmployeeForm.value = true;
  error.value = '';
}
function openEmployeeEdit(user: User) {
  editingEmployeeId.value = user.id;
  Object.assign(employeeForm, {
    fullName: user.fullName,
    email: user.email,
    phone: user.phone,
    password: '',
    roleId: user.role_id || '',
    outletId: user.outlet_id || '',
    isActive: user.isActive,
  });
  showEmployeeForm.value = true;
  error.value = '';
}
async function saveEmployee() {
  savingEmployee.value = true;
  error.value = '';
  try {
    const adminRole = roles.value.find((item) => item.name === 'admin');
    const payload = {
      ...employeeForm,
      outletId: employeeForm.roleId === adminRole?.id ? null : employeeForm.outletId || null,
    };
    if (editingEmployeeId.value)
      await api.put(`/users/${editingEmployeeId.value}`, payload, headers());
    else await api.post('/users', payload, headers());
    showEmployeeForm.value = false;
    notice.value = editingEmployeeId.value
      ? 'Data pegawai diperbarui.'
      : 'Akun pegawai berhasil dibuat.';
    await loadDashboard();
  } catch (cause: any) {
    error.value =
      cause.response?.data?.message ||
      Object.values(cause.response?.data?.errors || {})
        .flat()
        .join(' ') ||
      'Data pegawai belum tersimpan.';
  } finally {
    savingEmployee.value = false;
  }
}
async function toggleEmployee(user: User) {
  try {
    await api.put(
      `/users/${user.id}`,
      {
        fullName: user.fullName,
        email: user.email,
        phone: user.phone,
        roleId: user.role_id,
        outletId: user.outlet_id,
        isActive: !user.isActive,
      },
      headers()
    );
    notice.value = `${user.fullName} ${user.isActive ? 'dinonaktifkan' : 'diaktifkan'}.`;
    await loadDashboard();
  } catch (cause: any) {
    error.value = cause.response?.data?.message || 'Status pegawai belum dapat diubah.';
  }
}
async function loadAudits() {
  try {
    if (isAdmin.value && !outlets.value.length) {
      const outletResponse = await api.get('/outlets', headers());
      outlets.value = (outletResponse.data.data || []).map((outlet: Option) => ({
        id: outlet.id,
        name: outlet.name,
      }));
    }
    const response = await api.get('/audit-logs', {
      ...headers(),
      params: {
        date_from: dateFrom.value,
        date_to: dateTo.value,
        action: auditAction.value || undefined,
        outlet_id: isAdmin.value ? auditOutletId.value || undefined : undefined,
      },
    });
    audits.value = response.data.data?.data || [];
  } catch (cause: any) {
    error.value = cause.response?.data?.message || 'Audit log belum dapat dimuat.';
  }
}
async function reviewAlert(alert: Alert, status: string) {
  try {
    await api.post(`/fraud-alerts/${alert.id}/review`, { status }, headers());
    notice.value = 'Fraud alert diperbarui.';
    await loadDashboard();
  } catch (cause: any) {
    error.value = cause.response?.data?.message || 'Alert belum dapat diperbarui.';
  }
}
async function applyApproval() {
  try {
    const endpoint =
      approvalForm.type === 'discount'
        ? `/orders/${approvalForm.orderId}/discount`
        : `/orders/${approvalForm.orderId}/void`;
    const payload =
      approvalForm.type === 'discount'
        ? {
            discountTotal: approvalForm.amount,
            reason: approvalForm.reason,
            approvedByPin: approvalForm.pin,
          }
        : { reason: approvalForm.reason, approved_by_pin: approvalForm.pin };
    await api.post(endpoint, payload, headers());
    notice.value = 'Approval berhasil diproses.';
    Object.assign(approvalForm, { orderId: '', amount: 0, reason: '', pin: '' });
    await loadDashboard();
  } catch (cause: any) {
    error.value = cause.response?.data?.message || 'Approval belum dapat diproses.';
  }
}
function logout() {
  token.value = '';
  currentUser.value = null;
  localStorage.removeItem('admin_token');
}
function selectView(id: string) {
  activeView.value = id;
  error.value = '';
  if (id === 'report' || id === 'dashboard') loadDashboard();
  if (id === 'menu') loadMenuData();
  if (id === 'audit') loadAudits();
  if (id === 'people') loadEmployeeOptions();
}
onMounted(() => {
  if (token.value) loadDashboard();
});
</script>

<template>
  <main v-if="!token" class="login-shell">
    <section class="login-card">
      <div class="brand-mark">IR</div>
      <p class="eyebrow">iRestora / Control room</p>
      <h1>Jaga ritme<br /><em>outlet.</em></h1>
      <p class="muted">
        Ruang kerja supervisor dan manager untuk keputusan operasional yang cepat.
      </p>
      <form @submit.prevent="login">
        <label
          >Email<input
            v-model="loginForm.email"
            type="email"
            required
            placeholder="nama@irestora.com" /></label
        ><label>Password<input v-model="loginForm.password" type="password" required /></label
        ><button class="primary" type="submit">Masuk <span>→</span></button>
      </form>
      <p v-if="error" class="error">{{ error }}</p>
    </section>
    <aside class="login-art">
      <span>OPS / 01</span>
      <div>
        <strong>Every shift<br />has a story.</strong><small>Control room operasional outlet</small>
      </div>
    </aside>
  </main>
  <main v-else class="app-shell">
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-mark">IR</div>
        <div><strong>iRestora</strong><small>Control room</small></div>
      </div>
      <div class="outlet-switch">
        <small>OUTLET AKTIF</small
        ><strong>{{ currentUser?.outlet?.name || 'Semua outlet' }}</strong>
      </div>
      <nav>
        <button
          v-for="item in navItems"
          :key="item.id"
          :class="{ selected: activeView === item.id }"
          @click="selectView(item.id)"
        >
          <span>{{ item.icon }}</span
          >{{ item.label }}
        </button>
      </nav>
      <button class="account" @click="logout">
        <span class="avatar">{{ currentUser?.fullName?.slice(0, 1) || 'A' }}</span
        ><span
          ><strong>{{ currentUser?.fullName || 'Pengguna' }}</strong
          ><small>{{ role === 'admin' ? 'Admin pusat' : role }}</small></span
        ><b>↗</b>
      </button>
    </aside>
    <section class="workspace">
      <header class="topbar">
        <div>
          <p class="eyebrow">{{ isAdmin ? 'Central view' : 'Outlet operations' }}</p>
          <h2>{{ pageTitle }}</h2>
        </div>
        <div class="top-meta">
          <span class="live"><i></i> Live data</span
          ><button title="Refresh dashboard" @click="loadDashboard">↻</button>
        </div>
      </header>
      <div class="content">
        <p v-if="notice" class="notice">{{ notice }}</p>
        <p v-if="error" class="error">{{ error }}</p>
        <div v-if="loading" class="loading">Memuat control room...</div>
        <template v-else>
          <section v-if="activeView === 'dashboard'" class="view">
            <div class="welcome">
              <div>
                <p class="eyebrow">
                  {{
                    new Date().toLocaleDateString('id-ID', {
                      weekday: 'long',
                      day: 'numeric',
                      month: 'long',
                    })
                  }}
                </p>
                <h1>Selamat datang, {{ currentUser?.fullName?.split(' ')[0] }}.</h1>
                <p class="muted">
                  Berikut ringkasan kondisi operasional
                  {{ isAdmin ? 'seluruh jaringan' : 'outlet hari ini' }}.
                </p>
              </div>
              <button class="primary" @click="selectView('report')">Lihat laporan →</button>
            </div>
            <div class="metrics">
              <div>
                <span>Net sales</span><strong>{{ formatMoney(report?.netSales || 0) }}</strong
                ><small>{{ report?.paidOrderCount || 0 }} transaksi lunas</small>
              </div>
              <div>
                <span>Total order</span><strong>{{ report?.orderCount || 0 }}</strong
                ><small>{{ report?.voidOrderCount || 0 }} order void</small>
              </div>
              <div>
                <span>Diskon diberikan</span
                ><strong>{{ formatMoney(report?.discountTotal || 0) }}</strong
                ><small>tercatat hari ini</small>
              </div>
              <div>
                <span>Alert terbuka</span
                ><strong>{{ alerts.filter((alert) => alert.status === 'open').length }}</strong
                ><small>perlu ditinjau</small>
              </div>
            </div>
            <div class="split">
              <section class="panel">
                <div class="panel-head">
                  <div>
                    <h3>Aktivitas terbaru</h3>
                    <p>Order hari ini yang masuk ke sistem.</p>
                  </div>
                  <button @click="selectView('report')">Semua order →</button>
                </div>
                <div v-if="!orders.length" class="empty">Belum ada order hari ini.</div>
                <div v-for="order in orders.slice(0, 5)" :key="order.id" class="activity">
                  <span class="activity-icon">{{ order.status === 'paid' ? '✓' : '•' }}</span>
                  <div>
                    <strong>{{ order.order_number || 'Order' }}</strong
                    ><small
                      >{{ order.status }} · {{ formatMoney(Number(order.grand_total)) }}</small
                    >
                  </div>
                  <time>{{
                    order.created_at
                      ? new Date(order.created_at).toLocaleTimeString('id-ID', {
                          hour: '2-digit',
                          minute: '2-digit',
                        })
                      : ''
                  }}</time>
                </div>
              </section>
              <section class="panel accent-panel">
                <p class="eyebrow">Perlu perhatian</p>
                <h3>
                  {{
                    alerts.length
                      ? `${alerts.length} alert menunggu review`
                      : 'Semua terlihat baik.'
                  }}
                </h3>
                <p class="muted">Review indikasi fraud sebelum menutup hari operasional.</p>
                <button class="dark-button" @click="selectView('fraud')">Buka fraud alert →</button>
              </section>
            </div>
          </section>
          <section v-else-if="activeView === 'approval'" class="view">
            <div class="section-intro">
              <p class="eyebrow">Critical workflow</p>
              <h1>Approval center</h1>
              <p class="muted">Validasi aksi sensitif dengan PIN supervisor atau manager.</p>
            </div>
            <section class="panel form-panel">
              <div class="tabs">
                <button
                  :class="{ active: approvalForm.type === 'discount' }"
                  @click="approvalForm.type = 'discount'"
                >
                  Approval diskon</button
                ><button
                  :class="{ active: approvalForm.type === 'void' }"
                  @click="approvalForm.type = 'void'"
                >
                  Approval void
                </button>
              </div>
              <form @submit.prevent="applyApproval">
                <label
                  >ID order<input
                    v-model="approvalForm.orderId"
                    required
                    placeholder="UUID order" /></label
                ><label v-if="approvalForm.type === 'discount'"
                  >Nominal diskon<input
                    v-model.number="approvalForm.amount"
                    type="number"
                    min="0"
                    required /></label
                ><label>Alasan<textarea v-model="approvalForm.reason" required rows="3" /></label
                ><label
                  >PIN approval<input
                    v-model="approvalForm.pin"
                    type="password"
                    required
                    minlength="4"
                    maxlength="6" /></label
                ><button class="primary" type="submit">Konfirmasi approval →</button>
              </form>
            </section>
          </section>
          <section v-else-if="activeView === 'report'" class="view">
            <div class="section-intro inline">
              <div>
                <p class="eyebrow">Performance / Daily</p>
                <h1>Laporan harian</h1>
                <p class="muted">Ringkasan performa penjualan berdasarkan outlet dan periode.</p>
              </div>
              <div class="filters">
                <input v-model="dateFrom" type="date" @change="loadDashboard" /><input
                  v-model="dateTo"
                  type="date"
                  @change="loadDashboard"
                /><input
                  v-if="isAdmin"
                  v-model="outletId"
                  placeholder="UUID outlet"
                  @change="loadDashboard"
                />
              </div>
            </div>
            <div class="metrics report-metrics">
              <div>
                <span>Gross sales</span><strong>{{ formatMoney(report?.grossSales || 0) }}</strong>
              </div>
              <div>
                <span>Net sales</span><strong>{{ formatMoney(report?.netSales || 0) }}</strong>
              </div>
              <div>
                <span>Paid orders</span><strong>{{ report?.paidOrderCount || 0 }}</strong>
              </div>
            </div>
            <section class="panel">
              <div class="panel-head">
                <div>
                  <h3>Order terbaru</h3>
                  <p>Transaksi pada periode terpilih.</p>
                </div>
              </div>
              <div v-for="order in orders" :key="order.id" class="activity">
                <span class="status-dot" :class="order.status"></span>
                <div>
                  <strong>{{ order.order_number || order.id }}</strong
                  ><small>{{ order.status }} · {{ order.source || 'cashier' }}</small>
                </div>
                <b>{{ formatMoney(Number(order.grand_total)) }}</b>
              </div>
              <div v-if="!orders.length" class="empty">Belum ada transaksi.</div>
            </section>
          </section>
          <section v-else-if="activeView === 'fraud'" class="view">
            <div class="section-intro">
              <p class="eyebrow">Risk monitor</p>
              <h1>Fraud alert</h1>
              <p class="muted">Indikasi yang membutuhkan peninjauan supervisor atau manager.</p>
            </div>
            <section class="panel">
              <div v-for="alert in alerts" :key="alert.id" class="alert-row">
                <span class="severity" :class="alert.severity">{{ alert.severity }}</span>
                <div>
                  <strong>{{ alert.rule_code.replaceAll('_', ' ') }}</strong
                  ><small>{{ alert.outlet?.name || 'Outlet' }} · {{ alert.status }}</small>
                </div>
                <button v-if="alert.status === 'open'" @click="reviewAlert(alert, 'reviewed')">
                  Tandai reviewed
                </button>
              </div>
              <div v-if="!alerts.length" class="empty">Tidak ada fraud alert.</div>
            </section>
          </section>
          <section v-else-if="activeView === 'menu'" class="view">
            <div class="section-intro">
              <p class="eyebrow">Master data</p>
              <h1>Menu & harga</h1>
              <p class="muted">Kelola katalog, kategori, dan harga berdasarkan outlet.</p>
            </div>
            <div class="master-actions">
              <button class="secondary" @click="openCategoryCreate">+ Kategori</button
              ><button class="primary" @click="openMenuCreate">+ Menu baru</button>
            </div>
            <div class="category-strip">
              <div v-for="category in categories" :key="category.id" class="category-chip">
                <span>{{ category.name }}</span
                ><button @click="openCategoryEdit(category)">Edit</button
                ><button @click="deleteCategory(category)">×</button>
              </div>
              <span v-if="!categories.length" class="muted">Belum ada kategori.</span>
            </div>
            <section class="panel">
              <div v-for="menu in menus" :key="menu.id" class="activity">
                <span class="activity-icon">⌁</span>
                <div>
                  <strong>{{ menu.name }}</strong
                  ><small
                    >{{ menu.category?.name || 'Tanpa kategori' }} ·
                    {{
                      menu.prices
                        ?.map(
                          (price) =>
                            `${price.outlet?.name || 'Outlet'} ${formatMoney(Number(price.price))}`
                        )
                        .join(' / ') || 'Belum ada harga'
                    }}</small
                  >
                </div>
                <span class="employee-status">{{ menu.is_active ? 'Aktif' : 'Nonaktif' }}</span
                ><button class="row-edit" @click="openPriceEdit(menu)">Harga</button
                ><button class="row-edit" @click="openMenuEdit(menu)">Edit</button
                ><button class="row-edit muted-button" @click="toggleMenu(menu)">
                  {{ menu.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                </button>
              </div>
              <div v-if="!menus.length" class="empty">Belum ada data menu.</div>
            </section>
          </section>
          <section v-else-if="activeView === 'audit'" class="view">
            <div class="section-intro">
              <p class="eyebrow">Traceability</p>
              <h1>Audit log</h1>
              <p class="muted">Riwayat aksi sensitif yang tercatat dan tidak dapat diubah.</p>
            </div>
            <div class="audit-filters">
              <input
                v-model="dateFrom"
                type="date"
                aria-label="Tanggal mulai audit"
                @change="loadAudits"
              />
              <input
                v-model="dateTo"
                type="date"
                aria-label="Tanggal akhir audit"
                @change="loadAudits"
              />
              <select v-model="auditAction" aria-label="Filter action audit" @change="loadAudits">
                <option value="">Semua action</option>
                <option v-for="action in auditActions" :key="action" :value="action">
                  {{ action.replaceAll('_', ' ') }}
                </option>
              </select>
              <select
                v-if="isAdmin"
                v-model="auditOutletId"
                aria-label="Filter outlet audit"
                @change="loadAudits"
              >
                <option value="">Semua outlet</option>
                <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">
                  {{ outlet.name }}
                </option>
              </select>
            </div>
            <section class="panel">
              <div v-for="audit in audits" :key="audit.id" class="activity">
                <span class="activity-icon">#</span>
                <div>
                  <strong>{{ audit.action.replaceAll('_', ' ') }}</strong
                  ><small
                    >{{ audit.user?.full_name || 'User' }} · {{ audit.outlet?.name || 'Outlet' }} ·
                    {{ audit.reason || 'Tanpa alasan' }}</small
                  >
                </div>
                <time>{{ new Date(audit.created_at).toLocaleString('id-ID') }}</time>
              </div>
              <div v-if="!audits.length" class="empty">Belum ada audit log.</div>
            </section>
          </section>
          <section v-else-if="activeView === 'people'" class="view">
            <div class="section-intro inline">
              <div>
                <p class="eyebrow">Access management</p>
                <h1>Pegawai</h1>
                <p class="muted">Kelola akun, role, outlet, dan status akses pegawai.</p>
              </div>
              <button class="primary" @click="openEmployeeCreate">+ Tambah pegawai</button>
            </div>
            <section class="panel">
              <div v-for="user in users" :key="user.id" class="activity">
                <span class="avatar">{{ user.fullName.slice(0, 1) }}</span>
                <div>
                  <strong>{{ user.fullName }}</strong
                  ><small
                    >{{ user.email }} · {{ user.roleName || 'Tanpa role' }} ·
                    {{ user.outlet?.name || 'Semua outlet' }}</small
                  >
                </div>
                <span class="employee-status">{{ user.isActive ? 'Aktif' : 'Nonaktif' }}</span
                ><button class="row-edit" @click="openEmployeeEdit(user)">Edit</button
                ><button class="row-edit muted-button" @click="toggleEmployee(user)">
                  {{ user.isActive ? 'Nonaktifkan' : 'Aktifkan' }}
                </button>
              </div>
              <div v-if="!users.length" class="empty">Belum ada pegawai.</div>
            </section>
          </section>
          <section v-else class="view">
            <div class="section-intro">
              <p class="eyebrow">Workspace</p>
              <h1>{{ pageTitle }}</h1>
              <p class="muted">Rekonsiliasi shift tersedia melalui Cashier App.</p>
            </div>
            <section class="panel placeholder">
              <div class="placeholder-icon">
                {{ navItems.find((item) => item.id === activeView)?.icon }}
              </div>
              <h3>Rekonsiliasi shift</h3>
              <p>Gunakan alur tutup shift untuk memeriksa kas fisik dan selisih operasional.</p>
              <button class="primary" @click="selectView('dashboard')">Kembali ke dashboard</button>
            </section>
          </section>
        </template>
      </div>
    </section>
    <div
      v-if="showEmployeeForm || showMenuForm || showCategoryForm || showPriceForm"
      class="modal-backdrop"
      @click.self="showEmployeeForm = showMenuForm = showCategoryForm = showPriceForm = false"
    >
      <section class="modal">
        <div class="modal-heading">
          <div>
            <p class="eyebrow">
              {{
                showEmployeeForm
                  ? editingEmployeeId
                    ? 'Edit pegawai'
                    : 'Akun baru'
                  : showMenuForm
                    ? editingMenuId
                      ? 'Edit menu'
                      : 'Menu baru'
                    : showCategoryForm
                      ? editingCategoryId
                        ? 'Edit kategori'
                        : 'Kategori baru'
                      : 'Atur harga'
              }}
            </p>
            <h2>
              {{
                showEmployeeForm
                  ? editingEmployeeId
                    ? 'Perbarui data'
                    : 'Tambah pegawai'
                  : showMenuForm
                    ? 'Menu'
                    : showCategoryForm
                      ? 'Kategori'
                      : `Harga ${selectedMenu?.name || ''}`
              }}
            </h2>
          </div>
          <button
            class="close-button"
            @click="showEmployeeForm = showMenuForm = showCategoryForm = showPriceForm = false"
          >
            ×
          </button>
        </div>
        <form v-if="showEmployeeForm" class="employee-form" @submit.prevent="saveEmployee">
          <label>Nama lengkap<input v-model="employeeForm.fullName" required /></label>
          <div class="form-grid">
            <label>Email<input v-model="employeeForm.email" type="email" required /></label
            ><label>No. telepon<input v-model="employeeForm.phone" required /></label>
          </div>
          <div class="form-grid">
            <label
              >Role<select v-model="employeeForm.roleId" required>
                <option v-for="item in roles" :key="item.id" :value="item.id">
                  {{ item.name }}
                </option>
              </select></label
            ><label
              >Outlet<select v-model="employeeForm.outletId">
                <option value="">Semua outlet</option>
                <option v-for="item in outlets" :key="item.id" :value="item.id">
                  {{ item.name }}
                </option>
              </select></label
            >
          </div>
          <label
            >Password
            <small>{{
              editingEmployeeId ? '(kosongkan jika tidak diubah)' : '(minimal 8 karakter)'
            }}</small
            ><input
              v-model="employeeForm.password"
              type="password"
              :required="!editingEmployeeId"
              minlength="8" /></label
          ><label v-if="editingEmployeeId" class="check-row"
            ><input v-model="employeeForm.isActive" type="checkbox" /> Akun aktif</label
          >
          <div class="modal-actions">
            <button type="button" class="secondary" @click="showEmployeeForm = false">Batal</button
            ><button class="primary" type="submit" :disabled="savingEmployee">
              {{ savingEmployee ? 'Menyimpan...' : 'Simpan pegawai' }}
            </button>
          </div>
        </form>
        <form v-else-if="showMenuForm" class="employee-form" @submit.prevent="saveMenu">
          <label>Nama menu<input v-model="menuForm.name" required /></label
          ><label
            >Kategori<select v-model="menuForm.categoryId" required>
              <option v-for="item in categories" :key="item.id" :value="item.id">
                {{ item.name }}
              </option>
            </select></label
          ><label>Deskripsi<textarea v-model="menuForm.description" rows="3" /></label
          ><label class="check-row"
            ><input v-model="menuForm.isActive" type="checkbox" /> Menu aktif</label
          >
          <div class="modal-actions">
            <button type="button" class="secondary" @click="showMenuForm = false">Batal</button
            ><button class="primary" type="submit" :disabled="savingMenu">Simpan menu</button>
          </div>
        </form>
        <form v-else-if="showCategoryForm" class="employee-form" @submit.prevent="saveCategory">
          <label>Nama kategori<input v-model="categoryForm.name" required /></label
          ><label
            >Urutan tampil<input v-model.number="categoryForm.sortOrder" type="number" min="0"
          /></label>
          <div class="modal-actions">
            <button type="button" class="secondary" @click="showCategoryForm = false">Batal</button
            ><button class="primary" type="submit" :disabled="savingCategory">
              Simpan kategori
            </button>
          </div>
        </form>
        <form v-else class="employee-form" @submit.prevent="savePrice">
          <label
            >Outlet<select v-model="priceForm.outletId" required>
              <option v-for="item in outlets" :key="item.id" :value="item.id">
                {{ item.name }}
              </option>
            </select></label
          ><label
            >Harga (IDR)<input
              v-model.number="priceForm.price"
              type="number"
              min="0"
              required /></label
          ><label class="check-row"
            ><input v-model="priceForm.isActive" type="checkbox" /> Harga aktif</label
          >
          <div class="modal-actions">
            <button type="button" class="secondary" @click="showPriceForm = false">Batal</button
            ><button class="primary" type="submit">Simpan harga</button>
          </div>
        </form>
      </section>
    </div>
  </main>
</template>
