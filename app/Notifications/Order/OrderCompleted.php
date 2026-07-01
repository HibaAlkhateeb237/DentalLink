<?php

namespace App\Notifications\Order;

use App\Models\Order;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return [FcmChannel::class, 'database'];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => __(
                'orders.order_completed_notification',
                ['serial_number' => $this->order->serial_number]
            ),
            'body' => __(
                'orders.order_completed_body',
                ['patient_name' => $this->order->patient_name]
            ),
            'data' => [
                'order_id' => (string) $this->order->id,
                'type' => 'order_completed',
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
            'message' => "Your order \"{$this->order->serial_number}\" has been completed.",
        ];
    }
}
