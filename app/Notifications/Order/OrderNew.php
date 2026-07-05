<?php

namespace App\Notifications\Order;

use App\Models\Order;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderNew extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly Order $order
    ) {}

    public function via(object $notifiable): array
    {
        return [FcmChannel::class, 'database'];
    }

    public function toFcm(object $notifiable): array
    {
        $message = "New order from \"{$this->order->patient_name}\" (Order #{$this->order->serial_number}) requires attention.";

        return [
            'title' => __(
                'orders.new_order_notification',
                ['serial_number' => $this->order->serial_number]
            ),
            'body' => $message,
            'data' => [
                'order_id' => (string) $this->order->id,
                'type' => 'order_new',
                'patient_name' => $this->order->patient_name,
                'serial_number' => $this->order->serial_number,
                'lab_id' => (string) $this->order->lab_id,
                'priority' => $this->order->priority,
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'patient_name' => $this->order->patient_name,
            'serial_number' => $this->order->serial_number,
            'lab_id' => $this->order->lab_id,
            'priority' => $this->order->priority,
            'message' => "New order from \"{$this->order->patient_name}\" (Order #{$this->order->serial_number}) requires attention.",
        ];
    }
}