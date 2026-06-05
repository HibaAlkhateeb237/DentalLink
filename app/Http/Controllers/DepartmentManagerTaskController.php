<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepartmentManagerTaskIndexRequest;
use App\Http\Resources\DepartmentManagerTaskResource;
use App\Http\Responses\ApiResponse;
use App\Http\Services\DepartmentManagerTaskService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class DepartmentManagerTaskController extends Controller
{
    public function __construct(
        private DepartmentManagerTaskService $taskService,
        private ApiResponse $apiResponse,
    ) {}

    public function index(DepartmentManagerTaskIndexRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->apiResponse->error(__('auth.forbidden'), Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);

        $tasks = $this->taskService->paginateManagedTasks(
            $user,
            $validated['status'] ?? null,
            $perPage,
        );

        // Get managed departments for context
        $managedDepartments = $this->taskService->getManagedDepartments($user)
            ->map(fn ($dept) => [
                'id' => $dept->id,
                'name' => $dept->name,
                'time_allowed_hours' => $dept->time_allowed,
                'time_allowed_minutes' => $dept->time_allowed === null ? null : $dept->time_allowed * 60,
            ]);

        $tasksData = DepartmentManagerTaskResource::collection($tasks->getCollection())->toArray($request);

        return $this->apiResponse->success([
            'departments' => $managedDepartments,
            'tasks' => $tasksData,
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
            ],
        ], __('tasks.retrieved_successfully'), Response::HTTP_OK);
    }
}
