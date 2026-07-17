<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/orders/{order}', function ($order) {
    $payment = request()->get('payment');
    $sessionId = request()->get('session_id');

    return response()->json([
        'success' => true,
        'message' => match ($payment) {
            'success' => 'Payment completed successfully.',
            'cancelled' => 'Payment was cancelled.',
            default => 'Payment status unknown.',
        },
        'data' => [
            'order_id' => $order,
            'session_id' => $sessionId,
        ],
    ]);
})->name('payment.success');
