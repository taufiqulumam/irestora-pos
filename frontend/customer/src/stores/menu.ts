import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';
import type { Menu, Category } from '@shared/types';

export const useMenuStore = defineStore('menu', () => {
  const menus = ref<Menu[]>([]);
  const categories = ref<Category[]>([]);
  const loading = ref(false);
  const error = ref<string | null>(null);
  const lastFetched = ref<number | null>(null);

  const menusByCategory = computed(() => {
    const grouped: Record<string, Menu[]> = {};
    for (const menu of menus.value) {
      const catId = menu.category_id;
      if (!grouped[catId]) {
        grouped[catId] = [];
      }
      grouped[catId].push(menu);
    }
    return grouped;
  });

  const activeCategories = computed(() => 
    categories.value.filter(c => {
      const menus = menusByCategory.value[c.id];
      return menus && menus.length > 0;
    })
  );

  async function fetchMenus(outletId: string, force = false): Promise<Menu[]> {
    const now = Date.now();
    if (!force && lastFetched.value && now - lastFetched.value < 60000) {
      return menus.value;
    }

    loading.value = true;
    error.value = null;

    try {
      const [menusRes, categoriesRes] = await Promise.all([
        axios.get(`/api/menus?outlet_id=${outletId}`),
        axios.get(`/api/categories?outlet_id=${outletId}`),
      ]);
      
      menus.value = menusRes.data.data;
      categories.value = categoriesRes.data.data;
      lastFetched.value = now;
      
      return menus.value;
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Gagal memuat menu';
      throw err;
    } finally {
      loading.value = false;
    }
  }

  function getMenuById(id: string): Menu | undefined {
    return menus.value.find(m => m.id === id);
  }

  function searchMenus(query: string): Menu[] {
    const lower = query.toLowerCase();
    return menus.value.filter(m => 
      m.name.toLowerCase().includes(lower) ||
      m.description?.toLowerCase().includes(lower)
    );
  }

  function setMenus(newMenus: Menu[], newCategories: Category[]): void {
    menus.value = newMenus;
    categories.value = newCategories;
    lastFetched.value = Date.now();
  }

  return {
    menus,
    categories,
    loading,
    error,
    menusByCategory,
    activeCategories,
    fetchMenus,
    getMenuById,
    searchMenus,
    setMenus,
  };
});