<?php

namespace App\Events;

use App\Models\OrderBatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dipancarkan saat kasir confirm/reject sebuah order_batch dari customer.
 * Diterima oleh Customer App lewat channel PUBLIK (bukan private) yang
 * di-scope per meja via qr_code_token - datanya tidak sensitif dan
 * customer tidak perlu login untuk menerimanya. Lihat 02-SDD.md §4.6.
 */
class OrderBatchStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public OrderBatch $batch)
    {
    }

    public function broadcastOn(): array
    {
        $qrToken = $this->batch->order->table?->qr_code_token;

        return [new Channel("table.{$qrToken}")];
    }

    public function broadcastAs(): string
    {
        return 'OrderBatchStatusChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'batchId' => $this->batch->id,
            'status'  => $this->batch->status, // confirmed, rejected
        ];
    }
}
