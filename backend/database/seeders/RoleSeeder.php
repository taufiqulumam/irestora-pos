<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Admin (global, no outlet_id)
        $admin = Role::firstOrCreate(
            ['name' => 'admin'],
            ['name' => 'admin', 'outlet_id' => null]
        );
        $admin->permissions()->sync(Permission::pluck('id')->toArray());

        // Manager (per outlet)
        $manager = Role::firstOrCreate(
            ['name' => 'manager'],
            ['name' => 'manager', 'outlet_id' => null] // Will be assigned per outlet
        );
        $managerPermissions = [
            'order.create', 'order.view', 'order.edit.own', 'order.edit.any',
            'order.void.own', 'order.void.approve',
            'discount.apply', 'discount.approve',
            'payment.create', 'payment.refund',
            'shift.open', 'shift.close.own', 'shift.close.any',
            'report.daily', 'report.full', 'report.export',
            'menu.manage', 'menu.price.edit', 'category.manage',
            'table.manage',
            'outlet.settings',
            'user.create.cashier', 'user.create.supervisor',
            'user.view', 'user.edit', 'user.deactivate',
            'fraud.review', 'audit.view',
        ];
        $manager->permissions()->sync(Permission::whereIn('code', $managerPermissions)->pluck('id')->toArray());

        // Supervisor (per outlet)
        $supervisor = Role::firstOrCreate(
            ['name' => 'supervisor'],
            ['name' => 'supervisor', 'outlet_id' => null]
        );
        $supervisorPermissions = [
            'order.create', 'order.view', 'order.edit.own',
            'order.void.own', 'order.void.approve',
            'discount.apply', 'discount.approve',
            'payment.create',
            'shift.open', 'shift.close.own', 'shift.close.any',
            'report.daily',
            'fraud.review',
        ];
        $supervisor->permissions()->sync(Permission::whereIn('code', $supervisorPermissions)->pluck('id')->toArray());

        // Cashier (per outlet)
        $cashier = Role::firstOrCreate(
            ['name' => 'cashier'],
            ['name' => 'cashier', 'outlet_id' => null]
        );
        $cashierPermissions = [
            'order.create', 'order.view', 'order.edit.own',
            'order.void.own',
            'discount.apply',
            'payment.create',
            'shift.open', 'shift.close.own',
        ];
        $cashier->permissions()->sync(Permission::whereIn('code', $cashierPermissions)->pluck('id')->toArray());

        // Kitchen (per outlet) - for future use
        $kitchen = Role::firstOrCreate(
            ['name' => 'kitchen'],
            ['name' => 'kitchen', 'outlet_id' => null]
        );
        $kitchenPermissions = [
            'order.view',
        ];
        $kitchen->permissions()->sync(Permission::whereIn('code', $kitchenPermissions)->pluck('id')->toArray());
    }
}