<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function daily(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'outlet_id' => ['nullable', 'uuid', 'exists:outlets,id'],
        ]);

        $user = $request->user();
        $date = $data['date'] ?? now()->toDateString();
        $dateFrom = $data['date_from'] ?? $date;
        $dateTo = $data['date_to'] ?? $date;
        $outletId = $user->role->name === 'admin' ? ($data['outlet_id'] ?? null) : $user->outlet_id;

        $orders = Order::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($outletId, fn ($query) => $query->where('outlet_id', $outletId))
            ->get(['id', 'status', 'subtotal', 'discount_total', 'grand_total']);

        $paidOrders = $orders->where('status', 'paid');
        $payments = Payment::whereIn('order_id', $paidOrders->pluck('id'))->get();

        return ApiResponse::success('Daily report retrieved', [
            'date' => $date,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'outletId' => $outletId,
            'orderCount' => $orders->count(),
            'paidOrderCount' => $paidOrders->count(),
            'voidOrderCount' => $orders->where('status', 'void')->count(),
            'grossSales' => (float) $paidOrders->sum('subtotal'),
            'discountTotal' => (float) $paidOrders->sum('discount_total'),
            'netSales' => (float) $paidOrders->sum('grand_total'),
            'paymentTotals' => $payments->groupBy('method')->map(fn ($items) => (float) $items->sum('amount')),
        ]);
    }
}