<?php

namespace App\Notifications\Order;

use App\Models\Order;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderPrintStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly Order $order,
        public readonly string $printStatus,
        public readonly ?string $doctorNotes = null,
    ) {}

    public function via(object $notifiable): array
    {
        return [FcmChannel::class, 'database'];
    }

    public function toFcm(object $notifiable): array
    {
        $title = match ($this->printStatus) {
            'new_print' => __('orders.print_status_new_print_title'),
            'trial' => __('orders.print_status_trial_title'),
            default => __('orders.print_status_notification'),
        };

        $body = match ($this->printStatus) {
            'new_print' => __('orders.print_status_new_print_body', ['serial_number' => $this->order->serial_number]),
            'trial' => __('orders.print_status_trial_body', ['serial_number' => $this->order->serial_number]),
            default => '',
        };

        return [
            'title' => $title,
            'body' => $body,
            'data' => [
                'order_id' => (string) $this->order->id,
                'type' => 'order_print_status',
                'print_status' => $this->printStatus,
                'serial_number' => $this->order->serial_number,
                'patient_name' => $this->order->patient_name,
                'lab_id' => (string) $this->order->lab_id,
                'doctor_notes' => $this->doctorNotes,
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        $body = match ($this->printStatus) {
            'new_print' => __('orders.print_status_new_print_body', ['serial_number' => $this->order->serial_number]),
            'trial' => __('orders.print_status_trial_body', ['serial_number' => $this->order->serial_number]),
            default => '',
        };

        return [
            'order_id' => $this->order->id,
            'serial_number' => $this->order->serial_number,
            'patient_name' => $this->order->patient_name,
            'print_status' => $this->printStatus,
            'doctor_notes' => $this->doctorNotes,
            'message' => $body,
        ];
    }
}
