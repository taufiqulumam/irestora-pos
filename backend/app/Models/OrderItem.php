<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id',
        'order_batch_id',
        'menu_id',
        'menu_name_snapshot',
        'price_snapshot',
        'qty',
        'notes',
        'status',
        'voided_by',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'price_snapshot' => 'decimal:2',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function batch()
    {
        return $this->belongsTo(OrderBatch::class, 'order_batch_id');
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }
}
