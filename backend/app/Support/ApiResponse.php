<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Helper untuk membentuk response JSON yang konsisten di seluruh API.
 * Formatnya sengaja dibuat sama seperti response yang kamu lihat di
 * Swagger UI: success, message, data, statusCode.
 */
class ApiResponse
{
    public static function success(string $message, mixed $data = null, int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success'    => true,
            'message'    => $message,
            'data'       => $data,
            'statusCode' => $statusCode,
        ], $statusCode);
    }

    public static function error(string $message, mixed $data = null, int $statusCode = 400): JsonResponse
    {
        return response()->json([
            'success'    => false,
            'message'    => $message,
            'data'       => $data,
            'statusCode' => $statusCode,
        ], $statusCode);
    }
}
