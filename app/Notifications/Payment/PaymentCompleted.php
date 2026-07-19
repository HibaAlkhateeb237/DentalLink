<?php

namespace App\Notifications\Payment;

use App\Models\Order;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentCompleted extends Notification implements ShouldQueue
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
            'title' => __('payments.payment_completed_notification'),
            'body' => __(
                'payments.payment_completed_body',
                ['serial_number' => $this->order->serial_number]
            ),
            'data' => [
                'order_id' => (string) $this->order->id,
                'type' => 'payment_completed',
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'patient_name' => $this->order->patient_name,
            'serial_number' => $this->order->serial_number,
            'price' => $this->order->price,
            'message' => "Payment for order \"{$this->order->serial_number}\" has been completed successfully.",
        ];
    }
}
