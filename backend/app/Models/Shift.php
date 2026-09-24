<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasUuids;

    protected $fillable = [
        'outlet_id',
        'opened_by',
        'closed_by',
        'opening_cash',
        'closing_cash_expected',
        'closing_cash_actual',
        'cash_difference',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => 'decimal:2',
            'closing_cash_expected' => 'decimal:2',
            'closing_cash_actual' => 'decimal:2',
            'cash_difference' => 'decimal:2',
        ];
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function openedBy()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * Cari shift yang sedang berjalan (belum ditutup) untuk sebuah outlet.
     * Dipakai saat customer self-order untuk menentukan shift_id & cashier_id
     * order secara otomatis - lihat 02-SDD.md §4.7.2.
     */
    public static function activeFor(string $outletId): ?self
    {
        return static::where('outlet_id', $outletId)
            ->whereNull('closed_at')
            ->latest('opened_at')
            ->first();
    }
}
