<?php

namespace App\Support\Authorization;

use App\Models\DeliveryTask;
use App\Models\Department;
use App\Models\Task;
use Illuminate\Http\Request;

final class DepartmentScope
{
    public static function resolveDepartmentId(Request $request): ?int
    {
        $department = $request->route('department');

        if ($department instanceof Department) {
            return $department->id;
        }

        if (is_numeric($department)) {
            return (int) $department;
        }

        $task = $request->route('task');

        if ($task instanceof Task) {
            return $task->department_id;
        }

        $deliveryTask = $request->route('deliveryTask');

        if ($deliveryTask instanceof DeliveryTask) {
            return $deliveryTask->order?->tasks()->value('department_id');
        }

        $departmentId = $request->route('department_id') ?? $request->input('department_id');

        if (is_numeric($departmentId)) {
            return (int) $departmentId;
        }

        return null;
    }
}
