<?php

namespace App\Notifications\Task;

use App\Models\Task;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TaskAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public readonly Task $task) {}

    public function via(object $notifiable): array
    {
        return [FcmChannel::class, 'database'];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'New task assigned',
            'body' => 'You have been assigned a new task in the department '.($this->task->department->name ?? '').'.',
            'data' => [
                'task_id' => (string) $this->task->id,
                'order_id' => (string) $this->task->order_id,
                'type' => 'task_assigned',
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'order_id' => $this->task->order_id,
            'department_id' => $this->task->department_id,
            'message' => 'You have been assigned a new task.',
        ];
    }
}
