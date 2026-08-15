<?php

namespace App\Http\Services;

use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use App\Notifications\PatientCase\CaseTransferred;
use App\Notifications\Task\TaskAssigned;
use App\Repositories\TaskRepository;
use App\Support\OrderStatus;
use App\Support\TaskStatus;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TaskWorkflowService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private OrderNotificationService $orderNotificationService,
        private readonly SystemLogService $systemLogs
    ) {}

    public function moveForward(Task $task): string
    {

        $currentUserId = Auth::id();

        $isAuthorized = $this->taskRepository->isUserAdminOfDepartment($currentUserId, $task['department_id']);

        if (! $isAuthorized) {
            throw new HttpException(403, 'عذراً، ليس لديك صلاحية إدارة أو نقل المهام الخاصة بهذا القسم.');
        }

        if ($task['status'] !== TaskStatus::PENDING_REVIEW) {
            throw new HttpException(400, 'هذه المهمة ليست في حالة بحاجة لتقييم حالياً.');
        }

        try {
            return DB::transaction(function () use ($task, $currentUserId) {
                $task->loadMissing('order');
                $labId = $task->order?->lab_id;

                $this->taskRepository->completeTask($task);

                $currentDepartment = $task['department'];
                $nextDepartment = $this->taskRepository->findNextDepartment($task['department'], $task['order_id']);

                if ($nextDepartment) {

                    $newTask = $this->taskRepository->createNextStageTask($task['order_id'], $nextDepartment['id']);
                    $this->notifyDoctorsAboutCaseTransfer($task, $currentDepartment, $nextDepartment);
                    $this->orderNotificationService->notifyDepartmentManagerAboutUrgentCase($newTask);

                    $this->systemLogs->info(
                        'task.moved_forward',
                        "Task #{$task->id} moved forward to department {$nextDepartment['name']}",
                        [
                            'task_id' => $task->id,
                            'order_id' => $task->order_id,
                            'from_department_id' => $task->department_id,
                            'to_department_id' => $nextDepartment['id'],
                        ],
                        $labId,
                        $currentUserId,
                    );

                    return "تم إنهاء المهمة ونقلها إلى القسم التالي: ({$nextDepartment['name']}).";
                }

                $task->order()->update([
                    'status' => OrderStatus::COMPLETED,
                ]);

                $this->orderNotificationService->notifyOrderCompleted($task->order);

                $this->systemLogs->info(
                    'order.completed',
                    "Order #{$task->order_id} completed",
                    [
                        'order_id' => $task->order_id,
                        'task_id' => $task->id,
                    ],
                    $labId,
                    $currentUserId,
                );

                return 'تم إنهاء المرحلة الأخيرة بنجاح، والطلب الآن مكتمل بالكامل وجاهز للتسليم!';
            });
        } catch (Exception $e) {

            throw new HttpException(500, 'حدث خطأ أثناء معالجة سير العمل: '.$e->getMessage());
        }
    }

    public function moveBackward(Task $task): string
    {
        $currentUserId = Auth::id();

        $isAuthorized = $this->taskRepository->isUserAdminOfDepartment($currentUserId, $task['department_id']);
        if (! $isAuthorized) {
            throw new HttpException(403, 'عذراً، ليس لديك صلاحية إدارة أو إرجاع المهام الخاصة بهذا القسم.');
        }

        if ($task['status'] !== TaskStatus::PENDING_REVIEW) {
            throw new HttpException(400, 'لا يمكن إرجاع هذه المهمة لأنها ليست قيد التقييم حالياً.');
        }

        try {
            return DB::transaction(function () use ($task, $currentUserId) {
                $task->loadMissing('order');
                $labId = $task->order?->lab_id;

                $task->update(['status' => TaskStatus::ASSIGNED]);

                $this->taskRepository->markLastSessionAsReturned($task['id']);

                $this->systemLogs->info(
                    'task.moved_backward',
                    "Task #{$task->id} moved backward to assigned",
                    [
                        'task_id' => $task->id,
                        'order_id' => $task->order_id,
                        'department_id' => $task->department_id,
                    ],
                    $labId,
                    $currentUserId,
                );

                return "تم إرجاع المهمة بنجاح إلى الفني المكلّف في قسم ({$task->department->name}) لإعادة العمل.";
            });

        } catch (Exception $e) {
            if ($e instanceof HttpException) {
                throw $e;
            }
            throw new HttpException(500, 'حدث خطأ أثناء معالجة إرجاع الطلب: '.$e->getMessage());
        }
    }

    public function getTechniciansForManager(int $departmentId): Collection
    {
        $currentUserId = Auth::id();

        $isAuthorized = $this->taskRepository->isUserAdminOfDepartment($currentUserId, $departmentId);

        if (! $isAuthorized) {
            throw new HttpException(403, 'عذراً، ليس لديك صلاحية استعراض موظفي هذا القسم.');
        }

        return $this->taskRepository->getTechniciansByDepartment($departmentId);
    }

    public function assignTechnician(Task $task, int $technicianId): string
    {
        $currentUserId = Auth::id();

        $isAuthorized = $this->taskRepository->isUserAdminOfDepartment($currentUserId, $task['department_id']);
        if (! $isAuthorized) {
            throw new HttpException(403, 'عذراً، ليس لديك صلاحية إدارة هذا القسم.');
        }

        if ($task['status'] !== TaskStatus::PENDING_ASSIGNMENT) {
            throw new HttpException(400, 'لا يمكن إسناد هذه المهمة في حالتها الحالية.');
        }

        $isTechInDept = DB::table('department_user_roles')
            ->where('department_id', $task['department_id'])
            ->where('user_id', $technicianId)
            ->exists();

        if (! $isTechInDept) {
            throw new HttpException(400, 'الموظف المختار لا ينتمي إلى هذا القسم.');
        }

        try {
            return DB::transaction(function () use ($task, $technicianId, $currentUserId) {
                $task->loadMissing('order');
                $labId = $task->order?->lab_id;

                $this->taskRepository->assignTechnicianToTask($task, $technicianId);

                $technician = User::find($technicianId);
                if ($technician) {
                    $technician->notify(new TaskAssigned($task->fresh()));
                }

                $this->systemLogs->info(
                    'task.assigned',
                    "Task #{$task->id} assigned to technician #{$technicianId}",
                    [
                        'task_id' => $task->id,
                        'order_id' => $task->order_id,
                        'department_id' => $task->department_id,
                        'technician_id' => $technicianId,
                    ],
                    $labId,
                    $currentUserId,
                );

                return 'تم تعيين الفني بنجاح، والمهمة الآن جاهزة لبدء العمل عليها.';
            });
        } catch (Exception $e) {
            if ($e instanceof HttpException) {
                throw $e;
            }
            throw new HttpException(500, 'حدث خطأ أثناء عملية التعيين: '.$e->getMessage());
        }
    }

    public function notifyDoctorsAboutCaseTransfer(Task $task, Department $fromDepartment, Department $toDepartment): void
    {
        $order = $task->order;
        $doctor = $order->user;
        if ($doctor) {
            $doctor->notify(
                new CaseTransferred(
                    $order,
                    $fromDepartment->name,
                    $toDepartment->name
                )
            );
        }
    }
}
