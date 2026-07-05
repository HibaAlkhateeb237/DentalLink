<?php

namespace App\Notifications\Task;

use App\Models\Task;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DepartmentManagerTaskMovedForwardNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly Task $task
    ) {}

    public function via(object $notifiable): array
    {
        return [FcmChannel::class, 'database'];
    }

    public function toFcm(object $notifiable): array
    {
        $order = $this->task->order;

        return [
            'title' => 'Urgent Case Transfer',
            'body' => 'Urgent patient "'.($order->patient_name ?? 'Unknown Patient').'" case has been transferred from '.($this->task->department->name ?? 'Previous Department').' to the next department.',
            'data' => [
                'task_id' => (string) $this->task->id,
                'order_id' => (string) $this->task->order_id,
                'type' => 'urgent_case_transfer',
                'patient_name' => $order->patient_name,
                'priority' => $order->priority,
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        $order = $this->task->order;

        return [
            'task_id' => $this->task->id,
            'order_id' => $this->task->order_id,
            'department_id' => $this->task->department_id,
            'patient_name' => $order->patient_name,
            'serial_number' => $order->serial_number,
            'priority' => $order->priority,
            'message' => 'Urgent case "'.($order->patient_name ?? 'Unknown Patient').'" (Order #'.($order->serial_number ?? 'N/A').') has been transferred to your department. Please review immediately.',
        ];
    }
}
