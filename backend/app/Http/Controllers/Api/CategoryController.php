<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Categories",
 *     description="Manajemen kategori menu"
 * )
 */
class CategoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/categories",
     *     tags={"Categories"},
     *     summary="List categories for an outlet",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="outlet_id",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="List of categories")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'outlet_id' => ['required', 'uuid', 'exists:outlets,id'],
        ]);

        $categories = Category::with(['menus' => function ($query) use ($validated) {
            $query->where('is_active', true)
                ->whereHas('prices', function ($q) use ($validated) {
                    $q->where('outlet_id', $validated['outlet_id'])
                        ->where('is_active', true);
                });
        }])
            ->whereHas('menus.prices', function ($query) use ($validated) {
                $query->where('outlet_id', $validated['outlet_id'])
                    ->where('is_active', true);
            })
            ->orderBy('sort_order')
            ->get();

        return ApiResponse::success('Categories retrieved', $categories);
    }
}