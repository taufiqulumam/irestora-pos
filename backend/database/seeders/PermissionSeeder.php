<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Order permissions
            ['code' => 'order.create', 'description' => 'Create new orders'],
            ['code' => 'order.view', 'description' => 'View orders'],
            ['code' => 'order.edit.own', 'description' => 'Edit own orders'],
            ['code' => 'order.edit.any', 'description' => 'Edit any orders'],
            ['code' => 'order.void.own', 'description' => 'Void own orders'],
            ['code' => 'order.void.approve', 'description' => 'Approve void orders'],
            
            // Discount permissions
            ['code' => 'discount.apply', 'description' => 'Apply discount to orders'],
            ['code' => 'discount.approve', 'description' => 'Approve discounts above threshold'],
            
            // Payment permissions
            ['code' => 'payment.create', 'description' => 'Create payments'],
            ['code' => 'payment.refund', 'description' => 'Process refunds'],
            
            // Shift permissions
            ['code' => 'shift.open', 'description' => 'Open shift'],
            ['code' => 'shift.close.own', 'description' => 'Close own shift'],
            ['code' => 'shift.close.any', 'description' => 'Close any shift'],
            
            // Report permissions
            ['code' => 'report.daily', 'description' => 'View daily reports'],
            ['code' => 'report.full', 'description' => 'View full reports'],
            ['code' => 'report.export', 'description' => 'Export reports'],
            
            // Menu permissions
            ['code' => 'menu.manage', 'description' => 'Manage menus'],
            ['code' => 'menu.price.edit', 'description' => 'Edit menu prices'],
            ['code' => 'category.manage', 'description' => 'Manage categories'],
            
            // Table permissions
            ['code' => 'table.manage', 'description' => 'Manage tables'],
            
            // Outlet permissions
            ['code' => 'outlet.settings', 'description' => 'Manage outlet settings'],
            ['code' => 'outlet.create', 'description' => 'Create outlets'],
            
            // User permissions
            ['code' => 'user.create.cashier', 'description' => 'Create cashier users'],
            ['code' => 'user.create.supervisor', 'description' => 'Create supervisor users'],
            ['code' => 'user.create.manager', 'description' => 'Create manager users'],
            ['code' => 'user.view', 'description' => 'View users'],
            ['code' => 'user.edit', 'description' => 'Edit users'],
            ['code' => 'user.deactivate', 'description' => 'Deactivate users'],
            
            // Fraud & Audit permissions
            ['code' => 'fraud.review', 'description' => 'Review fraud alerts'],
            ['code' => 'audit.view', 'description' => 'View audit logs'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['code' => $permission['code']],
                $permission
            );
        }
    }
}