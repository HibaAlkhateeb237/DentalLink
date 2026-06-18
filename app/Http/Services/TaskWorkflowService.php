<?php

namespace App\Http\Services;

use App\Models\Task;
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
    protected TaskRepository $taskRepository;

    public function __construct(TaskRepository $taskRepository)
    {
        $this->taskRepository = $taskRepository;
    }

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
            return DB::transaction(function () use ($task) {

                $this->taskRepository->completeTask($task);

                $nextDepartment = $this->taskRepository->findNextDepartment($task['department'], $task['order_id']);

                if ($nextDepartment) {

                    $newTask = $this->taskRepository->createNextStageTask($task['order_id'], $nextDepartment['id']);

                    /*   return [
                           'is_order_completed' => false,
                           'next_department' => $nextDepartment['name'],
                           'task' => $newTask
                       ];*/

                    return "تم إنهاء المهمة ونقلها إلى القسم التالي: ({$nextDepartment['name']}).";

                }

                $task->order()->update([
                    'status' => OrderStatus::COMPLETED,
                ]);

                /*  return [
                      'is_order_completed' => true,
                      'next_department' => null,
                      'task' => null
                  ];*/

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
            return DB::transaction(function () use ($task) {

                $task->update(['status' => TaskStatus::ASSIGNED]);

                $this->taskRepository->markLastSessionAsReturned($task['id']);

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
            return DB::transaction(function () use ($task, $technicianId) {
                $this->taskRepository->assignTechnicianToTask($task, $technicianId);

                return 'تم تعيين الفني بنجاح، والمهمة الآن جاهزة لبدء العمل عليها.';
            });
        } catch (Exception $e) {
            if ($e instanceof HttpException) {
                throw $e;
            }
            throw new HttpException(500, 'حدث خطأ أثناء عملية التعيين: '.$e->getMessage());
        }
    }
}
