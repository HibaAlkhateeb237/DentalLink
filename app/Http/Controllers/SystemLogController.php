<?php

namespace App\Http\Controllers;

use App\Http\Requests\SystemLogIndexRequest;
use App\Http\Resources\SystemLogResource;
use App\Http\Responses\ApiResponse;
use App\Http\Services\SystemLogService;
use App\Models\DepartmentUserRole;
use App\Models\SystemLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemLogController extends Controller
{
    public function __construct(
        private ApiResponse $apiResponse,
        private SystemLogService $systemLogs
    ) {}

    public function index(SystemLogIndexRequest $request): JsonResponse
    {
        $labId = $this->resolveLabId();

        if (! $labId) {
            return $this->apiResponse->error('Could not resolve lab for the authenticated user.', 403);
        }

        $logs = $this->systemLogs->query([
            'level' => $request->validated('level'),
            'event' => $request->validated('event'),
            'user_id' => $request->validated('user_id'),
            'lab_id' => $labId,
        ])->paginate($request->integer('per_page', 15));

        $resource = SystemLogResource::collection($logs);
        $resourceArray = $resource->response()->getData(true);

        $data = [
            'data' => $resourceArray['data'],
            'total' => $resourceArray['meta']['total'] ?? 0,
            'per_page' => $resourceArray['meta']['per_page'] ?? $request->integer('per_page', 15),
            'current_page' => $resourceArray['meta']['current_page'] ?? 1,
            'last_page' => $resourceArray['meta']['last_page'] ?? 1,
        ];

        return $this->apiResponse->success(
            $data,
            __('system_logs.retrieved_successfully')
        );
    }

    public function show(Request $request, SystemLog $systemLog): JsonResponse
    {
        $labId = $this->resolveLabId();

        if (! $labId || $systemLog->lab_id !== $labId) {
            return $this->apiResponse->error(__('system_logs.not_found'), 404);
        }

        $systemLog->load('user');

        return $this->apiResponse->success(
            new SystemLogResource($systemLog),
            __('system_logs.retrieved_successfully')
        );
    }

    private function resolveLabId(): ?int
    {
        $user = request()->user();

        if ($user->hasRole('system_admin')) {
            return (int) request()->query('lab_id');
        }

        return DepartmentUserRole::query()
            ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
            ->join('departments', 'departments.id', '=', 'department_user_roles.department_id')
            ->where('department_user_roles.user_id', $user->id)
            ->where('roles.name', 'lab_manager')
            ->where('roles.guard_name', 'sanctum')
            ->value('departments.lab_id');
    }
}
