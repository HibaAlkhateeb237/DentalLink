<?php

namespace App\Notifications\Order;

use App\Models\DeliveryTask;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DeliveryAssignedToDriver extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly DeliveryTask $deliveryTask
    ) {}

    public function via(object $notifiable): array
    {
        return [FcmChannel::class, 'database'];
    }

    public function toFcm(object $notifiable): array
    {
        $order = $this->deliveryTask->order;

        return [
            'title' => 'New Delivery Assigned',
            'body' => "Delivery assigned for order \"{$order->patient_name}\" (Order #{$order->serial_number})",
            'data' => [
                'delivery_task_id' => (string) $this->deliveryTask->id,
                'order_id' => (string) $order->id,
                'patient_name' => $order->patient_name,
                'serial_number' => $order->serial_number,
                'order_type' => $order->order_type,
                'status' => $this->deliveryTask->status,
                'direction' => $this->deliveryTask->direction,
                'type' => 'delivery_assigned',
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        $order = $this->deliveryTask->order;

        return [
            'delivery_task_id' => $this->deliveryTask->id,
            'order_id' => $order->id,
            'patient_name' => $order->patient_name,
            'serial_number' => $order->serial_number,
            'order_type' => $order->order_type,
            'status' => $this->deliveryTask->status,
            'direction' => $this->deliveryTask->direction,
            'message' => "Delivery assigned for order \"{$order->patient_name}\" (Order #{$order->serial_number})",
        ];
    }
}
