<?php

namespace App\Http\Controllers;

use App\Http\Services\TaskWorkflowService;
use App\Models\Task;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TaskWorkflowController extends Controller
{
    protected TaskWorkflowService $workflowService;
    protected ApiResponse $response;

    public function __construct(TaskWorkflowService $workflowService, ApiResponse $response)
    {
        $this->workflowService = $workflowService;
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
}
