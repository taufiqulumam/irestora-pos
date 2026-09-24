<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! $request->user()?->hasPermissionTo($permission)) {
            return ApiResponse::error('Anda tidak memiliki izin untuk melakukan aksi ini.', null, 403);
        }

        return $next($request);
    }
}