<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Outlet;

class OutletSeeder extends Seeder
{
    public function run(): void
    {
        $outlets = [
            [
                'name' => 'iRestora Cabang Bandung',
                'code' => 'BDG01',
                'address' => 'Jl. Asia Afrika No.10, Bandung',
                'pb1_rate' => 10.0,
                'service_charge_rate' => 5.0,
                'rounding_enabled' => true,
                'is_active' => true,
            ],
            [
                'name' => 'iRestora Cabang Jakarta',
                'code' => 'JKT01',
                'address' => 'Jl. Sudirman No.25, Jakarta',
                'pb1_rate' => 10.0,
                'service_charge_rate' => 5.0,
                'rounding_enabled' => true,
                'is_active' => true,
            ],
            [
                'name' => 'iRestora Cabang Surabaya',
                'code' => 'SBY01',
                'address' => 'Jl. Basuki Rahmat No.50, Surabaya',
                'pb1_rate' => 10.0,
                'service_charge_rate' => 5.0,
                'rounding_enabled' => true,
                'is_active' => true,
            ],
        ];

        foreach ($outlets as $outlet) {
            Outlet::firstOrCreate(
                ['code' => $outlet['code']],
                $outlet
            );
        }
    }
}