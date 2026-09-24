<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Outlet extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'code',
        'address',
        'pb1_rate',
        'service_charge_rate',
        'rounding_enabled',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'pb1_rate'             => 'decimal:4',
            'service_charge_rate'  => 'decimal:4',
            'rounding_enabled'     => 'boolean',
            'is_active'            => 'boolean',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
