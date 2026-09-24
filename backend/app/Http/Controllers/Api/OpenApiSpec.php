<?php

namespace App\Http\Controllers\Api;

/**
 * File khusus untuk menampung anotasi global OpenAPI.
 * Tidak berisi route/logic apa pun — hanya dibaca oleh l5-swagger
 * saat generate dokumentasi (php artisan l5-swagger:generate).
 *
 * @OA\Info(
 *     title="iRestora POS API",
 *     version="1.0.0",
 *     description="API Documentation untuk Sistem POS F&B iRestora"
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="API Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 *
 * @OA\Schema(
 *     schema="OrderSyncPayload",
 *     required={"id", "outletId", "orderType", "shiftId", "items", "createdAtClient"},
 *     @OA\Property(property="id", type="string", format="uuid", description="UUID di-generate client"),
 *     @OA\Property(property="outletId", type="string", format="uuid"),
 *     @OA\Property(property="orderType", type="string", enum={"dine_in", "takeaway"}),
 *     @OA\Property(property="tableId", type="string", format="uuid", nullable=true, description="Wajib diisi jika orderType=dine_in, wajib null jika takeaway"),
 *     @OA\Property(property="shiftId", type="string", format="uuid"),
 *     @OA\Property(property="orderNumber", type="string"),
 *     @OA\Property(property="cashierId", type="string", format="uuid", nullable=true),
 *     @OA\Property(property="deviceId", type="string"),
 *     @OA\Property(property="createdAtClient", type="string", format="date-time"),
 *     @OA\Property(property="subtotal", type="number", description="Dihitung client, divalidasi ulang oleh server"),
 *     @OA\Property(property="discountTotal", type="number"),
 *     @OA\Property(property="serviceChargeTotal", type="number", description="Dihitung dari (subtotal - discountTotal) x outlet.serviceChargeRate"),
 *     @OA\Property(property="pb1Total", type="number", description="PBJT/PB1 (pajak restoran daerah, bukan PPN)"),
 *     @OA\Property(property="roundingAdjustment", type="number"),
 *     @OA\Property(property="grandTotal", type="number"),
 *     @OA\Property(
 *         property="items",
 *         type="array",
 *         @OA\Items(
 *             @OA\Property(property="menuId", type="string", format="uuid"),
 *             @OA\Property(property="menuNameSnapshot", type="string"),
 *             @OA\Property(property="priceSnapshot", type="number"),
 *             @OA\Property(property="qty", type="integer"),
 *             @OA\Property(property="notes", type="string", nullable=true),
 *             @OA\Property(property="batchId", type="string", format="uuid", nullable=true)
 *         )
 *     ),
 *     @OA\Property(
 *         property="payments",
 *         type="array",
 *         @OA\Items(
 *             @OA\Property(property="method", type="string", enum={"cash", "qris", "card", "other"}),
 *             @OA\Property(property="amount", type="number"),
 *             @OA\Property(property="referenceNumber", type="string", nullable=true)
 *         )
 *     )
 * )
 */
class OpenApiSpec
{
    //
}