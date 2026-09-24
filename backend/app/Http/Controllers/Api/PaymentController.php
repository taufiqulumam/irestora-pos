<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\VoidOrderRequest;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shift;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Payments",
 *     description="Payment processing and order voiding"
 * )
 */
class PaymentController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/orders/{orderId}/payments",
     *     tags={"Payments"},
     *     summary="Tambah pembayaran ke order",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"method", "amount"},
     *             @OA\Property(property="method", type="string", enum={"cash","qris","card","other"}),
     *             @OA\Property(property="amount", type="number", format="float"),
     *             @OA\Property(property="reference_number", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Pembayaran ditambahkan, order status diupdate ke paid jika lunas")
     * )
     */
    public function store(StorePaymentRequest $request, string $orderId): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $query = Order::where('id', $orderId);
        
        // Admin can access all outlets, others only their own
        if ($user->role->name !== 'admin') {
            $query->where('outlet_id', $user->outlet_id);
        }
        
        $order = $query->firstOrFail();

        if ($order->status !== 'open') {
            return ApiResponse::error('Order tidak bisa dibayar (status: ' . $order->status . ')', null, 422);
        }

        $payment = DB::transaction(function () use ($order, $validated, $user, $request) {
            $payment = Payment::create([
                'order_id' => $order->id,
                'method' => $validated['method'],
                'amount' => $validated['amount'],
                'reference_number' => $validated['reference_number'] ?? null,
            ]);

            // Check if order is fully paid
            $totalPaid = $order->payments()->sum('amount');
            
            if ($totalPaid >= $order->grand_total) {
                $order->update(['status' => 'paid']);
                
                // Log audit
                AuditLog::create([
                    'outlet_id' => $order->outlet_id,
                    'user_id' => $user->id,
                    'action' => 'payment_completed',
                    'target_type' => 'orders',
                    'target_id' => $order->id,
                    'before_value' => ['status' => 'open', 'paid_amount' => $totalPaid - $validated['amount']],
                    'after_value' => ['status' => 'paid', 'paid_amount' => $totalPaid],
                    'device_id' => $request->header('X-Device-Id', 'cashier-app'),
                    'ip_address' => $request->ip(),
                ]);
            }

            return $payment;
        });

        return ApiResponse::success('Pembayaran ditambahkan', $payment, 201);
    }

    /**
     * @OA\Post(
     *     path="/api/orders/{orderId}/void",
     *     tags={"Payments"},
     *     summary="Void/batal order (butuh approval PIN supervisor)",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reason", "approved_by_pin"},
     *             @OA\Property(property="reason", type="string"),
     *             @OA\Property(property="approved_by_pin", type="string", description="PIN supervisor/manager")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Order dibatalkan, tercatat di audit_logs"),
     *     @OA\Response(response=403, description="PIN approval tidak valid / tidak memiliki izin")
     * )
     */
    public function void(VoidOrderRequest $request, string $orderId): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $query = Order::where('id', $orderId);
        
        // Admin can access all outlets, others only their own
        if ($user->role->name !== 'admin') {
            $query->where('outlet_id', $user->outlet_id);
        }
        
        $order = $query->firstOrFail();

        if ($order->status !== 'open') {
            return ApiResponse::error('Order tidak bisa dibatalkan (status: ' . $order->status . ')', null, 422);
        }

        // Verify supervisor/manager PIN
        $approver = $this->verifyApprovalPin($user->outlet_id, $validated['approved_by_pin']);
        
        if (! $approver) {
            return ApiResponse::error('PIN approval tidak valid atau tidak memiliki izin void', null, 403);
        }

        if (! $approver->hasPermissionTo('order.void.approve')) {
            return ApiResponse::error('User tidak memiliki izin approve void', null, 403);
        }

        $beforeValue = [
            'status' => $order->status,
            'grand_total' => $order->grand_total,
        ];

        $order->update(['status' => 'void']);

        // Log audit
        AuditLog::create([
            'outlet_id'   => $order->outlet_id,
            'user_id'     => $user->id,
            'action'      => 'void_order',
            'target_type' => 'orders',
            'target_id'   => $order->id,
            'before_value' => $beforeValue,
            'after_value'  => ['status' => 'void'],
            'reason'      => $validated['reason'],
            'approved_by' => $approver->id,
            'device_id'   => $request->header('X-Device-Id', 'cashier-app'),
            'ip_address'  => $request->ip(),
        ]);

        return ApiResponse::success('Order dibatalkan', ['orderId' => $order->id, 'status' => 'void']);
    }

    /**
     * Verify PIN for supervisor/manager approval
     */
    protected function verifyApprovalPin(string $outletId, string $pin): ?\App\Models\User
    {
        $users = \App\Models\User::where('outlet_id', $outletId)
            ->whereIn('role_id', function ($query) {
                $query->select('id')->from('roles')->whereIn('name', ['supervisor', 'manager']);
            })
            ->where('is_active', true)
            ->get();

        foreach ($users as $u) {
            if (\Illuminate\Support\Facades\Hash::check($pin, $u->pin_hash)) {
                return $u;
            }
        }

        return null;
    }
}