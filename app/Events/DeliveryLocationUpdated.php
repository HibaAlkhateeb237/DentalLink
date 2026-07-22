<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $orderId,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly int $deliveryPersonId,
        public readonly string $status,
        public readonly ?string $locationRecordedAt = null,
    ) {}

    public function broadcastAs(): string
    {
        return 'location.updated';
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('tracking.order.'.$this->orderId);
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'delivery_person_id' => $this->deliveryPersonId,
            'status' => $this->status,
            'location_recorded_at' => $this->locationRecordedAt,
        ];
    }
}
