<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\DiscountController;
use App\Http\Controllers\Api\FraudAlertController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\MenuManagementController;
use App\Http\Controllers\Api\OrderBatchController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OutletController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    // Butuh Bearer token (Sanctum)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

// Customer App - publik, tidak butuh login (akses via qr_code_token meja)
Route::get('tables/{tableId}/order', [OrderController::class, 'activeForTable']);
Route::get('tables/{tableId}', [TableController::class, 'show']);
Route::post('tables/{tableId}/cart/submit', [OrderBatchController::class, 'submitCart']);
Route::get('menus', [MenuController::class, 'index']);
Route::get('categories', [CategoryController::class, 'index']);

// Outlets
Route::middleware('auth:sanctum')->group(function () {
    Route::get('outlets', [OutletController::class, 'index']);
    Route::get('outlets/{id}', [OutletController::class, 'show']);
});

// Tables
Route::middleware('auth:sanctum')->group(function () {
    Route::get('tables', [TableController::class, 'index']);
});

// Menu & Category - untuk refresh cache kasir
Route::middleware('auth:sanctum')->group(function () {
    Route::get('menu-categories', [MenuManagementController::class, 'categories'])->middleware('permission:menu.manage');
    Route::get('managed-menus', [MenuManagementController::class, 'index'])->middleware('permission:menu.manage');
    Route::post('categories', [MenuManagementController::class, 'storeCategory'])->middleware('permission:menu.manage');
    Route::put('categories/{category}', [MenuManagementController::class, 'updateCategory'])->middleware('permission:menu.manage');
    Route::delete('categories/{category}', [MenuManagementController::class, 'destroyCategory'])->middleware('permission:menu.manage');
    Route::post('menus', [MenuManagementController::class, 'store'])->middleware('permission:menu.manage');
    Route::put('menus/{menu}', [MenuManagementController::class, 'update'])->middleware('permission:menu.manage');
    Route::put('menus/{menu}/price', [MenuManagementController::class, 'setPrice'])->middleware('permission:menu.price.edit');
});

// Kasir - butuh Bearer token (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('users', [UserController::class, 'index']);
    Route::get('roles', [UserController::class, 'roles']);
    Route::post('users', [UserController::class, 'store']);
    Route::put('users/{user}', [UserController::class, 'update']);

    // Orders
    Route::post('orders', [OrderController::class, 'open'])->middleware('permission:order.create'); // pilih dine_in/takeaway
    Route::get('orders', [OrderController::class, 'index']); // list orders untuk Admin Panel / laporan
    Route::get('reports/daily', [ReportController::class, 'daily'])->middleware('permission:report.daily');
    Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit.view');
    Route::get('orders/{orderId}', [OrderController::class, 'show']); // detail order
    Route::get('orders/{orderId}/batches', [OrderBatchController::class, 'batches']); // batches untuk order
    
    // Order Batches
    Route::post('order-batches/{batchId}/confirm', [OrderBatchController::class, 'confirm']);
    
    // Payments
    Route::post('orders/{orderId}/payments', [PaymentController::class, 'store'])->middleware('permission:payment.create');
    Route::post('orders/{orderId}/discount', [DiscountController::class, 'apply'])->middleware('permission:discount.apply');
    Route::post('orders/{orderId}/void', [PaymentController::class, 'void'])->middleware('permission:order.void.own');
    
    // Tables
    Route::post('tables/{tableId}/close', [TableController::class, 'close']); // tutup meja manual
    
    // Shifts
    Route::get('shifts/active', [ShiftController::class, 'active']);
    Route::post('shifts/open', [ShiftController::class, 'open'])->middleware('permission:shift.open');
    Route::post('shifts/{shiftId}/close', [ShiftController::class, 'close']);
    
    // Fraud Alerts
    Route::get('fraud-alerts', [FraudAlertController::class, 'index'])->middleware('permission:fraud.review');
    Route::post('fraud-alerts/{alertId}/review', [FraudAlertController::class, 'review'])->middleware('permission:fraud.review');
    
    // Sync (Offline-first)
    Route::post('orders/sync', [SyncController::class, 'syncOrders'])->middleware('permission:order.create');
});
