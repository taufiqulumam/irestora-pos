<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FraudAlert\ReviewFraudAlertRequest;
use App\Models\FraudAlert;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Fraud",
 *     description="Fraud alert management - lihat 02-SDD.md §5.2"
 * )
 */
class FraudAlertController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/fraud-alerts",
     *     tags={"Fraud"},
     *     summary="List fraud alerts (Admin Panel dashboard)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="outlet_id",
     *         in="query",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         @OA\Schema(type="string", enum={"open","reviewed","dismissed"})
     *     ),
     *     @OA\Parameter(
     *         name="rule_code",
     *         in="query",
     *         @OA\Schema(type="string", enum={"high_void_rate","high_discount","cash_mismatch","sequence_gap"})
     *     ),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = FraudAlert::with(['outlet', 'shift', 'user', 'reviewedBy'])
            ->latest('created_at');

        if ($user->role->name !== 'admin') {
            $query->where('outlet_id', $user->outlet_id);
        } elseif ($request->filled('outlet_id')) {
            $query->where('outlet_id', $request->outlet_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('rule_code')) {
            $query->where('rule_code', $request->rule_code);
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        $alerts = $query->paginate(20);

        return ApiResponse::success('Fraud alerts retrieved', $alerts);
    }

    /**
     * @OA\Post(
     *     path="/api/fraud-alerts/{alertId}/review",
     *     tags={"Fraud"},
     *     summary="Tandai fraud alert sebagai reviewed/dismissed",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", enum={"reviewed","dismissed"}),
     *             @OA\Property(property="notes", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function review(ReviewFraudAlertRequest $request, string $alertId): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $query = FraudAlert::where('id', $alertId);
        if ($user->role->name !== 'admin') {
            $query->where('outlet_id', $user->outlet_id);
        }
        $alert = $query->firstOrFail();

        $alert->update([
            'status' => $validated['status'],
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        return ApiResponse::success('Fraud alert ' . $validated['status'], $alert);
    }
}