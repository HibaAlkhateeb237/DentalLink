<?php

namespace App\Notifications\Tracking;

use App\Models\Order;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class TrackingStateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @param  Collection<int, Order>  $orders
     */
    public function __construct(
        public readonly Collection $orders,
        public readonly string $state,
        public readonly string $deliveryPersonId = '',
        public readonly string $deliveryPersonName = '',
        public readonly string $deliveryPersonPhone = '',
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
                'order_ids' => $this->encodeList($this->orders->pluck('id')->all()),
                'serial_numbers' => $this->encodeList($this->orders->pluck('serial_number')->all()),
                'patient_names' => $this->encodeList($this->orders->pluck('patient_name')->all()),
                'tracking_state' => $this->state,
                'type' => 'tracking_state_change',
                'delivery_person_id' => $this->deliveryPersonId,
                'delivery_person_name' => $this->deliveryPersonName,
                'delivery_person_phone' => $this->deliveryPersonPhone,
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_ids' => $this->orders->pluck('id')->all(),
            'serial_numbers' => $this->orders->pluck('serial_number')->all(),
            'patient_names' => $this->orders->pluck('patient_name')->all(),
            'tracking_state' => $this->state,
            'message' => $this->getBody(),
            'delivery_person_id' => $this->deliveryPersonId,
            'delivery_person_name' => $this->deliveryPersonName,
            'delivery_person_phone' => $this->deliveryPersonPhone,
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
        $orderCount = $this->orders->count();

        $orderList = $this->orders->pluck('serial_number')
            ->filter()
            ->map(fn (string $serial): string => "#{$serial}")
            ->implode(', ');

        if ($orderList === '') {
            $orderList = $this->orders
                ->pluck('id')
                ->map(fn (int $id): string => "#{$id}")
                ->implode(', ');
        }

        return match ($this->state) {
            'started' => "The driver is on the way with {$orderCount} order(s): {$orderList}.",
            'arrived' => "The driver has arrived with {$orderCount} order(s): {$orderList}.",
            'cancelled' => "Delivery tracking has been cancelled for {$orderCount} order(s): {$orderList}.",
            default => "Tracking status changed for {$orderCount} order(s): {$orderList}.",
        };
    }

    /**
     * @param  mixed[]  $values
     */
    private function encodeList(array $values): string
    {
        $encoded = json_encode(array_map(
            static fn (mixed $value): string => (string) $value,
            array_values($values),
        ), JSON_UNESCAPED_UNICODE);

        return $encoded === false ? '[]' : $encoded;
    }
}
