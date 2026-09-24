<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only. TIDAK ADA endpoint API yang boleh update/delete baris di
 * tabel ini - lihat 03-ERD.md §5.1 dan migration audit_and_fraud_tables.
 */
class AuditLog extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'outlet_id',
        'user_id',
        'action',
        'target_type',
        'target_id',
        'before_value',
        'after_value',
        'reason',
        'approved_by',
        'device_id',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'before_value' => 'array',
            'after_value'  => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }
}
