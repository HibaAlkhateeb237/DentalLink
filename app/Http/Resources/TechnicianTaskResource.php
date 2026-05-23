<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TechnicianTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $departmentTimeAllowedHours = (int) ($this->department?->time_allowed ?? 0);
        $allowedMinutes = max($departmentTimeAllowedHours, 0) * 60;
        $workedMinutes = $this->workedMinutes();
        $remainingMinutes = $allowedMinutes - $workedMinutes;
        $overdueMinutes = $remainingMinutes < 0 ? $remainingMinutes : 0;

        return [
            'id' => $this->id,
            'status' => $this->status,
            //           'approved_at' => $this->approved_at,
            //            'department' => $this->department === null ? null : [
            //               'id' => $this->department->id,
            //               'name' => $this->department->name,
            //                'time_allowed_hours' => $departmentTimeAllowedHours,
            //            ],
            'order' => $this->order === null ? null : [
                'id' => $this->order->id,
                'priority' => $this->order->priority,
                'material_type' => $this->order->dentalCompensationTypePrice?->dentalCompensationType?->name,
            ],
            'worked_minutes' => $workedMinutes,
            'remaining_minutes' => $remainingMinutes,
            'overdue_minutes' => $overdueMinutes,
        ];
    }
}
