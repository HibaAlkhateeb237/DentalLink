<?php

namespace App\Http\Services;

use App\Http\Repositories\OrderTrackingRepository;
use App\Models\Order;
use Carbon\Carbon;

class DoctorOrderTrackingService
{
    protected OrderTrackingRepository $trackingRepository;

    public function __construct(OrderTrackingRepository $trackingRepository)
    {
        $this->trackingRepository = $trackingRepository;
    }

    public function getTrackingDetails(Order $order): array
    {
        $departments = $this->trackingRepository->getDepartmentsWithOrderTasks($order);
        $timeline = [];

        $deliveryDate = $order->delivered_at ? Carbon::parse($order->delivered_at) : null;

        $now = Carbon::now();

        if ($deliveryDate && $deliveryDate->isFuture()) {
            $totalRemainingMinutes = (int) $now->diffInMinutes($deliveryDate);
        } else {

            $totalRemainingMinutes = 0;
        }

        foreach ($departments as $department) {
            $task = $department->tasks->first();

            $allowedMinutes = ((int) ($department->time_allowed ?? 0)) * 60;
            $workedMinutes = $task ? $task->workedMinutes() : 0;

            if ($task && $task->status === 'completed') {
                $stepStatus = 'completed';
                $remainingMinutesForStep = 0;

            } elseif ($task && in_array($task->status, ['assigned', 'in_progress', 'pending_review'])) {
                $stepStatus = 'current';
                $diff = $allowedMinutes - $workedMinutes;
                $remainingMinutesForStep = max($diff, 0);

            } else {

                $stepStatus = 'upcoming';
                $remainingMinutesForStep = $allowedMinutes;

            }

            $timeline[] = [
                'department_id' => $department->id,
                'department_name' => $department->name,
                'step_status' => $stepStatus, // completed, current, upcoming
                'time_allowed_minutes' => $allowedMinutes,
                'worked_minutes' => $workedMinutes,
                'remaining_minutes' => $remainingMinutesForStep,
            ];
        }

        return [
            'order_id' => $order->id,
            'serial_number' => $order->serial_number,
            'patient_name' => $order->patient_name,
            'order_status' => $order->status,
            'total_remaining_minutes' => $totalRemainingMinutes,
            'timeline' => $timeline,
        ];
    }
}
