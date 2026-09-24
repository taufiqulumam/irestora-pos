<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasUuids;

    protected $fillable = [
        'outlet_id',
        'order_type',
        'table_id',
        'shift_id',
        'cashier_id',
        'order_number',
        'status',
        'subtotal',
        'discount_total',
        'service_charge_total',
        'pb1_total',
        'rounding_adjustment',
        'grand_total',
        'source',
        'device_id',
        'created_at_client',
        'synced_at',
        'sync_status',
    ];

    protected function casts(): array
    {
        return [
            'created_at_client' => 'datetime',
            'synced_at'          => 'datetime',
            'subtotal'           => 'decimal:2',
            'discount_total'     => 'decimal:2',
            'service_charge_total' => 'decimal:2',
            'pb1_total'          => 'decimal:2',
            'rounding_adjustment' => 'decimal:2',
            'grand_total'        => 'decimal:2',
        ];
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function table()
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    public function batches()
    {
        return $this->hasMany(OrderBatch::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
