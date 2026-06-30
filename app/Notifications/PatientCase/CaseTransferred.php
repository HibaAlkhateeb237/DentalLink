<?php

namespace App\Notifications\PatientCase;

use App\Models\Order;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CaseTransferred extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly Order $order,
        public readonly string $fromDepartmentName,
        public readonly string $toDepartmentName
    ) {}

    public function via(object $notifiable): array
    {
        return [FcmChannel::class, 'database'];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Patient Case Transfer',
            'body' => 'Patient "'.($this->order->patient_name ?? 'Unknown Patient').'" case has been transferred from '.$this->fromDepartmentName.' to '.$this->toDepartmentName.'.',
            'data' => [
                'order_id' => (string) $this->order->id,
                'from_department' => $this->fromDepartmentName,
                'to_department' => $this->toDepartmentName,
                'type' => 'patient_case_transferred',
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'patient_name' => $this->order->patient_name,
            'from_department' => $this->fromDepartmentName,
            'to_department' => $this->toDepartmentName,
            'message' => 'The case of patient "'.($this->order->patient_name ?? 'Unknown Patient').'" has moved from '.$this->fromDepartmentName.' department to '.$this->toDepartmentName.' department.',
        ];
    }
}
