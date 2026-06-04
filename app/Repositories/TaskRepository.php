<?php

namespace App\Repositories;

use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TaskRepository
{
    /**
     * Return department ids for a user with a given role name
     *
     * @return int[]
     */
    public function getManagedDepartmentIds(User $user, string $roleName = 'department_manager'): array
    {
        return DepartmentUserRole::query()
            ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
            ->where('department_user_roles.user_id', $user->id)
            ->where('roles.name', $roleName)
            ->where('roles.guard_name', 'sanctum')
            ->pluck('department_user_roles.department_id')
            ->toArray();
    }

    /**
     * Return Department collection that the user manages
     */
    public function getManagedDepartments(User $user, string $roleName = 'department_manager'): Collection
    {
        $ids = $this->getManagedDepartmentIds($user, $roleName);

        return Department::query()->whereIn('id', $ids)->get();
    }

    /**
     * Paginate tasks by department ids and optional status
     */
    public function paginateByDepartmentIds(array $departmentIds, ?string $status, int $perPage = 15): LengthAwarePaginator
    {
        $statuses = $status === null ? ['assigned', 'in_progress', 'completed'] : [$status];

        return Task::query()
            ->select(['id', 'order_id', 'department_id', 'user_id', 'approved_at', 'status', 'created_at'])
            ->with([
                'department:id,name,time_allowed',
                'order:id,priority,dental_compensation_type_price_id,serial_number,case_type,notes',
                'order.dentalCompensationTypePrice:id,dental_compensation_type_id',
                'order.dentalCompensationTypePrice.dentalCompensationType:id,name,code',
                'workSessions:id,task_id,start_time,end_time,status',
                'user:id,name',
            ])
            ->whereIn('department_id', $departmentIds)
            ->whereIn('status', $statuses)
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Paginate tasks for a specific department and user (technician)
     */
    public function paginateForDepartmentAndUser(User $user, Department $department, ?string $status, int $perPage = 15): LengthAwarePaginator
    {
        $statuses = $status === null ? ['assigned', 'in_progress', 'completed'] : [$status];

        return Task::query()
            ->select(['id', 'order_id', 'department_id', 'user_id', 'approved_at', 'status', 'created_at'])
            ->with([
                'department:id,name,time_allowed',
                'order:id,priority,dental_compensation_type_price_id,serial_number,case_type,notes',
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