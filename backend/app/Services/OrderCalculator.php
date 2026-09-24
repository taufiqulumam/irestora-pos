<?php

namespace App\Services;

use App\Models\Outlet;
use Illuminate\Support\Collection;

/**
 * OrderCalculator - Single source of truth for PB1/Service Charge calculation
 * Used by both client (offline) and server (validation)
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
class OrderCalculator
{
    /**
     * Calculate order totals from items
     * 
     * @param array $items Array of ['priceSnapshot' => float, 'qty' => int]
     * @param float $discountTotal
     * @param Outlet $outlet
     * @return array Calculated totals
     */
    public function calculate(array $items, float $discountTotal, Outlet $outlet): array
    {
        // 1. subtotal
        $subtotal = collect($items)->sum(fn ($item) => $item['priceSnapshot'] * $item['qty']);

        // 2. after_diskon
        $afterDiscount = $subtotal - $discountTotal;

        // 3. service_charge_total
        $serviceChargeRate = $outlet->service_charge_rate ?? 0;
        $serviceChargeTotal = $afterDiscount * ($serviceChargeRate / 100);

        // 4. dasar_pengenaan_pb1
        $pb1Base = $afterDiscount + $serviceChargeTotal;

        // 5. pb1_total
        $pb1Rate = $outlet->pb1_rate ?? 0;
        $pb1Total = $pb1Base * ($pb1Rate / 100);

        // 6. grand_total_raw
        $grandTotalRaw = $afterDiscount + $serviceChargeTotal + $pb1Total;

        // 7. grand_total (rounded)
        $roundingEnabled = $outlet->rounding_enabled ?? true;
        $grandTotal = $this->roundToNearestHundred($grandTotalRaw, $roundingEnabled);

        // 8. rounding_adjustment
        $roundingAdjustment = $grandTotal - $grandTotalRaw;

        return [
            'subtotal' => $subtotal,
            'discountTotal' => $discountTotal,
            'afterDiscount' => $afterDiscount,
            'serviceChargeTotal' => $serviceChargeTotal,
            'pb1Base' => $pb1Base,
            'pb1Total' => $pb1Total,
            'grandTotalRaw' => $grandTotalRaw,
            'grandTotal' => $grandTotal,
            'roundingAdjustment' => $roundingAdjustment,
        ];
    }

    /**
     * Round to nearest 100 (Rp)
     */
    protected function roundToNearestHundred(float $amount, bool $enabled): float
    {
        if (! $enabled) {
            return $amount;
        }
        
        return round($amount / 100) * 100;
    }

    /**
     * Calculate from order items collection
     */
    public function calculateFromOrder(Collection $items, float $discountTotal, Outlet $outlet): array
    {
        $itemsArray = $items->map(fn ($item) => [
            'priceSnapshot' => (float) $item->price_snapshot,
            'qty' => (int) $item->qty,
        ])->toArray();

        return $this->calculate($itemsArray, $discountTotal, $outlet);
    }

    /**
     * Validate client calculations against server
     */
    public function validateCalculations(array $clientTotals, array $serverTotals, float $tolerance = 0.01): array
    {
        $mismatches = [];

        $checkFields = [
            'subtotal',
            'discountTotal',
            'serviceChargeTotal',
            'pb1Total',
            'grandTotal',
            'roundingAdjustment',
        ];

        foreach ($checkFields as $field) {
            $clientVal = $clientTotals[$field] ?? 0;
            $serverVal = $serverTotals[$field] ?? 0;

            if (abs($clientVal - $serverVal) > $tolerance) {
                $mismatches[] = "$field: client=$clientVal, server=$serverVal";
            }
        }

        return $mismatches;
    }

    /**
     * Get calculation breakdown for receipt
     */
    public function getBreakdown(array $items, float $discountTotal, Outlet $outlet): array
    {
        $totals = $this->calculate($items, $discountTotal, $outlet);
        
        return [
            'items' => collect($items)->map(fn ($item) => [
                'price_snapshot' => $item['priceSnapshot'],
                'qty' => $item['qty'],
                'line_total' => $item['priceSnapshot'] * $item['qty'],
            ])->toArray(),
            'subtotal' => $totals['subtotal'],
            'discount_total' => $totals['discountTotal'],
            'after_discount' => $totals['afterDiscount'],
            'service_charge_rate' => $outlet->service_charge_rate ?? 0,
            'service_charge_total' => $totals['serviceChargeTotal'],
            'pb1_rate' => $outlet->pb1_rate ?? 0,
            'pb1_base' => $totals['pb1Base'],
            'pb1_total' => $totals['pb1Total'],
            'grand_total_raw' => $totals['grandTotalRaw'],
            'rounding_enabled' => $outlet->rounding_enabled ?? true,
            'grand_total' => $totals['grandTotal'],
            'rounding_adjustment' => $totals['roundingAdjustment'],
        ];
    }
}