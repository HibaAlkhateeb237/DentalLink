<?php

namespace App\Http\Services;

use App\Models\Department;
use App\Models\User;
use App\Repositories\TaskRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class DepartmentManagerTaskService
{
    public function __construct(private TaskRepository $tasks) {}

    /**
     * Get all departments where user has department_manager role
     */
    public function getManagedDepartments(User $user): Collection
    {
        return $this->tasks->getManagedDepartments($user);
    }

    /**
     * Get paginated tasks across all managed departments
     */
    public function paginateManagedTasks(User $user, ?string $status = null, int $perPage = 15, ?string $priority = null): LengthAwarePaginator
    {
        $departmentIds = $this->tasks->getManagedDepartmentIds($user);

        return $this->tasks->paginateByDepartmentIds($departmentIds, $status, $perPage, $priority);
    }

    /**
     * Check if user can view a specific department (is a manager for it)
     */
    public function canViewDepartment(User $user, Department $department): bool
    {
        $ids = $this->tasks->getManagedDepartmentIds($user);

        return in_array($department->id, $ids, true);
    }
}
