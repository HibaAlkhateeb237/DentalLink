<?php

namespace App\Http\Services;

use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LabTechnicianTaskService
{
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

    public function paginateDepartmentTasks(User $user, Department $department, ?string $status, int $perPage = 15): LengthAwarePaginator
    {
        $statuses = $status === null
            ? ['assigned', 'in_progress', 'completed']
            : [$status];

        return Task::query()
            ->select(['id', 'order_id', 'department_id', 'user_id', 'approved_at', 'status', 'created_at'])
            ->with([
                'department:id,name,time_allowed',
                'order:id,priority,dental_compensation_type_price_id',
                'order.dentalCompensationTypePrice:id,dental_compensation_type_id',
                'order.dentalCompensationTypePrice.dentalCompensationType:id,name,code',
                'workSessions:id,task_id,start_time,end_time,status',
            ])
            ->where('department_id', $department->id)
            ->where('user_id', $user->id)
            ->whereIn('status', $statuses)
            ->latest('id')
            ->paginate($perPage);
    }
}
