<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shift\OpenShiftRequest;
use App\Http\Requests\Shift\CloseShiftRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\FraudAlert;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Shifts",
 *     description="Shift management - buka/tutup shift kasir"
 * )
 */
class ShiftController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/shifts/active",
     *     tags={"Shifts"},
     *     summary="Get active shift for an outlet",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="outlet_id",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Active shift or null"),
     *     @OA\Response(response=404, description="No active shift")
     * )
     */
    public function active(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'outlet_id' => ['required', 'uuid', 'exists:outlets,id'],
        ]);

        $user = $request->user();
        if ($user->role->name !== 'admin' && $validated['outlet_id'] !== $user->outlet_id) {
            return ApiResponse::error('Anda hanya dapat mengakses shift outlet sendiri.', null, 403);
        }

        $shift = Shift::activeFor($validated['outlet_id']);

        if (! $shift) {
            return ApiResponse::error('Tidak ada shift aktif', null, 404);
        }

        $shift->closing_cash_expected = $this->calculateExpectedClosingCash($shift);

        return ApiResponse::success('Active shift retrieved', $shift);
    }

    /**
     * @OA\Post(
     *     path="/api/shifts/open",
     *     tags={"Shifts"},
     *     summary="Buka shift kasir",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"outlet_id", "opening_cash"},
     *             @OA\Property(property="outlet_id", type="string", format="uuid"),
     *             @OA\Property(property="opening_cash", type="number", format="float")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Shift dibuka"),
     *     @OA\Response(response=409, description="Sudah ada shift aktif untuk outlet ini")
     * )
     */
    public function open(OpenShiftRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // Check if user already has an active shift
        $outletId = $validated['outlet_id'];
        if ($user->role->name !== 'admin' && $outletId !== $user->outlet_id) {
            return ApiResponse::error('Anda hanya dapat membuka shift outlet sendiri.', null, 403);
        }
        $existingShift = Shift::activeFor($outletId);
        if ($existingShift) {
            return ApiResponse::error('Sudah ada shift aktif untuk outlet ini', null, 409);
        }

        $shift = Shift::create([
            'outlet_id' => $outletId,
            'opened_by' => $user->id,
            'opening_cash' => $validated['opening_cash'],
            'opened_at' => now(),
        ]);

        return ApiResponse::success('Shift dibuka', $shift, 201);
    }

    /**
     * @OA\Post(
     *     path="/api/shifts/{shiftId}/close",
     *     tags={"Shifts"},
     *     summary="Tutup shift kasir, hitung selisih kas",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"closing_cash_actual"},
     *             @OA\Property(property="closing_cash_actual", type="number", format="float")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Shift ditutup"),
     *     @OA\Response(response=404, description="Shift tidak ditemukan"),
     *     @OA\Response(response=422, description="Shift sudah ditutup atau bukan milik user")
     * )
     */
    public function close(CloseShiftRequest $request, string $shiftId): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $query = Shift::where('id', $shiftId);
        
        // Admin can access all outlets, others only their own
        if ($user->role->name !== 'admin') {
            $query->where('outlet_id', $user->outlet_id);
        }
        
        $shift = $query->firstOrFail();

        $canCloseOwn = $shift->opened_by === $user->id && $user->hasPermissionTo('shift.close.own');
        $canCloseAny = $user->hasPermissionTo('shift.close.any');

        if (! $canCloseOwn && ! $canCloseAny) {
            return ApiResponse::error('Anda tidak memiliki izin untuk menutup shift ini.', null, 403);
        }

        if ($shift->closed_at) {
            return ApiResponse::error('Shift sudah ditutup', null, 422);
        }

        // Calculate expected closing cash from paid orders in this shift
        $expectedClosingCash = $this->calculateExpectedClosingCash($shift);

        $cashDifference = $validated['closing_cash_actual'] - $expectedClosingCash;

        $shift->update([
            'closed_by' => $user->id,
            'closing_cash_expected' => $expectedClosingCash,
            'closing_cash_actual' => $validated['closing_cash_actual'],
            'cash_difference' => $cashDifference,
            'closed_at' => now(),
        ]);

        // Trigger fraud alert if cash difference exceeds tolerance
        $tolerance = config('pos.cash_tolerance', 20000); // Default Rp 20,000
        if (abs($cashDifference) > $tolerance) {
            FraudAlert::create([
                'outlet_id' => $shift->outlet_id,
                'shift_id' => $shift->id,
                'user_id' => $user->id,
                'rule_code' => 'cash_mismatch',
                'severity' => abs($cashDifference) > $tolerance * 5 ? 'high' : 'medium',
                'details' => [
                    'expected' => $expectedClosingCash,
                    'actual' => $validated['closing_cash_actual'],
                    'difference' => $cashDifference,
                    'tolerance' => $tolerance,
                ],
            ]);
        }

        return ApiResponse::success('Shift ditutup', $shift);
    }

    /**
     * Calculate expected closing cash from paid orders in this shift
     */
    protected function calculateExpectedClosingCash(Shift $shift): float
    {
        // Get all paid orders in this shift
        $paidOrders = Order::where('shift_id', $shift->id)
            ->where('status', 'paid')
            ->get();

        $totalCash = 0;
        foreach ($paidOrders as $order) {
            // Sum only cash payments
            $cashPayments = $order->payments()->where('method', 'cash')->get();
            foreach ($cashPayments as $payment) {
                $totalCash += $payment->amount;
            }
        }

        return $shift->opening_cash + $totalCash;
    }
}