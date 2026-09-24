<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Shift;

class ShiftPolicy
{
    /**
     * Determine whether the user can view any shifts.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('report.daily') || $user->hasPermissionTo('shift.open');
    }

    /**
     * Determine whether the user can view the shift.
     */
    public function view(User $user, Shift $shift): bool
    {
        // Admin can view all
        if ($user->role->name === 'admin') {
            return true;
        }

        // Users can view shifts in their outlet
        return $user->outlet_id === $shift->outlet_id;
    }

    /**
     * Determine whether the user can open a shift.
     */
    public function open(User $user): bool
    {
        return $user->hasPermissionTo('shift.open');
    }

    /**
     * Determine whether the user can close their own shift.
     */
    public function closeOwn(User $user, Shift $shift): bool
    {
        return $shift->opened_by === $user->id && $user->hasPermissionTo('shift.close.own');
    }

    /**
     * Determine whether the user can close any shift.
     */
    public function closeAny(User $user, Shift $shift): bool
    {
        // Admin can close any
        if ($user->role->name === 'admin') {
            return true;
        }

        // Supervisor/Manager can close any shift in their outlet
        if ($user->hasPermissionTo('shift.close.any')) {
            return $user->outlet_id === $shift->outlet_id;
        }

        return false;
    }
}