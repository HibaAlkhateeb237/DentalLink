<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class DoctorOrdersResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $orders = $this->relationLoaded('orders') ? $this->orders : collect();
        $allOrders = $this->relationLoaded('allOrders') ? $this->allOrders : $orders;

        $totalCost = $allOrders->sum('price');
        $totalPayments = $allOrders->sum(fn ($order): float => $order->relationLoaded('payments')
            ? $order->payments->sum('pivot.amount')
            : 0);
        $totalDue = $allOrders->sum('remaining_amount');

        $profileImage = $this->profile_image;

        if (filled($profileImage) && ! Str::startsWith((string) $profileImage, ['http://', 'https://'])) {
            $publicDiskUrl = rtrim((string) config('filesystems.disks.public.url', ''), '/');
            $profileImage = $publicDiskUrl !== ''
                ? $publicDiskUrl.'/'.ltrim((string) $profileImage, '/')
                : '/storage/'.ltrim((string) $profileImage, '/');
        }

        return [
            'doctor_id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'profile_image' => $profileImage,
            'orders_count' => $allOrders->count(),
            'total_cost' => number_format((float) $totalCost, 2, '.', ''),
            'total_payments' => number_format((float) $totalPayments, 2, '.', ''),
            'total_amount_due' => number_format((float) $totalDue, 2, '.', ''),
            'orders' => $orders->isNotEmpty()
                ? DoctorOrderListItemResource::collection($orders)
                : [],
        ];
    }
}
