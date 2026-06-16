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


            if ($days == 0) {
                $countdownText = "أقل من يوم";
            } elseif ($days == 1) {
                $countdownText = "يوم واحد";
            } elseif ($days == 2) {
                $countdownText = "يومان";
            } elseif ($days <= 10) {
                $countdownText = "{$days} أيام";
            } else {
                $countdownText = "{$days} يوم";
            }
        } else {
            $countdownText = "منتهي / متأخر";
            $days = 0;
        }

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
