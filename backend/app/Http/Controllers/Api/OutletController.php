<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Outlets",
 *     description="Outlet management"
 * )
 */
class OutletController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/outlets",
     *     tags={"Outlets"},
     *     summary="List outlets accessible by the authenticated user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = Outlet::where('is_active', true);
        
        // Admin can see all outlets, others only their own
        if ($user->role->name !== 'admin') {
            $query->where('id', $user->outlet_id);
        }
        
        $outlets = $query->get(['id', 'name', 'code', 'address', 'pb1_rate', 'service_charge_rate', 'rounding_enabled']);
        
        return ApiResponse::success('Outlets retrieved', $outlets);
    }

    /**
     * @OA\Get(
     *     path="/api/outlets/{id}",
     *     tags={"Outlets"},
     *     summary="Get outlet detail",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Outlet detail"),
     *     @OA\Response(response=404, description="Outlet not found")
     * )
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        
        $query = Outlet::where('is_active', true);
        
        if ($user->role->name !== 'admin') {
            $query->where('id', $user->outlet_id);
        }
        
        $outlet = $query->where('id', $id)->firstOrFail();
        
        return ApiResponse::success('Outlet detail', $outlet);
    }
}