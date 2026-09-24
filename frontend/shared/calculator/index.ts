import { roundToNearestHundred, calculateRoundingAdjustment } from '../utils';

export interface OutletTaxConfig {
  pb1Rate: number; // percentage, e.g., 10 for 10%
  serviceChargeRate: number; // percentage, e.g., 5 for 5%
  roundingEnabled: boolean;
}

export interface OrderItemInput {
  priceSnapshot: number;
  qty: number;
}

export interface CalculatedTotals {
  subtotal: number;
  discountTotal: number;
  afterDiscount: number;
  serviceChargeTotal: number;
  pb1Base: number;
  pb1Total: number;
  grandTotalRaw: number;
  grandTotal: number;
  roundingAdjustment: number;
}

export interface OrderCalculatorResult extends CalculatedTotals {
  items: Array<OrderItemInput & { lineTotal: number }>;
}

/**
 * Port of PHP OrderCalculator service to TypeScript.
 * Must produce IDENTICAL results to backend for offline/online consistency.
 * Formula per ERD §4.2:
 * 1. subtotal = Σ (price_snapshot × qty) for active items
 * 2. after_diskon = subtotal − discount_total
 * 3. service_charge_total = after_diskon × service_charge_rate
 * 4. dasar_pengenaan_pb1 = after_diskon + service_charge_total
 * 5. pb1_total = dasar_pengenaan_pb1 × pb1_rate
 * 6. grand_total_raw = after_diskon + service_charge_total + pb1_total
 * 7. grand_total = ROUND(grand_total_raw to nearest 100) if rounding_enabled
 * 8. rounding_adjustment = grand_total − grand_total_raw
 */
export function calculateOrderTotals(
  items: OrderItemInput[],
  discountTotal: number,
  outletConfig: OutletTaxConfig
): CalculatedTotals {
  // 1. subtotal
  const subtotal = items.reduce((sum, item) => sum + item.priceSnapshot * item.qty, 0);

  // 2. after_diskon
  const afterDiscount = subtotal - discountTotal;

  // 3. service_charge_total
  const serviceChargeTotal = afterDiscount * (outletConfig.serviceChargeRate / 100);

  // 4. dasar_pengenaan_pb1
  const pb1Base = afterDiscount + serviceChargeTotal;

  // 5. pb1_total
  const pb1Total = pb1Base * (outletConfig.pb1Rate / 100);

  // 6. grand_total_raw
  const grandTotalRaw = afterDiscount + serviceChargeTotal + pb1Total;

  // 7. grand_total (rounded)
  const grandTotal = roundToNearestHundred(grandTotalRaw, outletConfig.roundingEnabled);

  // 8. rounding_adjustment
  const roundingAdjustment = calculateRoundingAdjustment(grandTotalRaw, grandTotal);

  return {
    subtotal,
    discountTotal,
    afterDiscount,
    serviceChargeTotal,
    pb1Base,
    pb1Total,
    grandTotalRaw,
    grandTotal,
    roundingAdjustment,
  };
}

export function calculateOrderWithItems(
  items: OrderItemInput[],
  discountTotal: number,
  outletConfig: OutletTaxConfig
): OrderCalculatorResult {
  const itemsWithLineTotal = items.map((item) => ({
    ...item,
    lineTotal: item.priceSnapshot * item.qty,
  }));

  const totals = calculateOrderTotals(items, discountTotal, outletConfig);

  return {
    ...totals,
    items: itemsWithLineTotal,
  };
}

/**
 * Validate that client-calculated totals match server-calculated totals.
 * Used during sync to detect manipulation or bugs.
 */
export function validateCalculatedTotals(
  clientTotals: Partial<CalculatedTotals>,
  serverTotals: CalculatedTotals,
  tolerance = 0.01 // 1 cent tolerance for floating point
): { valid: boolean; mismatches: string[] } {
  const mismatches: string[] = [];

  const checkFields: Array<keyof CalculatedTotals> = [
    'subtotal',
    'discountTotal',
    'serviceChargeTotal',
    'pb1Total',
    'grandTotal',
    'roundingAdjustment',
  ];

  for (const field of checkFields) {
    const clientVal = clientTotals[field];
    const serverVal = serverTotals[field];

    if (clientVal !== undefined && Math.abs(clientVal - serverVal) > tolerance) {
      mismatches.push(`${field}: client=${clientVal}, server=${serverVal}`);
    }
  }

  return {
    valid: mismatches.length === 0,
    mismatches,
  };
}

/**
 * Default tax config for testing (Jakarta: PB1 10%, Service 5%)
 */
export const DEFAULT_TAX_CONFIG: OutletTaxConfig = {
  pb1Rate: 10,
  serviceChargeRate: 5,
  roundingEnabled: true,
};

/**
 * Example calculation from ERD §4.3:
 * Subtotal: 500,000
 * Discount (10%): -50,000
 * After discount: 450,000
 * Service charge (5%): 22,500
 * PB1 base: 472,500
 * PB1 (10%): 47,250
 * Grand total raw: 519,750
 * Grand total (rounded to 100): 519,800
 * Rounding adjustment: +50
 */
export const ERD_EXAMPLE = {
  items: [
    { priceSnapshot: 500000, qty: 1 },
  ],
  discountTotal: 50000,
  config: DEFAULT_TAX_CONFIG,
  expected: {
    subtotal: 500000,
    discountTotal: 50000,
    afterDiscount: 450000,
    serviceChargeTotal: 22500,
    pb1Base: 472500,
    pb1Total: 47250,
    grandTotalRaw: 519750,
    grandTotal: 519800,
    roundingAdjustment: 50,
  },
};