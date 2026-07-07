<?php

namespace App\Notifications\Order;

use App\Models\Order;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderDeliveredToDoctor extends Notification implements ShouldQueue
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
        return [
            'title' => __('orders.delivery_delivered_notification'),
            'body' => __('orders.delivery_delivered_body', [
                'serial_number' => $this->order->serial_number,
                'patient_name' => $this->order->patient_name,
            ]),
            'data' => [
                'order_id' => (string) $this->order->id,
                'type' => 'order_delivery_delivered_to_doctor',
                'serial_number' => $this->order->serial_number,
                'patient_name' => $this->order->patient_name,
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'patient_name' => $this->order->patient_name,
            'serial_number' => $this->order->serial_number,
            'message' => __('orders.delivery_delivered_body', [
                'serial_number' => $this->order->serial_number,
                'patient_name' => $this->order->patient_name,
            ]),
        ];
    }
}