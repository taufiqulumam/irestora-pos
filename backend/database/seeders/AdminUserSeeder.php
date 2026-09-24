<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;
use App\Models\Outlet;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Get or create global admin role
        $adminRole = Role::where('name', 'admin')->whereNull('outlet_id')->first();
        
        // Get first outlet for admin user reference
        $outlet = Outlet::first();

        // Create global admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@irestora.com'],
            [
                'outlet_id' => null,
                'role_id' => $adminRole->id,
                'email' => 'admin@irestora.com',
                'password' => Hash::make('admin123'),
                'full_name' => 'System Administrator',
                'phone' => '081234567890',
                'pin_hash' => Hash::make('123456'),
                'is_active' => true,
            ]
        );

        // Create outlet manager for first outlet
        $managerRole = Role::where('name', 'manager')->first();
        if ($outlet) {
            User::firstOrCreate(
                ['email' => 'manager@irestora.com'],
                [
                    'outlet_id' => $outlet->id,
                    'role_id' => $managerRole->id,
                    'email' => 'manager@irestora.com',
                    'password' => Hash::make('manager123'),
                    'full_name' => 'Outlet Manager',
                    'phone' => '081234567891',
                    'pin_hash' => Hash::make('123456'),
                    'is_active' => true,
                ]
            );

            // Create supervisor for first outlet
            $supervisorRole = Role::where('name', 'supervisor')->first();
            User::firstOrCreate(
                ['email' => 'supervisor@irestora.com'],
                [
                    'outlet_id' => $outlet->id,
                    'role_id' => $supervisorRole->id,
                    'email' => 'supervisor@irestora.com',
                    'password' => Hash::make('supervisor123'),
                    'full_name' => 'Shift Supervisor',
                    'phone' => '081234567892',
                    'pin_hash' => Hash::make('123456'),
                    'is_active' => true,
                ]
            );

            // Create cashier for first outlet
            $cashierRole = Role::where('name', 'cashier')->first();
            User::firstOrCreate(
                ['email' => 'cashier@irestora.com'],
                [
                    'outlet_id' => $outlet->id,
                    'role_id' => $cashierRole->id,
                    'email' => 'cashier@irestora.com',
                    'password' => Hash::make('cashier123'),
                    'full_name' => 'Budi Santoso',
                    'phone' => '081234567893',
                    'pin_hash' => Hash::make('1234'),
                    'is_active' => true,
                ]
            );

            // Create another cashier
            User::firstOrCreate(
                ['email' => 'cashier2@irestora.com'],
                [
                    'outlet_id' => $outlet->id,
                    'role_id' => $cashierRole->id,
                    'email' => 'cashier2@irestora.com',
                    'password' => Hash::make('cashier123'),
                    'full_name' => 'Siti Rahayu',
                    'phone' => '081234567894',
                    'pin_hash' => Hash::make('5678'),
                    'is_active' => true,
                ]
            );
        }
    }
}