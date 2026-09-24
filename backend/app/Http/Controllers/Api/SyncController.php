<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sync\SyncOrdersRequest;
use App\Models\Order;
use App\Models\OrderBatch;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Outlet;
use App\Models\Shift;
use App\Services\OrderCalculator;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Sync",
 *     description="Offline sync endpoint untuk Kasir PWA - lihat 02-SDD.md §4.2-4.3"
 * )
 */
class SyncController extends Controller
{
    public function __construct(private OrderCalculator $orderCalculator) {}

    /**
     * @OA\Post(
     *     path="/api/orders/sync",
     *     tags={"Sync"},
     *     summary="Kirim batch transaksi dari kasir app (offline sync)",
     *     description="Endpoint idempotent — jika `id` (UUID) transaksi sudah pernah diterima sebelumnya, server mengabaikan duplikat dan mengembalikan status 'already_synced' tanpa membuat data baru.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="orders",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/OrderSyncPayload")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Batch processed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="string", format="uuid"),
     *                     @OA\Property(property="status", type="string", enum={"synced", "already_synced", "conflict", "rejected"}),
     *                     @OA\Property(property="reason", type="string", nullable=true)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function syncOrders(SyncOrdersRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $results = [];

        foreach ($validated['orders'] as $orderData) {
            $result = $this->processOrder($orderData);
            $results[] = $result;
        }

        return ApiResponse::success('Batch processed', $results);
    }

    /**
     * Process a single order from sync payload
     */
    protected function processOrder(array $orderData): array
    {
        $orderId = $orderData['id'];
        $outletId = $orderData['outletId'];

        // Check if already synced (idempotent)
        $existingOrder = Order::where('id', $orderId)->first();
        if ($existingOrder) {
            if ($existingOrder->status === 'open') {
                try {
                    $result = DB::transaction(function () use ($existingOrder, $orderData) {
                        return $this->updateOpenOrderFromSync($existingOrder, $orderData);
                    });

                    return $result;
                } catch (\Exception $e) {
                    return [
                        'id' => $orderId,
                        'status' => 'rejected',
                        'reason' => $e->getMessage(),
                    ];
                }
            }

            return [
                'id' => $orderId,
                'status' => 'already_synced',
                'reason' => 'Order sudah pernah disinkronkan',
            ];
        }

        try {
            $result = DB::transaction(function () use ($orderData, $outletId) {
                return $this->createOrderFromSync($orderData, $outletId);
            });

            return $result;
        } catch (\Exception $e) {
            return [
                'id' => $orderId,
                'status' => 'rejected',
                'reason' => $e->getMessage(),
            ];
        }
    }

    protected function updateOpenOrderFromSync(Order $order, array $data): array
    {
        $outlet = Outlet::findOrFail($order->outlet_id);
        $calculated = $this->orderCalculator->calculate(
            $data['items'],
            $data['discountTotal'] ?? 0,
            $outlet
        );

        $mismatches = $this->validateCalculations($data, $calculated);
        if (! empty($mismatches)) {
            return [
                'id' => $order->id,
                'status' => 'conflict',
                'reason' => 'Perhitungan tidak cocok: ' . implode(', ', $mismatches),
                'server_calculated' => $calculated,
            ];
        }

        $order->update([
            'subtotal' => $calculated['subtotal'],
            'discount_total' => $calculated['discountTotal'],
            'service_charge_total' => $calculated['serviceChargeTotal'],
            'pb1_total' => $calculated['pb1Total'],
            'rounding_adjustment' => $calculated['roundingAdjustment'],
            'grand_total' => $calculated['grandTotal'],
            'sync_status' => 'synced',
        ]);

        $order->items()->delete();
        $batch = $this->createCashierBatch($order);

        // Create order items
        foreach ($data['items'] as $itemData) {
            OrderItem::create([
                'order_id' => $order->id,
                'order_batch_id' => $itemData['batchId'] ?? $batch->id,
                'menu_id' => $itemData['menuId'],
                'menu_name_snapshot' => $itemData['menuNameSnapshot'],
                'price_snapshot' => $itemData['priceSnapshot'],
                'qty' => $itemData['qty'],
                'notes' => $itemData['notes'] ?? null,
                'status' => 'active',
            ]);
        }

        return [
            'id' => $order->id,
            'status' => 'synced',
            'reason' => null,
        ];
    }

    /**
     * Create order from sync data
     */
    protected function createOrderFromSync(array $data, string $outletId): array
    {
        $orderId = $data['id'];

        // Validate outlet exists
        $outlet = Outlet::findOrFail($outletId);

        // Validate shift exists
        $shift = Shift::findOrFail($data['shiftId']);

        // Validate table if dine_in
        $table = null;
        if ($data['orderType'] === 'dine_in') {
            $table = \App\Models\DiningTable::where('id', $data['tableId'])
                ->where('outlet_id', $outletId)
                ->firstOrFail();

            if ($table->status !== 'available') {
                throw new \Exception('Meja tidak tersedia');
            }
        }

        // Validate calculations using OrderCalculator
        $calculated = $this->orderCalculator->calculate(
            $data['items'],
            $data['discountTotal'] ?? 0,
            $outlet
        );

        // Check for calculation mismatch (conflict detection)
        $mismatches = $this->validateCalculations($data, $calculated);
        if (! empty($mismatches)) {
            return [
                'id' => $orderId,
                'status' => 'conflict',
                'reason' => 'Perhitungan tidak cocok: ' . implode(', ', $mismatches),
                'server_calculated' => $calculated,
            ];
        }

        // Create order
        $order = Order::create([
            'id' => $orderId,
            'outlet_id' => $outletId,
            'order_type' => $data['orderType'],
            'table_id' => $table?->id,
            'shift_id' => $data['shiftId'],
            'cashier_id' => $data['cashierId'] ?? $data['shiftId'], // fallback
            'order_number' => $data['orderNumber'],
            'status' => empty($data['payments'] ?? []) ? 'open' : 'paid',
            'source' => $data['source'] ?? 'cashier',
            'device_id' => $data['deviceId'],
            'created_at_client' => $data['createdAtClient'],
            'synced_at' => now(),
            'sync_status' => 'synced',
            'subtotal' => $calculated['subtotal'],
            'discount_total' => $calculated['discountTotal'],
            'service_charge_total' => $calculated['serviceChargeTotal'],
            'pb1_total' => $calculated['pb1Total'],
            'rounding_adjustment' => $calculated['roundingAdjustment'],
            'grand_total' => $calculated['grandTotal'],
        ]);

        $batch = $this->createCashierBatch($order);

        // Create order items
        foreach ($data['items'] as $itemData) {
            OrderItem::create([
                'order_id' => $order->id,
                'order_batch_id' => $itemData['batchId'] ?? $batch->id,
                'menu_id' => $itemData['menuId'],
                'menu_name_snapshot' => $itemData['menuNameSnapshot'],
                'price_snapshot' => $itemData['priceSnapshot'],
                'qty' => $itemData['qty'],
                'notes' => $itemData['notes'] ?? null,
                'status' => 'active',
            ]);
        }

        // Create payments
        foreach ($data['payments'] ?? [] as $paymentData) {
            Payment::create([
                'order_id' => $order->id,
                'method' => $paymentData['method'],
                'amount' => $paymentData['amount'],
                'reference_number' => $paymentData['referenceNumber'] ?? null,
            ]);
        }

        // Update table status if dine_in
        if ($table) {
            $table->update(['status' => 'occupied', 'current_order_id' => $order->id]);
        }

        return [
            'id' => $orderId,
            'status' => 'synced',
            'reason' => null,
        ];
    }

    private function createCashierBatch(Order $order): OrderBatch
    {
        $batchNumber = ((int) $order->batches()->max('batch_number')) + 1;

        return OrderBatch::create([
            'order_id' => $order->id,
            'batch_number' => $batchNumber,
            'source' => 'cashier',
            'status' => 'confirmed',
            'submitted_at' => now(),
            'confirmed_by' => $order->cashier_id,
            'confirmed_at' => now(),
        ]);
    }
    /**
     * Validate client calculations against server
     */
    protected function validateCalculations(array $clientData, array $serverCalculated): array
    {
        $mismatches = [];
        $tolerance = 0.01; // 1 cent tolerance

        $checkFields = [
            'subtotal' => 'subtotal',
            'discountTotal' => 'discountTotal',
            'serviceChargeTotal' => 'serviceChargeTotal',
            'pb1Total' => 'pb1Total',
            'grandTotal' => 'grandTotal',
            'roundingAdjustment' => 'roundingAdjustment',
        ];

        foreach ($checkFields as $clientKey => $serverKey) {
            $clientVal = $clientData[$clientKey] ?? 0;
            $serverVal = $serverCalculated[$serverKey] ?? 0;

            if (abs($clientVal - $serverVal) > $tolerance) {
                $mismatches[] = "$clientKey: client=$clientVal, server=$serverVal";
            }
        }

        return $mismatches;
    }
}
