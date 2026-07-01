<?php

namespace App\Notifications\Order;

use App\Models\Order;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderProcessingStarted extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly Order $order,
        public readonly string $triggerType = 'manual'
    ) {}

    public function via(object $notifiable): array
    {
        return [FcmChannel::class, 'database'];
    }

    public function toFcm(object $notifiable): array
    {
        $status = $this->order->status;
        $statusDisplay = match ($status) {
            'in_progress' => 'processing',
            'try_on' => 'try-on',
            'resend_wrong_impression' => 'resend',
            default => 'processing'
        };

        return [
            'title' => 'Order ' . $this->order->serial_number . ' ' . __(
                'orders.processing_started_notification',
                ['status' => ucfirst($statusDisplay)]
            ),
            'body' => "Patient \"{$this->order->patient_name}\" order has started processing.",
            'data' => [
                'order_id' => (string) $this->order->id,
                'type' => 'order_processing_started',
                'trigger_type' => $this->triggerType,
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'patient_name' => $this->order->patient_name,
            'serial_number' => $this->order->serial_number,
            'status' => $this->order->status,
            'trigger_type' => $this->triggerType,
            'message' => "Your order \"{$this->order->serial_number}\" has started processing.",
        ];
    }
}
