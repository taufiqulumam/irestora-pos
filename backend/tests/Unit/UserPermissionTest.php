<?php

namespace Tests\Unit;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserPermissionTest extends TestCase
{
    #[DataProvider('rolePermissions')]
    public function test_role_permission_matrix(string $roleName, array $granted, string $permission, bool $expected): void
    {
        $role = (new Role())->setRelation('permissions', collect(array_map(
            fn (string $code) => (new Permission())->forceFill(['code' => $code]),
            $granted
        )));
        $user = (new User())->setRelation('role', $role);

        self::assertSame($expected, $user->hasPermissionTo($permission), $roleName);
    }

    public static function rolePermissions(): array
    {
        return [
            'cashier can transact' => ['cashier', ['order.create', 'payment.create', 'shift.close.own'], 'order.create', true],
            'cashier cannot manage menu' => ['cashier', ['order.create', 'payment.create'], 'menu.price.edit', false],
            'supervisor can review fraud' => ['supervisor', ['fraud.review', 'discount.approve'], 'fraud.review', true],
            'supervisor cannot manage staff' => ['supervisor', ['fraud.review'], 'user.edit', false],
            'manager can edit prices' => ['manager', ['menu.manage', 'menu.price.edit', 'user.view'], 'menu.price.edit', true],
            'manager cannot create manager' => ['manager', ['user.create.cashier', 'user.create.supervisor'], 'user.create.manager', false],
            'admin can manage roles' => ['admin', ['user.create.manager', 'user.edit', 'audit.view'], 'user.create.manager', true],
        ];
    }
}