<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        $this->log('create_order', $order, null, [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'order_type' => $order->order_type,
            'table_id' => $order->table_id,
            'grand_total' => $order->grand_total,
        ]);
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        $dirty = $order->getDirty();
        
        // Log status changes
        if (isset($dirty['status'])) {
            $this->log('order_status_changed', $order, 
                ['status' => $order->getOriginal('status')], 
                ['status' => $order->status]
            );
        }

        // Log discount changes
        if (isset($dirty['discount_total'])) {
            $this->log('apply_discount', $order,
                ['discount_total' => $order->getOriginal('discount_total')],
                ['discount_total' => $order->discount_total]
            );
        }

        // Log grand total changes
        if (isset($dirty['grand_total'])) {
            $this->log('grand_total_changed', $order,
                ['grand_total' => $order->getOriginal('grand_total')],
                ['grand_total' => $order->grand_total]
            );
        }
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        $this->log('delete_order', $order, [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'grand_total' => $order->grand_total,
        ], null);
    }

    /**
     * Log to audit_logs
     */
    protected function log(string $action, Order $order, ?array $before = null, ?array $after = null): void
    {
        $user = Auth::user();
        if (! $user) return;

        AuditLog::create([
            'outlet_id'   => $order->outlet_id,
            'user_id'     => $user->id,
            'action'      => $action,
            'target_type' => 'orders',
            'target_id'   => $order->id,
            'before_value' => $before,
            'after_value'  => $after,
            'device_id'   => Request::header('X-Device-Id', 'unknown'),
            'ip_address'  => Request::ip(),
        ]);
    }
}