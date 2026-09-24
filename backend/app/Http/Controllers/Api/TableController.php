<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DiningTable;
use App\Models\Shift;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Tables",
 *     description="Manajemen status meja - lihat 02-SDD.md §4.7.1"
 * )
 */
class TableController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/tables",
     *     tags={"Tables"},
     *     summary="List tables for an outlet",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="outlet_id",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="List of tables")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'outlet_id' => ['required', 'uuid', 'exists:outlets,id'],
        ]);

        $tables = DiningTable::with(['currentOrder' => function ($query) {
                $query->with(['items', 'cashier'])
                    ->whereIn('status', ['open', 'paid']);
            }])
            ->where('outlet_id', $validated['outlet_id'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'outlet_id', 'name', 'qr_code_token', 'status', 'current_order_id'])
            ->map(function (DiningTable $table) {
                $currentOrder = $table->currentOrder ? [
                    'id' => $table->currentOrder->id,
                    'outlet_id' => $table->currentOrder->outlet_id,
                    'order_type' => $table->currentOrder->order_type,
                    'table_id' => $table->currentOrder->table_id,
                    'shift_id' => $table->currentOrder->shift_id,
                    'cashier_id' => $table->currentOrder->cashier_id,
                    'order_number' => $table->currentOrder->order_number,
                    'status' => $table->currentOrder->status,
                    'subtotal' => $table->currentOrder->subtotal,
                    'discount_total' => $table->currentOrder->discount_total,
                    'service_charge_total' => $table->currentOrder->service_charge_total,
                    'pb1_total' => $table->currentOrder->pb1_total,
                    'rounding_adjustment' => $table->currentOrder->rounding_adjustment,
                    'grand_total' => $table->currentOrder->grand_total,
                    'source' => $table->currentOrder->source,
                    'device_id' => $table->currentOrder->device_id,
                    'created_at_client' => $table->currentOrder->created_at_client,
                    'synced_at' => $table->currentOrder->synced_at,
                    'sync_status' => $table->currentOrder->sync_status,
                    'created_at' => $table->currentOrder->created_at,
                    'updated_at' => $table->currentOrder->updated_at,
                    'items' => $table->currentOrder->items,
                    'cashier' => $table->currentOrder->cashier,
                ] : null;

                return [
                    'id' => $table->id,
                    'outlet_id' => $table->outlet_id,
                    'name' => $table->name,
                    'qr_code_token' => $table->qr_code_token,
                    'status' => $table->status,
                    'current_order_id' => $table->current_order_id,
                    'currentOrder' => $currentOrder,
                    'current_order' => $currentOrder,
                ];
            });

        return ApiResponse::success('Tables retrieved', $tables);
    }

    /**
     * @OA\Get(
     *     path="/api/tables/{tableId}",
     *     tags={"Tables"},
     *     summary="Get table detail (public - for QR code access)",
     *     description="Mengambil detail meja berdasarkan ID atau QR code token. Dipakai Customer App saat scan QR code.",
     *     @OA\Parameter(
     *         name="tableId",
     *         in="path",
     *         required=true,
     *         description="Table UUID atau QR code token",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Table detail"),
     *     @OA\Response(response=404, description="Meja tidak ditemukan")
     * )
     */
    public function show(string $tableId): JsonResponse
    {
        // Coba cari by UUID dulu, kalau tidak ketemu cari by qr_code_token
        $table = DiningTable::where('id', $tableId)
            ->orWhere('qr_code_token', $tableId)
            ->with(['outlet', 'currentOrder'])
            ->firstOrFail();

        return ApiResponse::success('Table detail', [
            'id' => $table->id,
            'outlet_id' => $table->outlet_id,
            'name' => $table->name,
            'qr_code_token' => $table->qr_code_token,
            'status' => $table->status,
            'outlet' => $table->outlet ? [
                'id' => $table->outlet->id,
                'name' => $table->outlet->name,
                'code' => $table->outlet->code,
            ] : null,
            'current_order' => $table->currentOrder ? [
                'id' => $table->currentOrder->id,
                'order_number' => $table->currentOrder->order_number,
                'status' => $table->currentOrder->status,
                'grand_total' => $table->currentOrder->grand_total,
            ] : null,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/tables/{tableId}/close",
     *     tags={"Tables"},
     *     summary="Tutup meja secara manual (kasir) - meja kembali berstatus available",
     *     description="Hanya bisa dilakukan jika order aktif di meja tsb sudah berstatus paid. Bisa dilakukan kasir manapun yang sedang shift aktif di outlet tersebut (tidak harus kasir yang membuka order). Aksi ini tercatat di audit_logs.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", nullable=true, description="Catatan opsional, misal 'meja sudah dibersihkan'")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Meja kembali berstatus available"),
     *     @OA\Response(response=404, description="Meja tidak ditemukan"),
     *     @OA\Response(response=422, description="Meja tidak punya order aktif, atau order belum lunas (status bukan paid)"),
     *     @OA\Response(response=409, description="Anda belum membuka shift")
     * )
     */
    public function close(Request $request, string $tableId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        // Keputusan: kasir MANAPUN yang sedang shift aktif di outlet tsb boleh
        // menutup meja (tidak harus kasir yang membuka order tsb).
        $activeShift = Shift::activeFor($user->outlet_id);

        if (! $activeShift) {
            return ApiResponse::error('Anda belum membuka shift. Buka shift terlebih dahulu.', null, 409);
        }

        $table = DiningTable::where('id', $tableId)->where('outlet_id', $user->outlet_id)->firstOrFail();

        DB::transaction(function () use ($table, $user, $validated, $request) {
            // Row-lock agar tidak ada race condition dengan order baru yang mungkin
            // sedang dibuka di meja yang sama.
            $lockedTable = DiningTable::where('id', $table->id)->lockForUpdate()->first();
            $lockedTable->loadMissing('currentOrder');

            $order = $lockedTable->currentOrder;

            if (! $order) {
                abort(422, 'Meja ini tidak memiliki order aktif untuk ditutup.');
            }

            // Keputusan: HANYA boleh ditutup jika order sudah lunas (status paid).
            if ($order->status !== 'paid') {
                abort(422, "Order di meja ini belum lunas (status saat ini: {$order->status}). Meja tidak bisa ditutup.");
            }

            $beforeValue = [
                'table_status' => $lockedTable->status,
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
            ];

            $lockedTable->update(['status' => 'available', 'current_order_id' => null]);

            // Keputusan: aksi ini WAJIB tercatat di audit_logs untuk fraud tracking.
            AuditLog::create([
                'outlet_id'   => $lockedTable->outlet_id,
                'user_id'     => $user->id,
                'action'      => 'close_table',
                'target_type' => 'tables',
                'target_id'   => $lockedTable->id,
                'before_value' => $beforeValue,
                'after_value'  => ['table_status' => 'available', 'order_id' => null],
                'reason'      => $validated['reason'] ?? null,
                'device_id'   => $request->header('X-Device-Id', 'cashier-app'),
                'ip_address'  => $request->ip(),
            ]);
        });

        return ApiResponse::success('Meja berhasil ditutup, status kembali available', [
            'tableId' => $table->id,
            'status'  => 'available',
        ]);
    }
}
