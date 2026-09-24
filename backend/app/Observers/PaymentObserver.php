<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class PaymentObserver
{
    /**
     * Handle the Payment "created" event.
     */
    public function created(Payment $payment): void
    {
        $this->log('create_payment', $payment, null, [
            'method' => $payment->method,
            'amount' => $payment->amount,
            'reference_number' => $payment->reference_number,
        ]);
    }

    /**
     * Handle the Payment "updated" event.
     */
    public function updated(Payment $payment): void
    {
        $dirty = $payment->getDirty();

        // Log refund
        if (isset($dirty['method']) && $payment->method === 'refund') {
            $this->log('refund', $payment,
                ['method' => $payment->getOriginal('method'), 'amount' => $payment->getOriginal('amount')],
                ['method' => $payment->method, 'amount' => $payment->amount]
            );
        }
    }

    /**
     * Handle the Payment "deleted" event.
     */
    public function deleted(Payment $payment): void
    {
        $this->log('delete_payment', $payment, [
            'method' => $payment->method,
            'amount' => $payment->amount,
        ], null);
    }

    /**
     * Log to audit_logs
     */
    protected function log(string $action, Payment $payment, ?array $before = null, ?array $after = null): void
    {
        $user = Auth::user();
        if (! $user) return;

        AuditLog::create([
            'outlet_id'   => $payment->order->outlet_id ?? $user->outlet_id,
            'user_id'     => $user->id,
            'action'      => $action,
            'target_type' => 'payments',
            'target_id'   => $payment->id,
            'before_value' => $before,
            'after_value'  => $after,
            'device_id'   => Request::header('X-Device-Id', 'unknown'),
            'ip_address'  => Request::ip(),
        ]);
    }
}