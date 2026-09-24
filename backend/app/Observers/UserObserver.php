<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $this->log('create_user', $user, null, [
            'name' => $user->full_name,
            'email' => $user->email,
            'role' => $user->role?->name,
            'outlet_id' => $user->outlet_id,
        ]);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        $dirty = $user->getDirty();

        // Log role changes
        if (isset($dirty['role_id'])) {
            $this->log('change_user_role', $user,
                ['role_id' => $user->getOriginal('role_id')],
                ['role_id' => $user->role_id]
            );
        }

        // Log status changes
        if (isset($dirty['is_active'])) {
            $this->log($user->is_active ? 'activate_user' : 'deactivate_user', $user,
                ['is_active' => $user->getOriginal('is_active')],
                ['is_active' => $user->is_active]
            );
        }

        // Log PIN changes
        if (isset($dirty['pin_hash'])) {
            $this->log('change_pin', $user, ['pin_changed' => true], ['pin_changed' => true]);
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        $this->log('delete_user', $user, [
            'name' => $user->full_name,
            'email' => $user->email,
        ], null);
    }

    /**
     * Log to audit_logs
     */
    protected function log(string $action, User $user, ?array $before = null, ?array $after = null): void
    {
        $actor = Auth::user();
        if (! $actor) return;

        AuditLog::create([
            'outlet_id'   => $user->outlet_id ?? $actor->outlet_id,
            'user_id'     => $actor->id,
            'action'      => $action,
            'target_type' => 'users',
            'target_id'   => $user->id,
            'before_value' => $before,
            'after_value'  => $after,
            'device_id'   => Request::header('X-Device-Id', 'unknown'),
            'ip_address'  => Request::ip(),
        ]);
    }
}