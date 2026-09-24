<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk tabel `tables` (meja). Nama class sengaja "DiningTable",
 * bukan "Table", untuk menghindari ambiguitas dengan istilah umum lain.
 */
class DiningTable extends Model
{
    use HasUuids;

    protected $table = 'tables';

    protected $fillable = [
        'outlet_id',
        'name',
        'qr_code_token',
        'status',
        'current_order_id',
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'table_id');
    }

    public function currentOrder()
    {
        return $this->belongsTo(Order::class, 'current_order_id');
    }
}
