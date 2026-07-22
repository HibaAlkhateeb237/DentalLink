<?php

namespace App\Notifications\Tracking;

use App\Models\Order;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TrackingStateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly Order $order,
        public readonly string $state,
    ) {}

    public function via(object $notifiable): array
    {
        return [FcmChannel::class, 'database'];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => $this->getTitle(),
            'body' => $this->getBody(),
            'data' => [
                'order_id' => (string) $this->order->id,
                'serial_number' => $this->order->serial_number ?? '',
                'patient_name' => $this->order->patient_name ?? '',
                'tracking_state' => $this->state,
                'type' => 'tracking_state_change',
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'serial_number' => $this->order->serial_number,
            'patient_name' => $this->order->patient_name,
            'tracking_state' => $this->state,
            'message' => $this->getBody(),
        ];
    }

    private function getTitle(): string
    {
        return match ($this->state) {
            'started' => 'Delivery Started',
            'arrived' => 'Delivery Arrived',
            'cancelled' => 'Delivery Cancelled',
            default => 'Tracking Update',
        };
    }

    private function getBody(): string
    {
        return match ($this->state) {
            'started' => "Delivery person is on the way for order #{$this->order->serial_number}.",
            'arrived' => "Delivery person has arrived for order #{$this->order->serial_number}.",
            'cancelled' => "Delivery tracking for order #{$this->order->serial_number} has been cancelled.",
            default => "Tracking status changed for order #{$this->order->serial_number}.",
        };
    }
}
