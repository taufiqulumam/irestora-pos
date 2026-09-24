<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('user.view');
    }

    /**
     * Determine whether the user can view the user.
     */
    public function view(User $user, User $target): bool
    {
        // Admin can view all
        if ($user->role->name === 'admin') {
            return true;
        }

        // Users can view users in their outlet
        return $user->outlet_id === $target->outlet_id;
    }

    /**
     * Determine whether the user can create cashiers.
     */
    public function createCashier(User $user): bool
    {
        // Admin can create managers
        if ($user->role->name === 'admin') {
            return true;
        }

        // Manager can create cashiers and supervisors
        if ($user->hasPermissionTo('user.create.cashier')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create supervisors.
     */
    public function createSupervisor(User $user): bool
    {
        // Admin can create managers
        if ($user->role->name === 'admin') {
            return true;
        }

        // Manager can create cashiers and supervisors
        if ($user->hasPermissionTo('user.create.supervisor')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create managers.
     */
    public function createManager(User $user): bool
    {
        // Only admin can create managers
        return $user->role->name === 'admin' && $user->hasPermissionTo('user.create.manager');
    }

    /**
     * Determine whether the user can update the user.
     */
    public function update(User $user, User $target): bool
    {
        // Admin can update all
        if ($user->role->name === 'admin') {
            return true;
        }

        // Users can update users in their outlet (if they have permission)
        if ($user->hasPermissionTo('user.edit')) {
            return $user->outlet_id === $target->outlet_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the user.
     */
    public function delete(User $user, User $target): bool
    {
        // Admin can delete (deactivate) users
        if ($user->role->name === 'admin') {
            return true;
        }

        // Manager can deactivate users in their outlet
        if ($user->hasPermissionTo('user.edit')) {
            return $user->outlet_id === $target->outlet_id && $target->id !== $user->id;
        }

        return false;
    }
}