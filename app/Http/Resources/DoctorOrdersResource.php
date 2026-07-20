<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorOrdersResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $orders = $this->relationLoaded('orders') ? $this->orders : collect();

        $totalCost = $orders->sum('price');
        $totalPayments = $orders->sum(fn ($order): float => $order->relationLoaded('payments')
            ? $order->payments->sum('pivot.amount')
            : 0);
        $totalDue = $orders->sum('remaining_amount');

        return [
            'doctor_id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'orders_count' => $orders->count(),
            'total_cost' => number_format((float) $totalCost, 2, '.', ''),
            'total_payments' => number_format((float) $totalPayments, 2, '.', ''),
            'total_amount_due' => number_format((float) $totalDue, 2, '.', ''),
            'orders' => $orders->isNotEmpty()
                ? DoctorOrderListItemResource::collection($orders)
                : [],
        ];
    }
}
