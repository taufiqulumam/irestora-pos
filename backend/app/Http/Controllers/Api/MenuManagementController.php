<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuPrice;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MenuManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureCanManage($request, 'menu.manage');

        return ApiResponse::success('Managed menus retrieved', Menu::with(['category', 'prices.outlet'])
            ->where('is_active', true)->orderBy('name')->get());
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $this->ensureCanManage($request, 'menu.manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'sortOrder' => ['nullable', 'integer', 'min:0']]);
        $category = Category::create(['name' => $data['name'], 'sort_order' => $data['sortOrder'] ?? 0]);
        $this->audit($request, 'create_category', $category->id, null, $category->only(['name', 'sort_order']));
        return ApiResponse::success('Category created', $category, 201);
    }

    public function updateCategory(Request $request, Category $category): JsonResponse
    {
        $this->ensureCanManage($request, 'menu.manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'sortOrder' => ['nullable', 'integer', 'min:0']]);
        $before = $category->only(['name', 'sort_order']);
        $category->update(['name' => $data['name'], 'sort_order' => $data['sortOrder'] ?? 0]);
        $this->audit($request, 'update_category', $category->id, $before, $category->only(['name', 'sort_order']));
        return ApiResponse::success('Category updated', $category);
    }

    public function destroyCategory(Request $request, Category $category): JsonResponse
    {
        $this->ensureCanManage($request, 'menu.manage');
        if ($category->menus()->exists()) return ApiResponse::error('Kategori yang masih memiliki menu tidak dapat dihapus.', null, 422);
        $before = $category->only(['name', 'sort_order']);
        $category->delete();
        $this->audit($request, 'delete_category', $category->id, $before, null);
        return ApiResponse::success('Category deleted');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categoryId' => ['required', 'uuid', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'imageUrl' => ['nullable', 'url', 'max:2048'],
            'isActive' => ['boolean'],
        ]);

        $this->ensureCanManage($request, 'menu.manage');
        $menu = Menu::create([
            'category_id' => $data['categoryId'], 'name' => $data['name'],
            'description' => $data['description'] ?? null, 'image_url' => $data['imageUrl'] ?? null,
            'is_active' => $data['isActive'] ?? true,
        ]);

        $this->audit($request, 'create_menu', $menu->id, null, $menu->only(['name', 'category_id', 'is_active']));
        return ApiResponse::success('Menu created', $menu->load('category'), 201);
    }

    public function update(Request $request, Menu $menu): JsonResponse
    {
        $this->ensureCanManage($request, 'menu.manage');
        $data = $request->validate([
            'categoryId' => ['required', 'uuid', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'], 'imageUrl' => ['nullable', 'url', 'max:2048'],
            'isActive' => ['required', 'boolean'],
        ]);
        $before = $menu->only(['name', 'category_id', 'description', 'image_url', 'is_active']);
        $menu->update(['category_id' => $data['categoryId'], 'name' => $data['name'], 'description' => $data['description'] ?? null, 'image_url' => $data['imageUrl'] ?? null, 'is_active' => $data['isActive']]);
        $this->audit($request, 'update_menu', $menu->id, $before, $menu->only(['name', 'category_id', 'description', 'image_url', 'is_active']));
        return ApiResponse::success('Menu updated', $menu->load('category'));
    }

    public function setPrice(Request $request, Menu $menu): JsonResponse
    {
        $this->ensureCanManage($request, 'menu.price.edit');
        $data = $request->validate([
            'outletId' => ['required', 'uuid', 'exists:outlets,id'],
            'price' => ['required', 'numeric', 'min:0'], 'isActive' => ['boolean'],
        ]);
        $this->ensureOutletScope($request, $data['outletId']);
        $price = MenuPrice::firstOrNew(['menu_id' => $menu->id, 'outlet_id' => $data['outletId']]);
        $before = $price->exists ? $price->only(['price', 'is_active']) : null;
        $price->fill(['price' => $data['price'], 'is_active' => $data['isActive'] ?? true])->save();
        $this->audit($request, 'edit_price', $price->id, $before, $price->only(['price', 'is_active']), $data['outletId']);
        return ApiResponse::success('Menu price updated', $price);
    }

    public function categories(Request $request): JsonResponse
    {
        $this->ensureCanManage($request, 'menu.manage');
        return ApiResponse::success('Categories retrieved', Category::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'sort_order']));
    }

    private function ensureCanManage(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermissionTo($permission), 403, 'Anda tidak memiliki izin mengelola menu.');
    }

    private function ensureOutletScope(Request $request, string $outletId): void
    {
        abort_unless($request->user()->role->name === 'admin' || $request->user()->outlet_id === $outletId, 403, 'Manager hanya dapat mengubah harga outlet sendiri.');
    }

    private function audit(Request $request, string $action, string $targetId, ?array $before, ?array $after, ?string $outletId = null): void
    {
        $auditOutletId = $outletId ?: $request->user()->outlet_id ?: \App\Models\Outlet::where('is_active', true)->value('id');
        AuditLog::create(['outlet_id' => $auditOutletId, 'user_id' => $request->user()->id, 'action' => $action, 'target_type' => 'menus', 'target_id' => $targetId, 'before_value' => $before, 'after_value' => $after, 'device_id' => $request->header('X-Device-Id', 'admin-panel'), 'ip_address' => $request->ip()]);
    }
}