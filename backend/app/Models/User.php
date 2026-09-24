<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'outlet_id',
        'role_id',
        'email',
        'password',
        'pin_hash',
        'full_name',
        'phone',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'pin_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'last_login_at'  => 'datetime',
            'password'       => 'hashed',
        ];
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function hasPermissionTo(string $permission): bool
    {
        if (! $this->role) {
            return false;
        }

        $permissions = $this->role->relationLoaded('permissions')
            ? $this->role->permissions
            : $this->role->permissions()->get();

        return $permissions->contains('code', $permission);
    }
}
