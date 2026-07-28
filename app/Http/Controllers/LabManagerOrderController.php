<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabManagerOrderDepartmentRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Department;
use App\Models\Lab;
use App\Models\Order;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LabManagerOrderController extends Controller
{
    public function __construct(
        private ApiResponse $apiResponse,
    ) {}

    public function setDepartmentRoute(LabManagerOrderDepartmentRequest $request): JsonResponse
    {
        $labId = $request->getManagerLabId();
        $departmentIds = $request->validated('department_ids');
        $departmentTimes = $request->validated('department_time_allowed_hours');

        $timeAllowedByDepartmentId = collect($departmentIds)
            ->values()
            ->mapWithKeys(static fn (int $departmentId, int $index): array => [
                $departmentId => (int) ($departmentTimes[$index] ?? 0),
            ]);

        $departments = DB::transaction(function () use ($labId, $departmentIds, $timeAllowedByDepartmentId) {
            $matchedDepartments = Department::query()
                ->where('lab_id', $labId)
                ->whereIn('id', $departmentIds)
                ->get()
                ->keyBy('id');

            collect($departmentIds)->each(function (int $departmentId, int $index) use ($matchedDepartments, $timeAllowedByDepartmentId): void {
                $department = $matchedDepartments->get($departmentId);

                if ($department !== null) {
                    $department->update([
                        'sort_order' => $index + 1,
                        'time_allowed' => (int) ($timeAllowedByDepartmentId->get($departmentId) ?? 0),
                    ]);
                }
            });

            // Reset sort_order for departments NOT in the new list (remove from workflow)
            Department::query()
                ->where('lab_id', $labId)
                ->whereNotIn('id', $departmentIds)
                ->where('sort_order', '>', 0)
                ->update(['sort_order' => 0]);

            return Department::query()
                ->select(['id', 'name', 'sort_order', 'time_allowed'])
                ->where('lab_id', $labId)
                ->whereIn('id', $departmentIds)
                ->orderBy('sort_order')
                ->get();
        });

        $totalEstimatedTimeHours = $departments->sum(
            static fn (Department $department): int => max(0, (int) ($department->time_allowed ?? 0))
        );

        return $this->apiResponse->success(
            [
                'total_departments_updated' => count($departmentIds),
                'department_route' => $departmentIds,
                'total_estimated_time_hours' => $totalEstimatedTimeHours,
                'departments' => $departments->map(static fn (Department $department): array => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'sort_order' => $department->sort_order,
                    'time_allowed_hours' => max(0, (int) ($department->time_allowed ?? 0)),
                ])->values(),
            ],
            __('orders.department_route_set_successfully'),
            200,
        );
    }

    public function getDepartmentRoute(Order $order): JsonResponse
    {
        $order->loadMissing('lab');

        $departments = Department::query()
            ->select(['id', 'name', 'sort_order', 'time_allowed'])
            ->where('lab_id', $order->lab_id)
            ->where('sort_order', '>', 0)
            ->where('is_management', false)
            ->orderBy('sort_order')
            ->get();

        $departmentsWithStatus = $departments->map(function (Department $department) use ($order) {
            $task = Task::query()
                ->where('order_id', $order->id)
                ->where('department_id', $department->id)
                ->first();

            return [
                'id' => $department->id,
                'name' => $department->name,
                'sort_order' => $department->sort_order,
                'time_allowed_hours' => max(0, (int) ($department->time_allowed ?? 0)),
                'task' => $task ? [
                    'id' => $task->id,
                    'status' => $task->status,
                    'user_id' => $task->user_id,
                    'approved_at' => $task->approved_at,
                ] : null,
                'is_current' => $task && in_array($task->status, ['assigned', 'in_progress'], true),
                'is_completed' => $task && $task->status === 'completed',
            ];
        })->values();

        $totalEstimatedTimeHours = $departments->sum(
            static fn (Department $department): int => max(0, (int) ($department->time_allowed ?? 0))
        );

        return $this->apiResponse->success(
            [
                'order_id' => $order->id,
                'order_serial' => $order->serial_number,
                'lab_id' => $order->lab_id,
                'total_departments' => $departments->count(),
                'total_estimated_time_hours' => $totalEstimatedTimeHours,
                'departments' => $departmentsWithStatus,
            ],
            __('orders.department_route_retrieved_successfully'),
            200,
        );
    }

    public function getLabDepartmentRoute(): JsonResponse
    {
        $user = request()->user();

        // First check department-scoped roles (lab_manager, receptionist, department_manager, lab_technician)
        $user->loadMissing('departmentUserRoles.department.lab');

        $labIds = $user->departmentUserRoles
            ->map(fn ($dur) => $dur->department?->lab_id)
            ->filter()
            ->unique();

        // If no department roles, check for global roles like system_admin with lab access
        if ($labIds->isEmpty()) {
            // system_admin might have access to all labs or specific labs
            if ($user->hasRole('system_admin')) {
                // For system_admin, get lab from request or return all labs with routes
                $labId = request()->query('lab_id');
                if ($labId) {
                    $labIds = collect([(int) $labId]);
                } else {
                    // Return all labs with their department routes
                    return $this->getAllLabsDepartmentRoutes();
                }
            }

            // Check if user has lab_name field (legacy)
            if (filled($user->lab_name)) {
                $lab = Lab::query()->where('name', $user->lab_name)->first();
                if ($lab) {
                    $labIds = collect([$lab->id]);
                }
            }
        }

        if ($labIds->isEmpty()) {
            return $this->apiResponse->error(__('orders.no_lab_access'), 403);
        }

        $labId = $labIds->first();

        $departments = Department::query()
            ->select(['id', 'name', 'sort_order', 'time_allowed'])
            ->where('lab_id', $labId)
            ->where('sort_order', '>', 0)
            ->where('is_management', false)
            ->orderBy('sort_order')
            ->get();

        // Check which departments have been assigned to at least one order (have tasks)
        $departmentsWithOrderAssignment = Task::query()
            ->join('orders', 'orders.id', '=', 'tasks.order_id')
            ->where('orders.lab_id', $labId)
            ->whereIn('tasks.department_id', $departments->pluck('id'))
            ->distinct('tasks.department_id')
            ->pluck('tasks.department_id')
            ->toArray();

        $totalEstimatedTimeHours = $departments->sum(
            static fn (Department $department): int => max(0, (int) ($department->time_allowed ?? 0))
        );

        return $this->apiResponse->success(
            [
                'lab_id' => $labId,
                'total_departments' => $departments->count(),
                'total_estimated_time_hours' => $totalEstimatedTimeHours,
                'departments' => $departments->map(static fn (Department $department): array => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'sort_order' => $department->sort_order,
                    'time_allowed_hours' => max(0, (int) ($department->time_allowed ?? 0)),
                    'in_order_workflow' => in_array($department->id, $departmentsWithOrderAssignment, true),
                ])->values(),
            ],
            __('orders.lab_department_route_retrieved_successfully'),
            200,
        );
    }

    private function getAllLabsDepartmentRoutes(): JsonResponse
    {
        $labs = Lab::query()->with(['departments' => function ($query) {
            $query->select(['id', 'lab_id', 'name', 'sort_order', 'time_allowed'])
                ->where('sort_order', '>', 0)
                ->where('is_management', false)
                ->orderBy('sort_order');
        }])->get();

        $labsWithRoutes = $labs->map(function ($lab) {
            $departments = $lab->departments;

            // Check which departments have been assigned to at least one order
            $departmentsWithOrderAssignment = Task::query()
                ->join('orders', 'orders.id', '=', 'tasks.order_id')
                ->where('orders.lab_id', $lab->id)
                ->whereIn('tasks.department_id', $departments->pluck('id'))
                ->distinct('tasks.department_id')
                ->pluck('tasks.department_id')
                ->toArray();

            $totalEstimatedTimeHours = $departments->sum(
                static fn (Department $department): int => max(0, (int) ($department->time_allowed ?? 0))
            );

            return [
                'lab_id' => $lab->id,
                'lab_name' => $lab->name,
                'total_departments' => $departments->count(),
                'total_estimated_time_hours' => $totalEstimatedTimeHours,
                'departments' => $departments->map(static function (Department $department) use ($departmentsWithOrderAssignment): array {
                    return [
                        'id' => $department->id,
                        'name' => $department->name,
                        'sort_order' => $department->sort_order,
                        'time_allowed_hours' => max(0, (int) ($department->time_allowed ?? 0)),
                        'in_order_workflow' => in_array($department->id, $departmentsWithOrderAssignment, true),
                    ];
                })->values(),
            ];
        });

        return $this->apiResponse->success(
            [
                'labs' => $labsWithRoutes,
            ],
            __('orders.all_labs_department_routes_retrieved_successfully'),
            200,
        );
    }
}
