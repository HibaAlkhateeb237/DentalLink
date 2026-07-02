<?php

namespace App\Http\Services;

use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Order;
use App\Models\Task;
use App\Models\TaskWorkSession;
use App\Models\User;
use App\Repositories\TaskRepository;
use App\Http\Services\OrderNotificationService;
use App\Support\OrderStatus;
use App\Support\TaskStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class LabTechnicianTaskService
{
    public function __construct(
        private TaskRepository $tasks,
        private OrderNotificationService $orderNotificationService
    ) {}

    public function canViewDepartment(User $user, Department $department): bool
    {
        return DepartmentUserRole::query()
            ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
            ->where('department_user_roles.user_id', $user->id)
            ->where('department_user_roles.department_id', $department->id)
            ->where('roles.name', 'lab_technician')
            ->where('roles.guard_name', 'sanctum')
            ->exists();
    }

    /**
     * Start a work session for the authenticated technician using an order QR code.
     *
     * @throws ModelNotFoundException
     */
    public function startTaskByQr(User $user, string $qr): TaskWorkSession
    {
        $order = Order::query()->where('qr_code', $qr)->firstOrFail();

        $task = Task::query()
            ->where('order_id', $order->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['assigned'])
            ->firstOrFail();

        return DB::transaction(function () use ($task, $order) {
            $session = TaskWorkSession::query()->create([
                'task_id' => $task->id,
                'start_time' => now(),
                'status' => 'active',
            ]);

            $task->status = TaskStatus::IN_PROGRESS;
            $task->save();

            $order->status = OrderStatus::IN_PROGRESS;
            $order->save();

        
            if ($this->tasks->isFirstTaskForOrder($task)) {
                $this->orderNotificationService->notifyOrderProcessingStarted($order, 'technician_qr_scan');
            }

            return $session;
        });
    }

    /**
     * Finish the currently active work session for the authenticated technician using an order QR code.
     *
     * @throws ModelNotFoundException
     */
    public function finishTaskByQr(User $user, string $qr): TaskWorkSession
    {
        $order = Order::query()->where('qr_code', $qr)->firstOrFail();

        $task = Task::query()
            ->where('order_id', $order->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['in_progress'])
            ->firstOrFail();

        return DB::transaction(function () use ($task) {
            $session = TaskWorkSession::query()
                ->where('task_id', $task->id)
                ->whereNull('end_time')
                ->where('status', 'active')
                ->latest('id')
                ->firstOrFail();

            $session->end_time = now();
            $session->status = 'completed';
            $session->save();

            $task->status = TaskStatus::PENDING_REVIEW;
            $task->save();

            return $session;
        });
    }

    public function paginateDepartmentTasks(User $user, Department $department, ?string $status, ?string $priority, int $perPage = 15): LengthAwarePaginator
    {
        return $this->tasks->paginateForDepartmentAndUser($user, $department, $status, $priority, $perPage);
    }
}
