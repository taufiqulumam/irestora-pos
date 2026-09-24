<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class OrderItemObserver
{
    /**
     * Handle the OrderItem "updated" event.
     */
    public function updated(OrderItem $item): void
    {
        $dirty = $item->getDirty();

        // Log void
        if (isset($dirty['status']) && $item->status === 'voided') {
            $this->log('void_item', $item, 
                ['status' => $item->getOriginal('status'), 'qty' => $item->getOriginal('qty')], 
                ['status' => $item->status, 'qty' => $item->qty, 'voided_by' => $item->voided_by, 'void_reason' => $item->void_reason]
            );
        }

        // Log qty changes
        if (isset($dirty['qty'])) {
            $this->log('update_item_qty', $item,
                ['qty' => $item->getOriginal('qty')],
                ['qty' => $item->qty]
            );
        }

        // Log notes changes
        if (isset($dirty['notes'])) {
            $this->log('update_item_notes', $item,
                ['notes' => $item->getOriginal('notes')],
                ['notes' => $item->notes]
            );
        }
    }

    /**
     * Handle the OrderItem "deleted" event.
     */
    public function deleted(OrderItem $item): void
    {
        $this->log('delete_item', $item, [
            'menu_name' => $item->menu_name_snapshot,
            'qty' => $item->qty,
            'price' => $item->price_snapshot,
        ], null);
    }

    /**
     * Log to audit_logs
     */
    protected function log(string $action, OrderItem $item, ?array $before = null, ?array $after = null): void
    {
        $user = Auth::user();
        if (! $user) return;

        AuditLog::create([
            'outlet_id'   => $item->order->outlet_id ?? $user->outlet_id,
            'user_id'     => $user->id,
            'action'      => $action,
            'target_type' => 'order_items',
            'target_id'   => $item->id,
            'before_value' => $before,
            'after_value'  => $after,
            'device_id'   => Request::header('X-Device-Id', 'unknown'),
            'ip_address'  => Request::ip(),
        ]);
    }
}