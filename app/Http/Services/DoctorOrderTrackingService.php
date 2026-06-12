<?php

namespace App\Http\Services;

use App\Models\Order;
use App\Http\Repositories\OrderTrackingRepository;

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
        $totalRemainingMinutes = 0;
        $hasFoundCurrent = false;

        foreach ($departments as $department) {
            $task = $department->tasks->first();

            $allowedMinutes = ((int) ($department->time_allowed ?? 0)) * 60;
            $workedMinutes = $task ? $task->workedMinutes() : 0;


            if ($task && $task->status === 'completed') {
                $stepStatus = 'completed';
                $remainingMinutesForStep = 0;
            } elseif ($task && in_array($task->status, ['assigned', 'in_progress', 'pending_review'])) {
                $stepStatus = 'current';
                $hasFoundCurrent = true;


                $diff = $allowedMinutes - $workedMinutes;
                $remainingMinutesForStep = max($diff, 0);
                $totalRemainingMinutes += $remainingMinutesForStep;
            } else {

                $stepStatus = 'upcoming';
                $remainingMinutesForStep = $allowedMinutes;


                    $totalRemainingMinutes += $remainingMinutesForStep;

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
