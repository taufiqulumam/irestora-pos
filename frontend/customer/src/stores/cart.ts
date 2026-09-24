import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { generateUUID } from '@shared/utils';
import type { Menu, OrderItem, CalculatedTotals, OutletTaxConfig } from '@shared/types';
import { calculateOrderTotals } from '@shared/calculator';

interface CartItem extends OrderItem {
  localId: string;
}

export const useCartStore = defineStore('cart', () => {
  const items = ref<CartItem[]>([]);
  const discountTotal = ref(0);
  const notes = ref('');
  const outletConfig = ref<OutletTaxConfig>({
    pb1Rate: 10,
    serviceChargeRate: 5,
    roundingEnabled: true,
  });

  const subtotal = computed(() => 
    items.value.reduce((sum, item) => sum + item.price_snapshot * item.qty, 0)
  );

  const itemCount = computed(() => 
    items.value.reduce((sum, item) => sum + item.qty, 0)
  );

  const totals = computed(() => 
    calculateOrderTotals(
      items.value.map(i => ({ priceSnapshot: i.price_snapshot, qty: i.qty })),
      discountTotal.value,
      outletConfig.value
    )
  );

  const grandTotal = computed(() => totals.value.grandTotal);

  function setOutletConfig(config: OutletTaxConfig): void {
    outletConfig.value = config;
  }

  function addItem(menu: Menu, qty = 1, itemNotes = ''): void {
    const existingIndex = items.value.findIndex(
      i => i.menu_id === menu.id && i.notes === itemNotes
    );
    
    const price = menu.price || 0;
    
    if (existingIndex >= 0) {
      const existingItem = items.value[existingIndex];
      if (existingItem) existingItem.qty += qty;
    } else {
      items.value.push({
        localId: generateUUID(),
        id: generateUUID(),
        order_id: '',
        order_batch_id: '',
        menu_id: menu.id,
        menu_name_snapshot: menu.name,
        price_snapshot: price,
        qty,
        notes: itemNotes || null,
        status: 'active',
        voided_by: null,
        void_reason: null,
      } as CartItem);
    }
    
    saveToStorage();
  }

  function updateQty(localId: string, qty: number): void {
    const item = items.value.find(i => i.localId === localId);
    if (item) {
      if (qty <= 0) {
        removeItem(localId);
      } else {
        item.qty = qty;
        saveToStorage();
      }
    }
  }

  function removeItem(localId: string): void {
    const index = items.value.findIndex(i => i.localId === localId);
    if (index >= 0) {
      items.value.splice(index, 1);
      saveToStorage();
    }
  }

  function updateNotes(localId: string, itemNotes: string): void {
    const item = items.value.find(i => i.localId === localId);
    if (item) {
      item.notes = itemNotes || null;
      saveToStorage();
    }
  }

  function setDiscount(amount: number): void {
    discountTotal.value = Math.max(0, Math.min(amount, subtotal.value));
    saveToStorage();
  }

  function applyDiscountPercent(percent: number): void {
    const amount = subtotal.value * (percent / 100);
    setDiscount(amount);
  }

  function clearCart(): void {
    items.value = [];
    discountTotal.value = 0;
    notes.value = '';
    saveToStorage();
  }

  function setItems(newItems: CartItem[]): void {
    items.value = newItems;
    saveToStorage();
  }

  function loadFromStorage(): void {
    try {
      const stored = localStorage.getItem('irestora_customer_cart');
      if (stored) {
        const data = JSON.parse(stored);
        items.value = data.items || [];
        discountTotal.value = data.discountTotal || 0;
        notes.value = data.notes || '';
      }
    } catch {
      // Ignore parse errors
    }
  }

  function saveToStorage(): void {
    try {
      localStorage.setItem('irestora_customer_cart', JSON.stringify({
        items: items.value,
        discountTotal: discountTotal.value,
        notes: notes.value,
      }));
    } catch {
      // Ignore quota exceeded
    }
  }

  function getItemsForSubmit(): Array<{
    menuId: string;
    qty: number;
    notes: string | null;
  }> {
    return items.value.map(item => ({
      menuId: item.menu_id,
      qty: item.qty,
      notes: item.notes,
    }));
  }

  return {
    items,
    discountTotal,
    notes,
    outletConfig,
    subtotal,
    itemCount,
    totals,
    grandTotal,
    setOutletConfig,
    addItem,
    updateQty,
    removeItem,
    updateNotes,
    setDiscount,
    applyDiscountPercent,
    clearCart,
    setItems,
    loadFromStorage,
    saveToStorage,
    getItemsForSubmit,
  };
});