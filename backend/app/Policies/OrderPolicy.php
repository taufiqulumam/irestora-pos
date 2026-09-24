<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Order;

class OrderPolicy
{
    /**
     * Determine whether the user can view any orders.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('report.daily') || $user->hasPermissionTo('report.full');
    }

    /**
     * Determine whether the user can view the order.
     */
    public function view(User $user, Order $order): bool
    {
        // Admin can view all
        if ($user->role->name === 'admin') {
            return true;
        }

        // Users can only view orders from their outlet
        return $user->outlet_id === $order->outlet_id;
    }

    /**
     * Determine whether the user can create orders.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('order.create');
    }

    /**
     * Determine whether the user can update the order.
     */
    public function update(User $user, Order $order): bool
    {
        // Admin can update all
        if ($user->role->name === 'admin') {
            return true;
        }

        // Cashier can only update their own open orders
        if ($user->hasPermissionTo('order.edit.own')) {
            return $order->cashier_id === $user->id && $order->status === 'open';
        }

        // Manager/Supervisor can update any order in their outlet
        if ($user->hasPermissionTo('order.edit.any')) {
            return $user->outlet_id === $order->outlet_id;
        }

        return false;
    }

    /**
     * Determine whether the user can void the order.
     */
    public function void(User $user, Order $order): bool
    {
        // Admin can void any
        if ($user->role->name === 'admin') {
            return true;
        }

        // Own void
        if ($user->hasPermissionTo('order.void.own')) {
            return $order->cashier_id === $user->id;
        }

        // Approve void (supervisor/manager)
        if ($user->hasPermissionTo('order.void.approve')) {
            return $user->outlet_id === $order->outlet_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the order.
     */
    public function delete(User $user, Order $order): bool
    {
        return false; // Orders should never be deleted, only voided
    }
}