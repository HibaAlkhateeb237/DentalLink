<?php

namespace App\Http\Services;

use App\Models\Order;
use App\Models\User;
use App\Repositories\OrderRepository;

class OrderService
{
    public function __construct(private OrderRepository $orders) {}

    /**
     * @param  array{lab_id:int,tooth_shade_id:int,dental_compensation_type_id:int,priority:string,order_type?:string,notes?:string|null,teeth:array<int, array{tooth_number:int,notes?:string|null}>}  $validated
     */
    public function createForDoctor(User $doctor, array $validated): Order
    {
        return $this->orders->createOrder($doctor, $validated);
    }
}
