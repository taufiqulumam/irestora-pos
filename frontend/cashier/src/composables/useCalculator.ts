import { computed } from 'vue';
import { useCartStore } from '@/stores/cart';
import { useMenuStore } from '@/stores/menu';
import type { Menu, Order, OrderBatch, OrderItem, CalculatedTotals, OutletTaxConfig } from '@shared/types';
import { calculateOrderTotals, validateCalculatedTotals, calculateOrderWithItems } from '@shared/calculator';

export function useCalculator() {
  const cartStore = useCartStore();
  const menuStore = useMenuStore();

  const totals = computed(() => cartStore.totals);
  const grandTotal = computed(() => cartStore.grandTotal);
  const subtotal = computed(() => cartStore.subtotal);
  const itemCount = computed(() => cartStore.itemCount);

  function setOutletConfig(config: OutletTaxConfig): void {
    cartStore.setOutletConfig(config);
  }

  function addMenuItem(menu: Menu, qty = 1, notes = ''): void {
    cartStore.addItem(menu, qty, notes);
  }

  function updateItemQty(itemId: string, qty: number): void {
    cartStore.updateQty(itemId, qty);
  }

  function removeItem(itemId: string): void {
    cartStore.removeItem(itemId);
  }

  function updateItemNotes(itemId: string, notes: string): void {
    cartStore.updateNotes(itemId, notes);
  }

  function setDiscount(amount: number): void {
    cartStore.setDiscount(amount);
  }

  function applyDiscountPercent(percent: number): void {
    cartStore.applyDiscountPercent(percent);
  }

  function clearCalculator(): void {
    cartStore.clearCart();
  }

  function hydrateFromOrder(order: Order): void {
    const orderItems = order.items?.length
      ? order.items
      : (order.batches || []).flatMap((batch: OrderBatch) => batch.items || []);

    cartStore.setItems(
      orderItems
        .filter(item => item.status === 'active')
        .map(item => ({
          ...item,
          price_snapshot: Number(item.price_snapshot) || 0,
          qty: Number(item.qty) || 0,
          notes: item.notes || null,
        }))
    );

    cartStore.setDiscount(Number(order.discount_total) || 0);

    const outlet = (order as any).outlet;
    if (outlet) {
      cartStore.setOutletConfig({
        serviceChargeRate: Number(outlet.service_charge_rate) || 0,
        pb1Rate: Number(outlet.pb1_rate) || 0,
        roundingEnabled: outlet.rounding_enabled ?? true,
      });
    }
  }

  function getItemsForOrder(): Omit<OrderItem, 'created_at' | 'updated_at'>[] {
    return cartStore.items.map(item => ({
      id: item.id,
      order_id: '',
      order_batch_id: '',
      menu_id: item.menu_id,
      menu_name_snapshot: item.menu_name_snapshot,
      price_snapshot: item.price_snapshot,
      qty: item.qty,
      notes: item.notes,
      status: 'active',
      voided_by: null,
      void_reason: null,
    }));
  }

  function getCalculationForSync(): CalculatedTotals & { items: any[] } {
    return calculateOrderWithItems(
      cartStore.items.map(i => ({ priceSnapshot: i.price_snapshot, qty: i.qty })),
      cartStore.discountTotal,
      cartStore.outletConfig
    );
  }

  function validateTotals(serverTotals: CalculatedTotals): { valid: boolean; mismatches: string[] } {
    return validateCalculatedTotals(cartStore.totals, serverTotals);
  }

  return {
    totals,
    grandTotal,
    subtotal,
    itemCount,
    items: computed(() => cartStore.items),
    discountTotal: computed(() => cartStore.discountTotal),
    notes: computed(() => cartStore.notes),
    setOutletConfig,
    addMenuItem,
    updateItemQty,
    removeItem,
    updateItemNotes,
    setDiscount,
    applyDiscountPercent,
    clearCalculator,
    hydrateFromOrder,
    getItemsForOrder,
    getCalculationForSync,
    validateTotals,
  };
}
