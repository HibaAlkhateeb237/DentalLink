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

    /**
     * @param  int[]  $taskIds
     * @param  int[]  $orderIds
     */
    public function __construct(
        public readonly int $doctorId,
        public readonly array $taskIds,
        public readonly array $orderIds,
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
        return new PrivateChannel('tracking.doctor.'.$this->doctorId);
    }

    public function broadcastWith(): array
    {
        return [
            'doctor_id' => $this->doctorId,
            'task_ids' => $this->taskIds,
            'order_ids' => $this->orderIds,
            'delivery_person_id' => $this->deliveryPersonId,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'status' => $this->status,
            'location_recorded_at' => $this->locationRecordedAt,
        ];
    }
}
