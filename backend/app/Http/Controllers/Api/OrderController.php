<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\OpenOrderRequest;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Shift;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Orders",
 *     description="Buka order baru dari Kasir App - dine-in atau takeaway, lihat 02-SDD.md §4.7"
 * )
 */
class OrderController extends Controller
{
    /**
     * Get active order for a public customer table page.
     */
    public function activeForTable(string $tableId): JsonResponse
    {
        $table = DiningTable::where('id', $tableId)
            ->orWhere('qr_code_token', $tableId)
            ->firstOrFail();

        $order = Order::with(['table', 'outlet', 'batches.items.menu', 'items.menu'])
            ->where('table_id', $table->id)
            ->whereIn('status', ['open', 'paid'])
            ->latest('created_at')
            ->first();

        if (! $order) {
            return ApiResponse::success('No active order', null);
        }

        return ApiResponse::success('Active order retrieved', $order);
    }

    /**
     * @OA\Get(
     *     path="/api/orders",
     *     tags={"Orders"},
     *     summary="List orders (untuk Admin Panel / laporan)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="outlet_id",
     *         in="query",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="table_id",
     *         in="query",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         @OA\Schema(type="string", enum={"open","paid","void","cancelled"})
     *     ),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Order::with(['table', 'cashier', 'shift', 'payments', 'batches.items.menu'])
            ->latest('created_at');

        if ($user->role->name !== 'admin') {
            $query->where('outlet_id', $user->outlet_id);

            if ($user->role->name === 'cashier') {
                $query->where('cashier_id', $user->id);
            }
        } elseif ($request->filled('outlet_id')) {
            $query->where('outlet_id', $request->outlet_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('table_id')) {
            $query->where('table_id', $request->table_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->paginate(20);

        return ApiResponse::success('Orders retrieved', $orders);
    }

    /**
     * @OA\Get(
     *     path="/api/orders/{orderId}",
     *     tags={"Orders"},
     *     summary="Get order detail with items and payments",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="orderId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Order detail"),
     *     @OA\Response(response=404, description="Order not found")
     * )
     */
    public function show(Request $request, string $orderId): JsonResponse
    {
        $user = $request->user();

        $query = Order::with(['table', 'outlet', 'cashier', 'shift', 'payments', 'batches.items.menu', 'items.menu'])
            ->where('id', $orderId);

        if ($user->role->name !== 'admin') {
            $query->where('outlet_id', $user->outlet_id);
        }

        $order = $query->firstOrFail();

        return ApiResponse::success('Order detail', $order);
    }

    /**
     * @OA\Post(
     *     path="/api/orders",
     *     tags={"Orders"},
     *     summary="Buka order baru (kasir) - pilih dine_in atau takeaway",
     *     description="Kasir wajib menentukan orderType terlebih dahulu. tableId wajib diisi jika dine_in (harus berstatus available), dan wajib kosong jika takeaway.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"orderType"},
     *             @OA\Property(property="orderType", type="string", enum={"dine_in","takeaway"}),
     *             @OA\Property(property="tableId", type="string", format="uuid", nullable=true, description="Wajib diisi jika orderType=dine_in, wajib null jika takeaway. Harus berstatus available.")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Order dibuka, status open; jika dine_in, meja otomatis berubah status jadi occupied"),
     *     @OA\Response(response=422, description="tableId tidak konsisten dengan orderType, atau meja tidak berstatus available"),
     *     @OA\Response(response=409, description="Kasir belum membuka shift")
     * )
     */
    public function open(OpenOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // Kasir wajib sudah membuka shift sebelum bisa buka order - lihat 02-SDD.md §4.7.2
        $activeShift = Shift::activeFor($user->outlet_id);

        if (! $activeShift) {
            return ApiResponse::error('Anda belum membuka shift. Buka shift terlebih dahulu.', null, 409);
        }

        $order = DB::transaction(function () use ($validated, $user, $activeShift, $request) {
            $table = null;

            if ($validated['orderType'] === 'dine_in') {
                // Row-lock meja saat pengecekan status, agar dua kasir tidak bisa
                // dapat meja available yang sama secara bersamaan (race condition).
                $table = DiningTable::where('id', $validated['tableId'])
                    ->where('outlet_id', $user->outlet_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($table->status !== 'available') {
                    // Sudah divalidasi di OpenOrderRequest, tapi dicek ulang di
                    // dalam transaksi terkunci untuk keamanan dari race condition.
                    abort(422, "Meja sudah tidak tersedia (status saat ini: {$table->status}).");
                }
            }

            $order = Order::create([
                'outlet_id'         => $user->outlet_id,
                'order_type'        => $validated['orderType'],
                'table_id'          => $table?->id,
                'shift_id'          => $activeShift->id,
                'cashier_id'        => $user->id,
                'order_number'      => now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -6)),
                'status'            => 'open',
                'source'            => 'cashier',
                'device_id'         => $request->header('X-Device-Id', 'cashier-app'),
                'created_at_client' => now(),
                'sync_status'       => 'synced',
            ]);

            // Meja langsung ditandai occupied begitu order dibuka - lihat 02-SDD.md §4.7.1
            if ($table) {
                $updatedTables = DiningTable::whereKey($table->id)->update([
                    'status' => 'occupied',
                    'current_order_id' => $order->id,
                    'updated_at' => now(),
                ]);

                if ($updatedTables !== 1) {
                    throw new \RuntimeException('Status meja gagal diperbarui saat membuka order.');
                }
            }

            return $order;
        });

        $tableState = $order->table_id
            ? DiningTable::find($order->table_id)
            : null;

        return ApiResponse::success(
            'Order dibuka',
            [
                'orderId'   => $order->id,
                'orderType' => $order->order_type,
                'tableId'   => $order->table_id,
                'tableStatus' => $tableState?->status,
                'currentOrderId' => $tableState?->current_order_id,
            ],
            201
        );
    }
}
