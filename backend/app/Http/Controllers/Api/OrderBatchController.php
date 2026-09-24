<?php

namespace App\Http\Controllers\Api;

use App\Events\OrderBatchStatusChanged;
use App\Events\OrderBatchSubmitted;
use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderBatch;
use App\Models\OrderItem;
use App\Models\Shift;
use App\Services\OrderCalculator;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Orders",
 *     description="Cart submission (customer) & batch confirmation (kasir) - lihat 02-SDD.md §4.6"
 * )
 */
class OrderBatchController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/tables/{tableId}/cart/submit",
     *     tags={"Orders"},
     *     summary="Submit cart customer sebagai Batch pesanan baru (order_batch)",
     *     @OA\Response(response=201, description="Batch dibuat dengan status pending_confirmation"),
     *     @OA\Response(response=404, description="Meja tidak ditemukan / QR token tidak valid"),
     *     @OA\Response(response=409, description="Outlet belum memiliki shift aktif, order tidak dapat dibuat")
     * )
     */
    public function submitCart(Request $request, string $tableId): JsonResponse
    {
        $validated = $request->validate([
            'items'             => ['required', 'array', 'min:1'],
            'items.*.menuId'    => ['required', 'uuid', 'exists:menus,id'],
            'items.*.qty'       => ['required', 'integer', 'min:1'],
            'items.*.notes'     => ['nullable', 'string'],
        ]);

        $table = DiningTable::findOrFail($tableId);

        // Lihat 02-SDD.md §4.7.2: kalau tidak ada shift aktif, order DITOLAK,
        // bukan dibuat menggantung tanpa shift_id/cashier_id.
        $activeShift = Shift::activeFor($table->outlet_id);

        if (! $activeShift) {
            return ApiResponse::error(
                'Outlet belum menerima pesanan saat ini, silakan hubungi staff.',
                null,
                409
            );
        }

        $batch = DB::transaction(function () use ($table, $validated, $activeShift, $request) {
            // Row-lock meja untuk hindari race condition dengan kasir yang mungkin
            // sedang membuka order di meja yang sama secara bersamaan.
            $lockedTable = DiningTable::where('id', $table->id)->lockForUpdate()->first();

            // Cari order dine_in yang masih 'open' di meja ini, atau buat baru
            $order = Order::where('table_id', $lockedTable->id)
                ->where('status', 'open')
                ->latest('created_at')
                ->first();

            if (! $order) {
                $order = Order::create([
                    'outlet_id'         => $lockedTable->outlet_id,
                    'order_type'        => 'dine_in', // self-order selalu dine_in - terikat ke meja
                    'table_id'          => $lockedTable->id,
                    'shift_id'          => $activeShift->id,
                    'cashier_id'        => $activeShift->opened_by,
                    'order_number'      => now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -6)),
                    'status'            => 'open',
                    'source'            => 'customer_self_order',
                    'device_id'         => $request->header('X-Device-Id', 'customer-web'),
                    'created_at_client' => now(),
                    'sync_status'       => 'synced', // customer app selalu online, tidak lewat alur offline-sync kasir
                ]);

                // Konsisten dengan alur kasir (02-SDD.md §4.7.1) - meja jadi occupied
                // begitu order pertama dibuka, baik dari kasir maupun self-order.
                $lockedTable->update(['status' => 'occupied', 'current_order_id' => $order->id]);
            }

            $nextBatchNumber = $order->batches()->max('batch_number') + 1;

            $batch = OrderBatch::create([
                'order_id'     => $order->id,
                'batch_number' => $nextBatchNumber,
                'source'       => 'customer_self_order',
                'status'       => 'pending_confirmation',
                'submitted_at' => now(),
            ]);

            foreach ($validated['items'] as $item) {
                $menu = \App\Models\Menu::findOrFail($item['menuId']);

                OrderItem::create([
                    'order_id'           => $order->id,
                    'order_batch_id'     => $batch->id,
                    'menu_id'            => $menu->id,
                    'menu_name_snapshot' => $menu->name,
                    'price_snapshot'     => $menu->priceForOutlet($table->outlet_id) ?? 0,
                    'qty'                => $item['qty'],
                    'notes'              => $item['notes'] ?? null,
                    'status'             => 'active',
                ]);
            }

            $order->loadMissing('outlet');
            $this->recalculateOrderTotals($order);

            return $batch;
        });

        broadcast(new OrderBatchSubmitted($batch))->toOthers();

        return ApiResponse::success(
            'Order batch submitted, menunggu konfirmasi kasir',
            [
                'id' => $batch->id,
                'batchId' => $batch->id,
                'order_id' => $batch->order_id,
                'orderId' => $batch->order_id,
                'batch_number' => $batch->batch_number,
                'source' => $batch->source,
                'status' => $batch->status,
                'submitted_at' => $batch->submitted_at,
                'confirmed_by' => $batch->confirmed_by,
                'confirmed_at' => $batch->confirmed_at,
            ],
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/orders/{orderId}/batches",
     *     tags={"Orders"},
     *     summary="Get all batches for an order",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="orderId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="List of batches with items"),
     *     @OA\Response(response=404, description="Order not found")
     * )
     */
    public function batches(Request $request, string $orderId): JsonResponse
    {
        $user = $request->user();
        
        $order = Order::where('outlet_id', $user->outlet_id)
            ->where('id', $orderId)
            ->firstOrFail();

        $batches = $order->batches()
            ->with(['items.menu'])
            ->orderBy('batch_number')
            ->get();

        return ApiResponse::success('Batches retrieved', $batches);
    }

    /**
     * @OA\Post(
     *     path="/api/order-batches/{batchId}/confirm",
     *     tags={"Orders"},
     *     summary="Kasir mengkonfirmasi atau menolak Batch pesanan dari customer",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Batch dikonfirmasi/ditolak")
     * )
     */
    public function confirm(Request $request, string $batchId): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:confirm,reject'],
            'reason' => ['nullable', 'string'],
        ]);

        $batch = OrderBatch::findOrFail($batchId);

        $batch->update([
            'status'       => $validated['action'] === 'confirm' ? 'confirmed' : 'rejected',
            'confirmed_by' => $request->user()->id,
            'confirmed_at' => now(),
        ]);

        $batch->loadMissing('items.menu', 'order');

        if ($validated['action'] === 'confirm') {
            foreach ($batch->items as $item) {
                $item->update(['status' => 'active']);
            }
        } else {
            foreach ($batch->items as $item) {
                $item->update([
                    'status' => 'voided',
                    'voided_by' => $request->user()->id,
                    'void_reason' => $validated['reason'] ?? 'Pesanan ditolak kasir',
                ]);
            }
        }

        $this->recalculateOrderTotals($batch->order);

        broadcast(new OrderBatchStatusChanged($batch))->toOthers();

        return ApiResponse::success('Batch ' . $batch->status, ['batchId' => $batch->id]);
    }

    /**
     * Recalculate order totals from items
     */
    protected function recalculateOrderTotals(Order $order): void
    {
        $order->loadMissing('outlet');
        $items = $order->items()->where('status', 'active')->get();
        $totals = app(OrderCalculator::class)->calculateFromOrder(
            $items,
            (float) $order->discount_total,
            $order->outlet
        );

        $order->update([
            'subtotal' => $totals['subtotal'],
            'discount_total' => $totals['discountTotal'],
            'service_charge_total' => $totals['serviceChargeTotal'],
            'pb1_total' => $totals['pb1Total'],
            'rounding_adjustment' => $totals['roundingAdjustment'],
            'grand_total' => $totals['grandTotal'],
        ]);
    }
}
