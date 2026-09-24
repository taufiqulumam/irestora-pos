import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { generateUUID } from '@shared/utils';
import type { OrderItem, Menu, CalculatedTotals, OutletTaxConfig } from '@shared/types';
import { calculateOrderTotals } from '@shared/calculator';

export const useCartStore = defineStore('cart', () => {
  const items = ref<OrderItem[]>([]);
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
      } as OrderItem);
    }
  }

  function updateQty(itemId: string, qty: number): void {
    const item = items.value.find(i => i.id === itemId);
    if (item) {
      if (qty <= 0) {
        removeItem(itemId);
      } else {
        item.qty = qty;
      }
    }
  }

  function removeItem(itemId: string): void {
    const index = items.value.findIndex(i => i.id === itemId);
    if (index >= 0) {
      items.value.splice(index, 1);
    }
  }

  function updateNotes(itemId: string, itemNotes: string): void {
    const item = items.value.find(i => i.id === itemId);
    if (item) {
      item.notes = itemNotes || null;
    }
  }

  function setDiscount(amount: number): void {
    discountTotal.value = Math.max(0, Math.min(amount, subtotal.value));
  }

  function applyDiscountPercent(percent: number): void {
    const amount = subtotal.value * (percent / 100);
    setDiscount(amount);
  }

  function clearCart(): void {
    items.value = [];
    discountTotal.value = 0;
    notes.value = '';
  }

  function setItems(newItems: OrderItem[]): void {
    items.value = newItems;
  }

  function getItemsForSync(): Array<{
    menuId: string;
    menuNameSnapshot: string;
    priceSnapshot: number;
    qty: number;
    notes: string | null;
  }> {
    return items.value.map(item => ({
      menuId: item.menu_id,
      menuNameSnapshot: item.menu_name_snapshot,
      priceSnapshot: item.price_snapshot,
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
    getItemsForSync,
  };
});