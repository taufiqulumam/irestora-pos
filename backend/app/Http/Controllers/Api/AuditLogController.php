<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'action' => ['nullable', 'string', 'max:100'],
            'outlet_id' => ['nullable', 'uuid', 'exists:outlets,id'],
        ]);

        $user = $request->user();
        $query = AuditLog::with(['user:id,full_name', 'outlet:id,name'])
            ->when($user->role->name !== 'admin', fn ($query) => $query->where('outlet_id', $user->outlet_id))
            ->when($user->role->name === 'admin' && ! empty($data['outlet_id']), fn ($query) => $query->where('outlet_id', $data['outlet_id']))
            ->when(! empty($data['date_from']), fn ($query) => $query->whereDate('created_at', '>=', $data['date_from']))
            ->when(! empty($data['date_to']), fn ($query) => $query->whereDate('created_at', '<=', $data['date_to']))
            ->when(! empty($data['action']), fn ($query) => $query->where('action', $data['action']))
            ->latest('created_at');

        return ApiResponse::success('Audit logs retrieved', $query->paginate(30));
    }
}