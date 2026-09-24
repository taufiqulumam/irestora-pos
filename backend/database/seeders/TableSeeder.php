<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DiningTable;
use App\Models\Outlet;
use Illuminate\Support\Str;

class TableSeeder extends Seeder
{
    public function run(): void
    {
        $outlets = Outlet::where('is_active', true)->get();
        
        foreach ($outlets as $outlet) {
            for ($i = 1; $i <= 10; $i++) {
                DiningTable::firstOrCreate(
                    [
                        'outlet_id' => $outlet->id,
                        'name' => "Meja $i",
                    ],
                    [
                        'qr_code_token' => Str::uuid()->toString(),
                        'status' => 'available',
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}