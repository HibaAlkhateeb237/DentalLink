<?php

// routes/api.php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
    ]);
});

Route::prefix('auth')->group(function (): void {
    Route::post('/register/request-otp', [AuthController::class, 'requestRegisterOtp'])->middleware('throttle:api');
    Route::post('/register/verify-otp', [AuthController::class, 'verifyRegisterOtp'])->middleware('throttle:api');
    Route::post('/register/complete', [AuthController::class, 'completeRegister'])->middleware('throttle:api');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:api');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/assign-role', [AuthController::class, 'assignRole'])
            ->middleware('permission:users.assign-role');
    });
});
