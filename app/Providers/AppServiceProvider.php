<?php

namespace App\Providers;

use App\Models\DeliveryTask;
use App\Models\Department;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Task;
use App\Policies\DeliveryTaskPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\TaskPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(DeliveryTask::class, DeliveryTaskPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
