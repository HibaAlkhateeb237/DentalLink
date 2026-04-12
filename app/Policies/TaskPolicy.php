<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('system_admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(['tasks.view-department', 'tasks.view-assigned']);
    }

    public function view(User $user, Task $task): bool
    {
        if ($user->hasPermission('tasks.view-department', $task->department_id)) {
            return true;
        }

        return $user->hasPermission('tasks.view-assigned', $task->department_id) && $task->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('tasks.assign');
    }

    public function update(User $user, Task $task): bool
    {
        if ($user->hasPermission('tasks.assign', $task->department_id)) {
            return true;
        }

        $assignedToCurrentUser = $task->user_id === $user->id;

        return $assignedToCurrentUser && $user->hasPermission(['tasks.start', 'tasks.finish'], $task->department_id);
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.assign', $task->department_id);
    }
}
