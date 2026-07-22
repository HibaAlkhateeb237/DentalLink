<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Broadcast::channel('tracking.order.{orderId}', function (User $user, int $orderId): bool {
    if ($user->hasRole('system_admin')) {
        return true;
    }

    $order = Order::find($orderId);

    if ($order === null) {
        return false;
    }

    return $order->user_id === $user->id;
});
