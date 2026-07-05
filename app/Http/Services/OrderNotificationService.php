<?php

namespace App\Http\Services;

use App\Models\Order;
use App\Notifications\Order\OrderCompleted;
use App\Notifications\Order\OrderProcessingStarted;
use App\Repositories\OrderRepository;

class OrderNotificationService
{
    public function __construct(private OrderRepository $orders) {}

    public function notifyOrderProcessingStarted(Order $order, string $triggerType = 'manual'): void
    {
        $doctor = $order->user;
        if ($doctor) {
            $doctor->notify(new OrderProcessingStarted($order, $triggerType));
        }
    }

    public function notifyOrderCompleted(Order $order): void
    {
        $doctor = $order->user;
        if ($doctor) {
            $doctor->notify(new OrderCompleted($order));
        }
    }
}
