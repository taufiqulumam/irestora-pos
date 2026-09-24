import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';
import { STORAGE_KEYS } from '@shared/utils';
import type { User } from '@shared/types';

interface AuthState {
  user: User | null;
  token: string | null;
  outletId: string | null;
  deviceId: string;
}

type RawUser = Partial<User> & {
  fullName?: string;
  roleName?: string | null;
  isActive?: boolean;
  lastLoginAt?: string | null;
  createdAt?: string;
  updatedAt?: string;
};

const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

export const useAuthStore = defineStore('auth', () => {
  const state = ref<AuthState>({
    user: null,
    token: null,
    outletId: null,
    deviceId: '',
  });

  const isAuthenticated = computed(() => !!state.value.token && !!state.value.user);
  const user = computed(() => state.value.user);
  const token = computed(() => state.value.token);
  const outletId = computed(() => state.value.outletId);
  const deviceId = computed(() => state.value.deviceId);

  function generateDeviceId(): string {
    const stored = localStorage.getItem(STORAGE_KEYS.DEVICE_ID);
    if (stored) return stored;
    const newId = `device_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    localStorage.setItem(STORAGE_KEYS.DEVICE_ID, newId);
    return newId;
  }

  function isValidOutletId(outletId: string | null | undefined): outletId is string {
    return typeof outletId === 'string' && UUID_PATTERN.test(outletId);
  }

  function normalizeUser(rawUser: RawUser): User {
    return {
      ...rawUser,
      outlet_id: rawUser.outlet_id || rawUser.outlet?.id || null,
      role_id: rawUser.role_id || rawUser.role?.id || '',
      full_name: rawUser.full_name || rawUser.fullName || '',
      is_active: rawUser.is_active ?? rawUser.isActive ?? true,
      last_login_at: rawUser.last_login_at ?? rawUser.lastLoginAt ?? null,
      created_at: rawUser.created_at || rawUser.createdAt || '',
      updated_at: rawUser.updated_at || rawUser.updatedAt || '',
    } as User;
  }

  function setAuthenticatedUser(userData: RawUser, accessToken?: string): void {
    const normalizedUser = normalizeUser(userData);
    const nextOutletId = isValidOutletId(normalizedUser.outlet_id) ? normalizedUser.outlet_id : null;

    if (accessToken) {
      state.value.token = accessToken;
      axios.defaults.headers.common['Authorization'] = `Bearer ${accessToken}`;
      localStorage.setItem(STORAGE_KEYS.AUTH_TOKEN, accessToken);
    }

    state.value.user = normalizedUser;
    state.value.outletId = nextOutletId;

    localStorage.setItem(STORAGE_KEYS.USER_DATA, JSON.stringify(normalizedUser));
    if (nextOutletId) {
      localStorage.setItem(STORAGE_KEYS.OUTLET_ID, nextOutletId);
    } else {
      localStorage.removeItem(STORAGE_KEYS.OUTLET_ID);
    }
  }

  async function login(email: string, password: string): Promise<void> {
    const deviceId = generateDeviceId();
    
    const response = await axios.post('/api/auth/login', { email, password }, {
      headers: { 'X-Device-Id': deviceId },
    });
    
    const { accessToken, user: userData } = response.data.data;
    
    state.value.deviceId = deviceId;
    setAuthenticatedUser(userData, accessToken);
  }

  function logout(): void {
    state.value.token = null;
    state.value.user = null;
    state.value.outletId = null;
    
    delete axios.defaults.headers.common['Authorization'];
    
    localStorage.removeItem(STORAGE_KEYS.AUTH_TOKEN);
    localStorage.removeItem(STORAGE_KEYS.USER_DATA);
    localStorage.removeItem(STORAGE_KEYS.OUTLET_ID);
  }

  async function initializeAuth(): Promise<boolean> {
    const storedToken = localStorage.getItem(STORAGE_KEYS.AUTH_TOKEN);
    const storedUser = localStorage.getItem(STORAGE_KEYS.USER_DATA);
    const storedOutletId = localStorage.getItem(STORAGE_KEYS.OUTLET_ID);
    const storedDeviceId = localStorage.getItem(STORAGE_KEYS.DEVICE_ID);
    
    if (storedToken && storedUser) {
      const parsedUser = normalizeUser(JSON.parse(storedUser));
      const cachedOutletId = isValidOutletId(storedOutletId)
        ? storedOutletId
        : isValidOutletId(parsedUser.outlet_id)
          ? parsedUser.outlet_id
          : null;

      state.value.token = storedToken;
      state.value.user = parsedUser;
      state.value.outletId = cachedOutletId;
      state.value.deviceId = storedDeviceId || generateDeviceId();
      
      axios.defaults.headers.common['Authorization'] = `Bearer ${storedToken}`;
      
      try {
        const response = await axios.get('/api/auth/me');
        setAuthenticatedUser(response.data.data, storedToken);
        return true;
      } catch {
        logout();
        return false;
      }
    }
    
    state.value.deviceId = generateDeviceId();
    return false;
  }

  function updateUser(userData: Partial<User>): void {
    if (state.value.user) {
      setAuthenticatedUser({ ...state.value.user, ...userData });
    }
  }

  return {
    state,
    isAuthenticated,
    user,
    token,
    outletId,
    deviceId,
    login,
    logout,
    initializeAuth,
    updateUser,
  };
});
