<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorOrderTrackingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $totalMinutes = (int) $this['total_remaining_minutes'];


        if ($totalMinutes > 0) {

            $days = floor($totalMinutes / (60 * 24));


            $remainingMinutesAfterDays = $totalMinutes % (60 * 24);
            $hours = floor($remainingMinutesAfterDays / 60);


            $minutes = $remainingMinutesAfterDays % 60;


            $countdownText = "{$days} أيام و {$hours} ساعة و {$minutes} دقيقة";
        } else {
            $countdownText = "0 أيام و 0 ساعة و 0 دقيقة";
        }


        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;
        $countdownText = sprintf('%02d:%02d:00', $hours, $minutes);


        return [
            'order_id' => $this['order_id'],
            'serial_number' => $this['serial_number'],
            'patient_name' => $this['patient_name'],
            'order_status' => $this['order_status'],
            'remaining_countdown' => $countdownText,
           // 'total_remaining_minutes' => $totalMinutes,
            'steps' => $this['timeline'],
        ];
    }
}
