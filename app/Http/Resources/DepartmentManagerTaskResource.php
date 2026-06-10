<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class DepartmentManagerTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $departmentTimeAllowedHours = (int) ($this->department?->time_allowed ?? 0);
        $allowedMinutes = max($departmentTimeAllowedHours, 0) * 60;
        $workedMinutes = $this->workedMinutes();
        $remainingMinutes = $allowedMinutes - $workedMinutes;
        $overdueMinutes = $remainingMinutes < 0 ? $remainingMinutes : 0;

        if ($remainingMinutes < 0) {
            $remainingMinutes = 0;
        }
        $overdueMinutes = abs($overdueMinutes);

        // الحقول الأساسية المشتركة بين كل الواجهات
        $data = [
            'id' => $this->id,
            'status' => $this->status,
            'department' => $this->department === null ? null : [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ],
            'order' => $this->order === null ? null : [
                'id' => $this->order->id,
                'serial_number' => $this->order->serial_number,
                'priority' => $this->order->priority,
                'case_type' => $this->order->case_type,
                'material_type' => $this->order->dentalCompensationTypePrice?->dentalCompensationType?->name,
                'patient_name' => $this->order->patient_name,
            ],
        ];


        if ($this->status === 'pending_assignment') {
            $data['assignment_context'] = [

                'doctor_name' => $this->order?->user?->name ?? 'طبيب غير معروف',
                'delivery_date' => $this->order?->delivered_at ? Carbon::parse($this->order->delivered_at)->format('Y-m-d h:i A') : null,
            ];
        }


        if (in_array($this->status, ['assigned', 'in_progress'])) {
            $data['progress_context'] = [
                'technician' => $this->user === null ? null : [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ],
                'notes' => $this->order?->notes,
                'worked_minutes' => $workedMinutes,
                'remaining_minutes' => $remainingMinutes,
                'overdue_minutes' => $overdueMinutes,
                'time_allowed_hours' => $departmentTimeAllowedHours,
            ];
        }


        if ($this->status === 'pending_review') {
            $data['review_context'] = [
                'technician_name' => $this->user?->name,
                'doctor_name' => $this->order?->user?->name ?? 'طبيب غير معروف',
              //  'last_session_note' => $this->workSessions?->last()?->note,

            ];
        }



        if ($this->status === 'completed') {


            $totalMinutes = (int) $workedMinutes;

            $days = floor($totalMinutes / (24 * 60));
            $hours = floor(($totalMinutes % (24 * 60)) / 60);
            $minutes = $totalMinutes % 60;


            $durationParts = [];
            if ($days > 0) $durationParts[] = "{$days} أيام";
            if ($hours > 0) $durationParts[] = "{$hours} ساعة";
            if ($minutes > 0 || empty($durationParts)) $durationParts[] = "{$minutes} دقيقة";

            $totalDurationText = implode(' و ', $durationParts);

            $data['archive_context'] = [
                'completed_at' => $this->approved_at ? Carbon::parse($this->approved_at)->format('Y-m-d') : null,
                'execution_duration' => $totalDurationText,
            ];
        }

        return $data;
    }
}


