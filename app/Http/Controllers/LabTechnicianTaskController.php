<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabTechnicianTaskIndexRequest;
use App\Http\Resources\TechnicianTaskResource;
use App\Http\Responses\ApiResponse;
use App\Http\Services\LabTechnicianTaskService;
use App\Models\Department;
use App\Models\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class LabTechnicianTaskController extends Controller
{
    public function __construct(
        private LabTechnicianTaskService $taskService,
        private ApiResponse $apiResponse,
    ) {}

    public function index(LabTechnicianTaskIndexRequest $request, Department $department): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $this->taskService->canViewDepartment($user, $department)) {
            return $this->apiResponse->error(__('auth.forbidden'), Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);

        $tasks = $this->taskService->paginateDepartmentTasks(
            $user,
            $department,
            $validated['status'] ?? null,
            $validated['priority'] ?? null,
            $perPage,
        );

        $tasksData = TechnicianTaskResource::collection($tasks->getCollection())->toArray($request);

        return $this->apiResponse->success([
            'department' => [
                'id' => $department->id,
                'name' => $department->name,
                'time_allowed_hours' => $department->time_allowed,
                'time_allowed_minutes' => $department->time_allowed === null ? null : $department->time_allowed * 60,
            ],
            'tasks' => $tasksData,
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
            ],
        ], __('tasks.retrieved_successfully'), Response::HTTP_OK);
    }

    public function startByQr(Request $request, string $qr): JsonResponse
    {
        try {
            $session = $this->taskService->startTaskByQr($request->user(), $qr);

            return $this->apiResponse->success([
                'session' => $session,
            ], __('tasks.started_successfully'), Response::HTTP_OK);
        } catch (ModelNotFoundException $e) {
            return $this->apiResponse->error(__('messages.not_found'), Response::HTTP_NOT_FOUND);
        }
    }

    public function finishByQr(Request $request, string $qr): JsonResponse
    {
        try {
            $session = $this->taskService->finishTaskByQr($request->user(), $qr);

            return $this->apiResponse->success([
                'session' => $session,
            ], __('tasks.finished_successfully'), Response::HTTP_OK);
        } catch (ModelNotFoundException $e) {
            return $this->apiResponse->error(__('messages.not_found'), Response::HTTP_NOT_FOUND);
        }
    }



    public function qrImage(Order $order): Response|JsonResponse
    {
        $path = $order->qr_image_path;

        if (! filled($path) || ! Storage::disk('public')->exists($path)) {
            return $this->apiResponse->error(__('messages.file_not_found'), 404);
        }

        return Storage::disk('public')->response($path, 'qr.png', [
            'Content-Type' => 'image/png',
        ]);
    }




}
