<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuPrice;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Menus",
 *     description="Menu management - lihat 02-SDD.md §4.5"
 * )
 */
class MenuController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/menus",
     *     tags={"Menus"},
     *     summary="Get menu list with prices for a given outlet",
     *     description="Digunakan kasir app untuk refresh cache lokal (IndexedDB) saat online.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="outlet_id",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="OK"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'outlet_id' => ['required', 'uuid', 'exists:outlets,id'],
        ]);

        $user = $request->user();
        if ($user && $user->role->name !== 'admin' && $validated['outlet_id'] !== $user->outlet_id) {
            return ApiResponse::error('Anda hanya dapat mengakses menu outlet sendiri.', null, 403);
        }

        $menus = Menu::with(['category', 'prices' => function ($query) use ($validated) {
            $query->where('outlet_id', $validated['outlet_id'])
                ->where('is_active', true);
        }])
            ->where('is_active', true)
            ->whereHas('prices', function ($query) use ($validated) {
                $query->where('outlet_id', $validated['outlet_id'])
                    ->where('is_active', true);
            })
            ->orderBy('category_id')
            ->orderBy('name')
            ->get()
            ->map(function ($menu) use ($validated) {
                $price = $menu->prices->first()?->price ?? 0;
                return [
                    'id' => $menu->id,
                    'category_id' => $menu->category_id,
                    'categoryId' => $menu->category_id,
                    'category_name' => $menu->category?->name,
                    'categoryName' => $menu->category?->name,
                    'name' => $menu->name,
                    'description' => $menu->description,
                    'image_url' => $menu->image_url,
                    'price' => $price,
                    'is_active' => $menu->is_active,
                    'isActive' => $menu->is_active,
                ];
            });

        return ApiResponse::success('Menu list retrieved', $menus);
    }
}
