<?php

namespace App\Http\Repositories;

use App\Models\Order;
use App\Models\Department;
use Illuminate\Database\Eloquent\Collection;

class OrderTrackingRepository
{

    public function getDepartmentsWithOrderTasks(Order $order): Collection
    {
        return Department::query()
            ->where('lab_id', $order->lab_id)
            ->where('sort_order', '>', 0)
            ->with([
                'tasks' => function ($query) use ($order) {
                    $query->where('order_id', $order->id)
                        ->select(['id', 'order_id', 'department_id', 'status', 'approved_at']);
                },
                'tasks.workSessions' => function ($query) {
                    $query->select(['id', 'task_id', 'start_time', 'end_time', 'status']);
                }
            ])
            ->orderBy('sort_order', 'asc')
            ->get();
    }
}
