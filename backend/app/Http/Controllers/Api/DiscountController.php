<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderCalculator;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class DiscountController extends Controller
{
    public function apply(Request $request, string $orderId, OrderCalculator $calculator): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'discountTotal' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
            'approvedByPin' => ['nullable', 'string', 'digits_between:4,6'],
        ])->validate();

        $user = $request->user();
        $query = Order::with(['items', 'outlet'])->where('id', $orderId);
        if ($user->role->name !== 'admin') {
            $query->where('outlet_id', $user->outlet_id);
        }
        $order = $query->firstOrFail();

        if ($order->status !== 'open') {
            return ApiResponse::error('Diskon hanya dapat diterapkan pada order yang masih open.', null, 422);
        }

        $items = $order->items()->where('status', 'active')->get();
        $current = $calculator->calculateFromOrder($items, (float) $order->discount_total, $order->outlet);
        $discount = (float) $data['discountTotal'];
        $maxDiscount = $current['subtotal'] * 0.10;

        if ($discount > $current['subtotal']) {
            return ApiResponse::error('Diskon tidak boleh melebihi subtotal.', null, 422);
        }

        $approver = null;
        if ($discount > $maxDiscount) {
            $approver = $this->findApprover($order->outlet_id, $data['approvedByPin'] ?? null);
            if (! $approver) {
                return ApiResponse::error('Diskon di atas 10% membutuhkan PIN approval supervisor atau manager.', null, 403);
            }
        }

        $totals = $calculator->calculateFromOrder($items, $discount, $order->outlet);
        $before = ['discount_total' => (float) $order->discount_total, 'grand_total' => (float) $order->grand_total];

        Order::withoutEvents(function () use ($order, $totals): void {
            $order->update([
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discountTotal'],
                'service_charge_total' => $totals['serviceChargeTotal'],
                'pb1_total' => $totals['pb1Total'],
                'rounding_adjustment' => $totals['roundingAdjustment'],
                'grand_total' => $totals['grandTotal'],
            ]);
        });

        AuditLog::create([
            'outlet_id' => $order->outlet_id,
            'user_id' => $user->id,
            'action' => 'apply_discount',
            'target_type' => 'orders',
            'target_id' => $order->id,
            'before_value' => $before,
            'after_value' => ['discount_total' => $discount, 'grand_total' => $totals['grandTotal']],
            'reason' => $data['reason'],
            'approved_by' => $approver?->id,
            'device_id' => $request->header('X-Device-Id', 'cashier-app'),
            'ip_address' => $request->ip(),
        ]);

        return ApiResponse::success('Diskon berhasil diterapkan', [
            'orderId' => $order->id,
            'discountTotal' => $discount,
            'grandTotal' => $totals['grandTotal'],
            'approvedBy' => $approver?->id,
        ]);
    }

    private function findApprover(string $outletId, ?string $pin): ?User
    {
        if (! $pin) return null;

        $approvers = User::where('outlet_id', $outletId)
            ->whereIn('role_id', fn ($query) => $query->select('id')->from('roles')->whereIn('name', ['supervisor', 'manager']))
            ->where('is_active', true)
            ->get();

        foreach ($approvers as $approver) {
            if (Hash::check($pin, $approver->pin_hash) && $approver->hasPermissionTo('discount.approve')) return $approver;
        }

        return null;
    }
}