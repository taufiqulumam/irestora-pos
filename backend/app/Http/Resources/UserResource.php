<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bentuk output user, dibuat mendekati contoh schema pada Swagger:
 * id, roleName, fullName, email, phone, isActive, lastLoginAt,
 * createdAt, updatedAt, role { id, name }, outlet { id, name }
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'outlet_id'   => $this->outlet_id,
            'role_id'     => $this->role_id,
            'roleName'    => $this->role?->name,
            'role_name'   => $this->role?->name,
            'fullName'    => $this->full_name,
            'full_name'   => $this->full_name,
            'email'       => $this->email,
            'phone'       => $this->phone,
            'isActive'    => (bool) $this->is_active,
            'is_active'   => (bool) $this->is_active,
            'lastLoginAt' => $this->last_login_at?->toISOString(),
            'last_login_at' => $this->last_login_at?->toISOString(),
            'createdAt'   => $this->created_at?->toISOString(),
            'created_at'  => $this->created_at?->toISOString(),
            'updatedAt'   => $this->updated_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
            'role' => $this->whenLoaded('role', fn () => [
                'id'   => $this->role->id,
                'name' => $this->role->name,
            ]),
            'outlet' => $this->whenLoaded('outlet', fn () => [
                'id'   => $this->outlet?->id,
                'name' => $this->outlet?->name,
            ]),
        ];
    }
}
