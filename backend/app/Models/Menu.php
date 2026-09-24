<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use HasUuids;

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'image_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function prices()
    {
        return $this->hasMany(MenuPrice::class);
    }

    public function priceForOutlet(string $outletId): ?float
    {
        return $this->prices()
            ->where('outlet_id', $outletId)
            ->where('is_active', true)
            ->value('price');
    }
}
