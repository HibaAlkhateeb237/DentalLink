<?php

// routes/api.php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DeliveryEmployeeTaskController;
use App\Http\Controllers\DentalCompensationTypeController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DepartmentManagerTaskController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\DoctorBalanceController;
use App\Http\Controllers\DoctorOrdersController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\LabDeliverySettingController;
use App\Http\Controllers\LabEmployeeController;
use App\Http\Controllers\LabManagerOrderController;
use App\Http\Controllers\LabPortfolioController;
use App\Http\Controllers\LabPricingController;
use App\Http\Controllers\LabRoleController;
use App\Http\Controllers\LabTechnicianTaskController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReceptionistDeliveryTaskController;
use App\Http\Controllers\ReceptionistOrderController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TaskWorkflowController;
use App\Http\Controllers\ToothShadeController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json([
        'success' => true,
        'status' => 200,
        'message' => __('messages.success'),
        // use App\Http\Controllers\DentalCompensationTypeController;
        'data' => [
            'app' => config('app.name'),
        ],
        'errors' => null,
    ]);
});

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleEvent'])
    ->name('stripe.webhook');

Route::middleware(['auth:sanctum', 'role:system_admin'])->prefix('admin/labs')->group(function (): void {
    Route::get('/', [LabController::class, 'adminIndex'])->name('admin.labs.index');
    Route::get('/stats', [LabController::class, 'adminStats'])->name('admin.labs.stats');
    Route::get('/inactive', [LabController::class, 'inactiveLabs'])->name('admin.labs.inactive');
    Route::get('/{lab}', [LabController::class, 'adminShow'])->name('admin.labs.show');
    Route::post('/', [LabController::class, 'store'])->name('admin.labs.store');
    Route::put('/{lab}', [LabController::class, 'update'])->name('admin.labs.update');
    Route::delete('/{lab}', [LabController::class, 'destroy'])->name('admin.labs.destroy');

    // Stripe Connect management
    Route::post('/{lab}/stripe/connect', [LabController::class, 'createStripeAccount'])->name('admin.labs.stripe.connect');
    Route::get('/{lab}/stripe/account-link', [LabController::class, 'createAccountLink'])->name('admin.labs.stripe.account-link');
});

// Packages management (system_admin only)
Route::middleware(['auth:sanctum', 'role:system_admin'])->prefix('admin/packages')->group(function (): void {
    Route::get('/', [PackageController::class, 'index'])->name('admin.packages.index');
    Route::post('/', [PackageController::class, 'store'])->name('admin.packages.store');
    Route::get('/{package}', [PackageController::class, 'show'])->name('admin.packages.show');
    Route::put('/{package}', [PackageController::class, 'update'])->name('admin.packages.update');
    Route::delete('/{package}', [PackageController::class, 'destroy'])->name('admin.packages.destroy');
});

Route::prefix('auth')->group(function (): void {
    Route::post('/register/request-otp', [AuthController::class, 'requestRegisterOtp'])->middleware('throttle:api');
    Route::post('/register/verify-otp', [AuthController::class, 'verifyRegisterOtp'])->middleware('throttle:api');
    Route::post('/register/complete', [AuthController::class, 'completeRegister'])->middleware('throttle:api');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:api');

    // for testing
    Route::get('orders/{order}/qr-image/testing', [LabTechnicianTaskController::class, 'qrImage']);

    Route::middleware('auth:sanctum')->group(function (): void {
        // Dental Compensation Types API (lab_manager)
        Route::middleware(['auth:sanctum', 'role:lab_manager,receptionist'])->prefix('lab/compensations')->group(function () {
            Route::get('/', [DentalCompensationTypeController::class, 'index'])->middleware('can:viewAny,App\Models\DentalCompensationType');
            Route::post('/', [DentalCompensationTypeController::class, 'store'])->middleware('can:create,App\Models\DentalCompensationType');
            Route::get('{dental_compensation_type}', [DentalCompensationTypeController::class, 'show'])->middleware('can:view,dental_compensation_type');
            Route::put('{dental_compensation_type}', [DentalCompensationTypeController::class, 'update'])->middleware('can:update,dental_compensation_type');
            Route::delete('{dental_compensation_type}', [DentalCompensationTypeController::class, 'destroy'])->middleware('can:delete,dental_compensation_type');
        });

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

        // Receptionist and lab manager may view and add portfolio photos
        Route::middleware(['role:doctor,system_admin,receptionist,lab_manager'])->get('/labs/{lab}/portfolio', [LabPortfolioController::class, 'index'])
            ->name('labs.portfolio.index');

        Route::middleware(['role:doctor,system_admin,receptionist,lab_manager'])->prefix('labs')->group(function (): void {
            Route::post('/{lab}/portfolio', [LabPortfolioController::class, 'store'])->name('labs.portfolio.store');
        });

        Route::middleware(['role:doctor,system_admin'])->prefix('labs')->group(function (): void {
            Route::get('/', [LabController::class, 'index'])->name('labs.index');
            Route::post('/search', [LabController::class, 'search'])->name('labs.search');
            Route::get('/top-rated', [LabController::class, 'topRated'])->name('labs.top-rated');
            Route::get('/nearby', [LabController::class, 'nearby'])->name('labs.nearby');
            Route::get('/suggested', [LabController::class, 'suggested'])->name('labs.suggested');
            Route::get('/most-ordered', [LabController::class, 'mostOrdered'])->name('labs.most-ordered');

            Route::get('/{lab}', [LabController::class, 'show'])->name('labs.show');

            Route::get('/{lab}/pricing', [LabPricingController::class, 'show'])->name('labs.pricing.show');

            Route::get('/{lab}/materials', [LabPricingController::class, 'materials'])->name('labs.materials.index');
        });

        Route::get('/tooth-shades', [ToothShadeController::class, 'index'])->name('tooth-shades.index');

        Route::middleware(['role:doctor'])->prefix('doctor/orders')->group(function (): void {
            Route::post('/', [OrderController::class, 'store'])->name('doctor.orders.store');
            Route::post('/{order}/pay', [CheckoutController::class, 'createSession'])->name('doctor.orders.pay');
            Route::get('/', [OrderController::class, 'index'])->name('doctor.orders.index');
            Route::get('/payment-status', [OrderController::class, 'paymentStatus'])->name('doctor.orders.payment-status');
            Route::get('/{order}', [OrderController::class, 'show'])->name('doctor.orders.show');

            Route::get('/{order}/track', [OrderController::class, 'track'])->name('doctor.orders.track');
            Route::post('/{order}/print-status', [OrderController::class, 'printStatus'])->name('doctor.orders.print-status');
            Route::get('/{order}/payment', [PaymentController::class, 'show'])->name('doctor.orders.payment.show');
            Route::get('/{order}/payment/status', [PaymentController::class, 'status'])->name('doctor.orders.payment.status');
            //  Route::post('/{order}/pricing/calculate', [OrderPricingController::class, 'calculate'])->name('orders.pricing.calculate');
        });

        // ----------------------------------receptionist-----------------------------------------------------------------

        Route::middleware(['role:receptionist,lab_manager'])->prefix('orders')->group(function (): void {
            Route::get('/', [ReceptionistOrderController::class, 'index'])->name('orders.index');
            Route::get('/delivery-employees', [ReceptionistDeliveryTaskController::class, 'employees'])
                ->name('orders.delivery-employees.index');
            Route::get('/delivery-tasks', [ReceptionistDeliveryTaskController::class, 'tasks'])
                ->name('orders.delivery-tasks.index');
            Route::get('/{order}/qr-image', [ReceptionistOrderController::class, 'qrImage'])->name('orders.qr-image');
            Route::get('/{order}', [ReceptionistOrderController::class, 'show'])->name('orders.show');
            Route::post('/{order}/lock', [ReceptionistOrderController::class, 'lock'])->name('orders.lock');
            Route::post('/{order}/unlock', [ReceptionistOrderController::class, 'unlock'])->name('orders.unlock');
            Route::post('/{order}/status', [ReceptionistOrderController::class, 'updateStatus'])->name('orders.status.update');
            Route::post('/{order}/delivery-assignments', [ReceptionistDeliveryTaskController::class, 'assign'])
                ->name('orders.delivery-assignments.store');
            // Route::post('/{order}/resubmission', [ReceptionistOrderController::class, 'markForResubmission'])
            //  ->name('orders.resubmission.store');
        });

        Route::middleware(['role:lab_manager'])->prefix('lab/employees')->group(function (): void {
            Route::get('/', [LabEmployeeController::class, 'index'])->name('lab.employees.index');
            Route::post('/', [LabEmployeeController::class, 'store'])->name('lab.employees.store');
            Route::get('/{employee}', [LabEmployeeController::class, 'show'])->name('lab.employees.show');
            Route::post('/{employee}', [LabEmployeeController::class, 'update'])->name('lab.employees.update');
            Route::delete('/{employee}', [LabEmployeeController::class, 'destroy'])->name('lab.employees.destroy');

            // Employee role assignment
            Route::get('/{employee}/roles', [LabRoleController::class, 'employeeRoles'])->name('lab.employees.roles.index');
            Route::post('/{employee}/roles', [LabRoleController::class, 'assignEmployeeRole'])->name('lab.employees.roles.store');
            Route::delete('/{employee}/roles/{departmentRole}', [LabRoleController::class, 'removeEmployeeRole'])->name('lab.employees.roles.destroy');
        });

        // Permissions and roles management
        Route::middleware(['role:lab_manager,system_admin'])->prefix('lab')->group(function (): void {
            Route::get('/permissions', [LabRoleController::class, 'permissions'])->name('lab.permissions.index');

            Route::prefix('roles')->group(function (): void {
                Route::get('/matrix', [LabRoleController::class, 'matrix'])->name('lab.roles.matrix');
                Route::put('/matrix', [LabRoleController::class, 'updateMatrix'])->name('lab.roles.matrix.update');
                Route::get('/', [LabRoleController::class, 'index'])->name('lab.roles.index');
                Route::post('/', [LabRoleController::class, 'store'])->name('lab.roles.store');
                Route::put('/{role}', [LabRoleController::class, 'update'])->name('lab.roles.update');
                Route::delete('/{role}', [LabRoleController::class, 'destroy'])->name('lab.roles.destroy');
            });
        });

        // Lab wallet
        Route::middleware(['role:lab_manager,system_admin'])->prefix('lab/wallet')->group(function (): void {
            Route::get('/', [WalletController::class, 'show'])->name('lab.wallet.show');
            Route::get('/transactions', [WalletController::class, 'transactions'])->name('lab.wallet.transactions.index');
            Route::get('/transactions/{transaction}', [WalletController::class, 'showTransaction'])->name('lab.wallet.transactions.show');
        });

        Route::middleware(['role:lab_manager'])->prefix('lab/departments')->group(function (): void {
            Route::get('/with-employees/list', [DepartmentController::class, 'indexWithEmployees'])->name('lab.departments.with-employees.index');
            Route::get('/', [DepartmentController::class, 'index'])->name('lab.departments.index');
            Route::post('/', [DepartmentController::class, 'store'])->name('lab.departments.store');
            Route::post('/bulk', [DepartmentController::class, 'bulkStore'])->name('lab.departments.bulk.store');
            Route::get('/{department}', [DepartmentController::class, 'show'])->name('lab.departments.show');
            Route::get('/{department}/with-employees', [DepartmentController::class, 'showWithEmployees'])->name('lab.departments.with-employees.show');
            Route::put('/{department}', [DepartmentController::class, 'update'])->name('lab.departments.update');
            Route::delete('/{department}', [DepartmentController::class, 'destroy'])->name('lab.departments.destroy');
        });

        // Lab manager - Bulk order department routing
        Route::middleware(['role:lab_manager'])->post('lab/orders/departments', [LabManagerOrderController::class, 'setDepartmentRoute'])
            ->name('lab.orders.departments.store');

        // Lab manager - Doctor balances (billed / paid / owed)
        Route::middleware(['role:lab_manager,receptionist'])->get('lab/doctors/balances', [DoctorBalanceController::class, 'index'])
            ->name('lab.doctors.balances');

        // Lab manager / Receptionist - Per-doctor refund summary (orders count, paid, due)
        Route::middleware(['role:lab_manager,receptionist'])->get('lab/refunds', [RefundController::class, 'index'])
            ->name('lab.refunds');

        // Receptionist / Lab manager - Each doctor's orders (serial, case type, date, cost)
        Route::middleware(['role:lab_manager,receptionist'])->get('lab/doctors/orders', [DoctorOrdersController::class, 'index'])
            ->name('lab.doctors.orders');

        Route::middleware(['role:lab_manager,receptionist'])->get('lab/doctors/orders/{doctor}', [DoctorOrdersController::class, 'show'])
            ->name('lab.doctors.orders.show');

        // Lab manager - Delivery time settings
        Route::middleware(['role:lab_manager'])->prefix('lab/delivery-settings')->group(function (): void {
            Route::get('/', [LabDeliverySettingController::class, 'show'])->name('lab.delivery-settings.show');
            Route::put('/', [LabDeliverySettingController::class, 'update'])->name('lab.delivery-settings.update');
        });
        // ====================================lab_technician===================================================

        Route::middleware(['role:lab_technician'])->prefix('lab/technician')->group(function (): void {
            Route::get('/departments/{department}/tasks', [LabTechnicianTaskController::class, 'index'])
                ->name('lab.technician.departments.tasks.index');

            Route::get('orders/qr/{qr}', [OrderController::class, 'showByQr'])->name('lab.technician.orders.show-qr');
            Route::get('orders/qr/{qr}', [OrderController::class, 'showByQr'])->name('orders.show-qr');
            Route::post('orders/qr/{qr}/start', [LabTechnicianTaskController::class, 'startByQr'])->name('lab.technician.orders.qr.start');
            Route::post('orders/qr/{qr}/finish', [LabTechnicianTaskController::class, 'finishByQr'])->name('lab.technician.orders.qr.finish');
        });
        // =======================================================================================================

        // ====================================department_manager===================================================

        Route::middleware(['role:department_manager'])->prefix('department_manager')->group(function (): void {
            Route::get('/tasks', [DepartmentManagerTaskController::class, 'index'])
                ->name('department.manager.tasks.index');

            Route::prefix('tasks/{task}')->group(function () {
                Route::post('/move-forward', [TaskWorkflowController::class, 'moveForward'])->name('department.manager.tasks.move.forward');
                Route::post('/move-backward', [TaskWorkflowController::class, 'moveBackward'])->name('department.manager.tasks.move.backward');
                Route::post('/assign', [TaskWorkflowController::class, 'assignTechnician'])->name('department.manager.tasks.assign.technician');
            });

            Route::get('/departments/{departmentId}/technicians', [TaskWorkflowController::class, 'getTechnicians'])->name('department.manager.getTechnicians');

            Route::get('orders/qr/{qr}', [OrderController::class, 'showByQr'])->name('department.manager.orders.show-qr');
        });

        // ====================================delivery===================================================

        Route::middleware(['role:delivery'])->prefix('delivery')->group(function (): void {
            Route::get('/tasks', [DeliveryEmployeeTaskController::class, 'index'])
                ->name('delivery.tasks.index');
            Route::post('/tasks/status/bulk', [DeliveryEmployeeTaskController::class, 'bulkUpdateStatus'])
                ->name('delivery.tasks.status.bulk-update');
        });

        // =======================================================================================================

        Route::middleware(['role:doctor,lab_technician,receptionist,department_manager,delivery'])->prefix('notifications')->group(function (): void {
            Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
            Route::post('/device-tokens', [DeviceTokenController::class, 'store'])->name('device-tokens.store');
            Route::delete('/device-tokens/{deviceToken}', [DeviceTokenController::class, 'destroy'])->name('device-tokens.destroy');
        });

        // ==========================================================================
    });
});
