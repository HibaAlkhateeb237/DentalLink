<?php

namespace App\Http\Services;

use App\Models\Task;
use App\Support\TaskStatus;
use App\Support\OrderStatus;
use App\Repositories\TaskRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;
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

        if (!$isAuthorized) {
            throw new HttpException(403,'عذراً، ليس لديك صلاحية إدارة أو نقل المهام الخاصة بهذا القسم.');
        }


        if ($task['status'] !== TaskStatus::PENDING_REVIEW) {
            throw new HttpException(400, 'هذه المهمة ليست في حالة بحاجة لتقييم حالياً.');
        }

        try {
        return DB::transaction(function () use ($task) {

            $this->taskRepository->completeTask($task);


            $nextDepartment = $this->taskRepository->findNextDepartment($task['department']);

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
                'status' => OrderStatus::COMPLETED
            ]);

          /*  return [
                'is_order_completed' => true,
                'next_department' => null,
                'task' => null
            ];*/

            // إرجاع رسالة اكتمال الطلب كلياً
            return "تم إنهاء المرحلة الأخيرة بنجاح، والطلب الآن مكتمل بالكامل وجاهز للتسليم!";
        });
            }catch (Exception $e) {

            throw new HttpException(500, 'حدث خطأ أثناء معالجة سير العمل: ' . $e->getMessage());
        }
    }





}
