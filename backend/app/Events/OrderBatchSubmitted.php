<?php

namespace App\Events;

use App\Models\OrderBatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dipancarkan saat customer submit cart (lihat 03-ERD.md §5 poin 5).
 * Diterima real-time oleh Kasir App via channel privat per outlet.
 *
 * PENTING (lihat 02-SDD.md §4.6): event ini hanya untuk KECEPATAN notifikasi.
 * Kasir app tetap wajib fetch ulang via REST saat reconnect setelah offline,
 * karena WebSocket bisa melewatkan event selama koneksi terputus.
 */
class OrderBatchSubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public OrderBatch $batch)
    {
    }

    public function broadcastOn(): array
    {
        $outletId = $this->batch->order->outlet_id;

        return [new PrivateChannel("outlet.{$outletId}.cashier")];
    }

    public function broadcastAs(): string
    {
        return 'OrderBatchSubmitted';
    }

    public function broadcastWith(): array
    {
        return [
            'batchId'   => $this->batch->id,
            'orderId'   => $this->batch->order_id,
            'tableName' => $this->batch->order->table?->name,
            'itemCount' => $this->batch->items()->count(),
            'submittedAt' => $this->batch->submitted_at?->toISOString(),
        ];
    }
}
