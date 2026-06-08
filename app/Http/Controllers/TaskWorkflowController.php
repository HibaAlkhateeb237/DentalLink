<?php

namespace App\Http\Controllers;

use App\Http\Services\TaskWorkflowService;
use App\Models\Task;
use App\Http\Responses\ApiResponse;
use App\Repositories\TaskRepository;
use Illuminate\Http\JsonResponse;
use Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TaskWorkflowController extends Controller
{
    protected TaskWorkflowService $workflowService;
    protected ApiResponse $response;

    public function __construct(TaskWorkflowService $workflowService,TaskRepository $taskRepository, ApiResponse $response)
    {
        $this->workflowService = $workflowService;
        $this->taskRepository = $taskRepository;
        $this->response = $response;

    }

    public function moveForward(Task $task): JsonResponse
    {
        try {
            $message = $this->workflowService->moveForward($task);

            return $this->response->success(
                null,
                $message,
            );

        } catch (HttpException $e) {

            return $this->response->error($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {

            return $this->response->error('حدث خطأ غير متوقع في السيرفر', 500);
        }
    }





    public function moveBackward(Task $task): JsonResponse
    {
        try {

            $message = $this->workflowService->moveBackward($task);

            return $this->response->success(null, $message, 200);

        } catch (HttpException $e) {
            return $this->response->error($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            return $this->response->error('حدث خطأ غير متوقع في السيرفر', 500);
        }
    }



    public function getTechnicians(int $departmentId): JsonResponse
    {
        $technicians = $this->workflowService->getTechniciansForManager($departmentId);

        return $this->response->success(
            $technicians,
            'تم جلب قائمة الفنيين للقسم بنجاح.',
            200
        );
    }








}
