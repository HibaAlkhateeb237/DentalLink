<?php

namespace App\Repositories;

use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
    public function paginateByDepartmentIds(array $departmentIds, ?string $status, int $perPage = 15, ?string $priority = null): LengthAwarePaginator
    {
        $statuses = $status === null ? [ 'pending_assignment','assigned', 'in_progress','pending_review', 'completed'] : [$status];

        return Task::query()
            ->select(['id', 'order_id', 'department_id', 'user_id', 'approved_at', 'status', 'created_at'])
            ->with([
                'department:id,name,time_allowed',
                'order:id,user_id,priority,dental_compensation_type_price_id,serial_number,case_type,notes,patient_name,received_at,delivered_at',
                'order.user:id,name',
                'order.dentalCompensationTypePrice:id,dental_compensation_type_id',
                'order.dentalCompensationTypePrice.dentalCompensationType:id,name,code',
                'workSessions:id,task_id,start_time,end_time,status',
                'user:id,name',
            ])
            ->whereIn('department_id', $departmentIds)
            ->whereIn('status', $statuses)

            ->when($priority, function ($query) use ($priority) {
                return $query->whereHas('order', function ($q) use ($priority) {
                    $q->where('priority', $priority);
                });
            })

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











    public function isUserAdminOfDepartment(int $userId, int $departmentId): bool
    {

        return DB::table('department_user_roles')
            ->join('roles', 'department_user_roles.role_id', '=', 'roles.id')
            ->where('department_user_roles.user_id', $userId)
            ->where('department_user_roles.department_id', $departmentId)
            ->where('roles.name', 'department_manager')
            ->exists();
    }




    public function findNextDepartment(Department $currentDepartment): ?Department
    {
        return Department::query()->where('lab_id', $currentDepartment['lab_id'])
            ->where('sort_order', '>', $currentDepartment['sort_order'])
            ->where('sort_order', '>', 0)
            ->orderBy('sort_order', 'asc')
            ->first();
    }


    public function completeTask(Task $task): void
    {
        $task->update([
            'status' => TaskStatus::COMPLETED,
            'approved_at' => now(),
        ]);
    }


    public function createNextStageTask(int $orderId, int $nextDepartmentId): Task
    {
        return Task::query()->create([
            'order_id'      => $orderId,
            'department_id' => $nextDepartmentId,
            'user_id'       => null,
            'status'        => TaskStatus::PENDING_ASSIGNMENT,
        ]);
    }







    public function findPreviousDepartment(Department $currentDepartment): ?Department
    {
        return Department::query()->where('lab_id', $currentDepartment['lab_id'])
            ->where('sort_order', '<', $currentDepartment['sort_order'])
            ->where('sort_order', '>', 0)
            ->orderBy('sort_order', 'desc')
            ->first();
    }


    public function markLastSessionAsReturned(int $taskId): void
    {
        DB::table('task_work_sessions')
            ->where('task_id', $taskId)
            ->latest('id')
            ->take(1)
            ->update([
                'status' => 'returned',
                'note' => "تم إرجاع المرحلة من قبل الإدارة"
            ]);
    }





    public function getTechniciansByDepartment(int $departmentId): \Illuminate\Support\Collection
    {
        return DB::table('department_user_roles')
            ->join('users', 'department_user_roles.user_id', '=', 'users.id')
            ->join('roles', 'department_user_roles.role_id', '=', 'roles.id')
            ->where('department_user_roles.department_id', $departmentId)
            ->where('roles.name', 'lab_technician')
            ->select('users.id', 'users.name', 'users.email')
            ->get();
    }







    public function assignTechnicianToTask(Task $task, int $technicianId): void
    {
        $task->update([
            'user_id' => $technicianId,
            'status'  => TaskStatus::ASSIGNED,
        ]);
    }





}
