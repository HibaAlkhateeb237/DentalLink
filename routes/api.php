<?php

// routes/api.php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\LabEmployeeController;
use App\Http\Controllers\LabPortfolioController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json([
        'success' => true,
        'status' => 200,
        'message' => __('messages.success'),
        'data' => [
            'app' => config('app.name'),
        ],
        'errors' => null,
    ]);
});

// ?context=home

Route::middleware(['auth:sanctum', 'role:system_admin'])->prefix('admin/labs')->group(function (): void {
    Route::get('/', [LabController::class, 'adminIndex'])->name('admin.labs.index');
    Route::get('/{lab}', [LabController::class, 'adminShow'])->name('admin.labs.show');
    Route::post('/', [LabController::class, 'store'])->name('admin.labs.store');
    Route::put('/{lab}', [LabController::class, 'update'])->name('admin.labs.update');
    Route::delete('/{lab}', [LabController::class, 'destroy'])->name('admin.labs.destroy');
});

Route::prefix('auth')->group(function (): void {
    Route::post('/register/request-otp', [AuthController::class, 'requestRegisterOtp'])->middleware('throttle:api');
    Route::post('/register/verify-otp', [AuthController::class, 'verifyRegisterOtp'])->middleware('throttle:api');
    Route::post('/register/complete', [AuthController::class, 'completeRegister'])->middleware('throttle:api');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:api');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/me', [AuthController::class, 'updateProfile']);
        Route::delete('/me/profile-image', [AuthController::class, 'removeProfileImage']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/assign-role', [AuthController::class, 'assignRole'])
            ->middleware('permission:users.assign-role');

        Route::get('/labs', [LabController::class, 'index'])->name('labs.index');
        Route::post('/labs/search', [LabController::class, 'search'])->name('labs.search');

        Route::get('/labs/top-rated', [LabController::class, 'topRated'])->name('labs.top-rated');
        Route::get('/labs/nearby', [LabController::class, 'nearby'])->name('labs.nearby');
        Route::get('/labs/suggested', [LabController::class, 'suggested'])->name('labs.suggested');
        Route::get('/labs/most-ordered', [LabController::class, 'most-ordered'])->name('labs.most-ordered');
        Route::get('/labs/{lab}', [LabController::class, 'show'])->name('labs.show');

        Route::middleware(['role:lab_manager'])->prefix('lab/employees')->group(function (): void {
            Route::get('/', [LabEmployeeController::class, 'index'])->name('lab.employees.index');
            Route::post('/', [LabEmployeeController::class, 'store'])->name('lab.employees.store');
            Route::get('/{employee}', [LabEmployeeController::class, 'show'])->name('lab.employees.show');
            Route::put('/{employee}', [LabEmployeeController::class, 'update'])->name('lab.employees.update');
            Route::delete('/{employee}', [LabEmployeeController::class, 'destroy'])->name('lab.employees.destroy');
        });

        Route::get('/labs/{lab}/portfolio', [LabPortfolioController::class, 'index'])->name('labs.portfolio.index');
        Route::post('/labs/{lab}/portfolio', [LabPortfolioController::class, 'store'])->name('labs.portfolio.store');
    });
});
