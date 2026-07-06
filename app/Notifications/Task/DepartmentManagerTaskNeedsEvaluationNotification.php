<?php

namespace App\Notifications\Task;

use App\Models\Task;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DepartmentManagerTaskNeedsEvaluationNotification extends Notification implements ShouldQueue
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
            'title' => __('tasks.needs_evaluation_notification_title'),
            'body' => __('tasks.needs_evaluation_notification_body', [
                'serial_number' => $order?->serial_number ?? __('messages.not_found'),
                'patient_name' => $order?->patient_name ?? __('messages.not_found'),
                'department_name' => $this->task->department?->name ?? __('messages.not_found'),
            ]),
            'data' => [
                'task_id' => (string) $this->task->id,
                'order_id' => (string) $this->task->order_id,
                'department_id' => (string) $this->task->department_id,
                'type' => 'task_needs_evaluation',
                'status' => $this->task->status,
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
            'patient_name' => $order?->patient_name,
            'serial_number' => $order?->serial_number,
            'status' => $this->task->status,
            'message' => __('tasks.needs_evaluation_notification_body', [
                'serial_number' => $order?->serial_number ?? __('messages.not_found'),
                'patient_name' => $order?->patient_name ?? __('messages.not_found'),
                'department_name' => $this->task->department?->name ?? __('messages.not_found'),
            ]),
        ];
    }
}
