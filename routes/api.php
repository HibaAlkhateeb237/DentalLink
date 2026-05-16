<?php

// routes/api.php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\LabEmployeeController;
use App\Http\Controllers\LabPortfolioController;
use App\Http\Controllers\LabPricingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderPricingController;
use App\Http\Controllers\ReceptionistOrderController;
use App\Http\Controllers\ToothShadeController;
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
    Route::get('/inactive', [LabController::class, 'inactiveLabs'])->name('admin.labs.inactive');
    Route::get('/{lab}', [LabController::class, 'adminShow'])->name('admin.labs.show');
    Route::post('/', [LabController::class, 'store'])->name('admin.labs.store');
    Route::post('/{lab}', [LabController::class, 'update'])->name('admin.labs.update');
    Route::delete('/{lab}', [LabController::class, 'destroy'])->name('admin.labs.destroy');
});

Route::prefix('auth')->group(function (): void {
    Route::post('/register/request-otp', [AuthController::class, 'requestRegisterOtp'])->middleware('throttle:api');
    Route::post('/register/verify-otp', [AuthController::class, 'verifyRegisterOtp'])->middleware('throttle:api');
    Route::post('/register/complete', [AuthController::class, 'completeRegister'])->middleware('throttle:api');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:api');

    Route::get('/top-rated', [LabController::class, 'topRated'])->name('labs.top-rated');


    Route::middleware('auth:sanctum')->group(function (): void {

        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/roles', [AuthController::class, 'roles']);
        Route::post('/me', [AuthController::class, 'updateProfile']);
        Route::delete('/me/profile-image', [AuthController::class, 'removeProfileImage']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/assign-role', [AuthController::class, 'assignRole'])
            ->middleware('permission:users.assign-role');

        Route::get('/labs/inactive', [LabController::class, 'inactiveLabs'])
            ->middleware('role:system_admin')
            ->name('labs.inactive');

        // -----------------------------Doctor-------------------------------------------------------

        Route::middleware(['role:doctor,system_admin'])->prefix('labs')->group(function (): void {
            Route::get('/', [LabController::class, 'index'])->name('labs.index');
            Route::post('/search', [LabController::class, 'search'])->name('labs.search');
            Route::get('/top-rated', [LabController::class, 'topRated'])->name('labs.top-rated');
            Route::get('/nearby', [LabController::class, 'nearby'])->name('labs.nearby');
            Route::get('/suggested', [LabController::class, 'suggested'])->name('labs.suggested');
            Route::get('/most-ordered', [LabController::class, 'mostOrdered'])->name('labs.most-ordered');

            Route::get('/{lab}', [LabController::class, 'show'])->name('labs.show');

            Route::get('/{lab}/portfolio', [LabPortfolioController::class, 'index'])->name('labs.portfolio.index');
            Route::post('/{lab}/portfolio', [LabPortfolioController::class, 'store'])->name('labs.portfolio.store');
            Route::get('/{lab}/pricing', [LabPricingController::class, 'show'])->name('labs.pricing.show');
        });

        Route::get('/tooth-shades', [ToothShadeController::class, 'index'])->name('tooth-shades.index');

        Route::middleware(['role:doctor'])->prefix('doctor/orders')->group(function (): void {
            Route::post('/', [OrderController::class, 'store'])->name('doctor.orders.store');
            Route::get('/', [OrderController::class, 'index'])->name('doctor.orders.index');
            Route::get('/{order}', [OrderController::class, 'show'])->name('doctor.orders.show');
            // for QR
            Route::get('/qr/{order:qr_code}', [OrderController::class, 'show'])->name('doctor.orders.show-qr');

            //  Route::post('/{order}/pricing/calculate', [OrderPricingController::class, 'calculate'])->name('orders.pricing.calculate');
        });


        // ----------------------------------Doctor-----------------------------------------------------------------

        Route::middleware(['role:receptionist'])->prefix('orders')->group(function (): void {
            Route::get('/', [ReceptionistOrderController::class, 'index'])->name('orders.index');
            Route::get('/{order}', [ReceptionistOrderController::class, 'show'])->name('orders.show');
            Route::post('/{order}/resubmission', [ReceptionistOrderController::class, 'markForResubmission'])
                ->name('orders.resubmission.store');
        });

        Route::middleware(['role:lab_manager'])->prefix('lab/employees')->group(function (): void {
            Route::get('/', [LabEmployeeController::class, 'index'])->name('lab.employees.index');
            Route::post('/', [LabEmployeeController::class, 'store'])->name('lab.employees.store');
            Route::get('/{employee}', [LabEmployeeController::class, 'show'])->name('lab.employees.show');
            Route::post('/{employee}', [LabEmployeeController::class, 'update'])->name('lab.employees.update');
            Route::delete('/{employee}', [LabEmployeeController::class, 'destroy'])->name('lab.employees.destroy');
        });

        Route::middleware(['role:lab_manager'])->prefix('lab/departments')->group(function (): void {
            Route::get('/', [DepartmentController::class, 'index'])->name('lab.departments.index');
            Route::get('/with-employees/list', [DepartmentController::class, 'indexWithEmployees'])->name('lab.departments.with-employees.index');
            Route::post('/', [DepartmentController::class, 'store'])->name('lab.departments.store');
            Route::post('/bulk', [DepartmentController::class, 'bulkStore'])->name('lab.departments.bulk.store');
            Route::get('/{department}', [DepartmentController::class, 'show'])->name('lab.departments.show');
            Route::get('/{department}/with-employees', [DepartmentController::class, 'showWithEmployees'])->name('lab.departments.with-employees.show');
            Route::put('/{department}', [DepartmentController::class, 'update'])->name('lab.departments.update');
            Route::delete('/{department}', [DepartmentController::class, 'destroy'])->name('lab.departments.destroy');
        });
    });
});
