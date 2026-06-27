<?php

namespace App\Providers;

use App\Models\DeliveryTask;
use App\Models\Department;
use App\Models\LabPricingSetting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PortfolioCase;
use App\Models\Task;
use App\Models\User;
use App\Policies\DeliveryTaskPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\LabPricingSettingPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PortfolioCasePolicy;
use App\Policies\TaskPolicy;
use App\Services\FcmService;
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
        $this->app->singleton(FcmService::class);
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

        Gate::policy(User::class, EmployeePolicy::class);

        Gate::policy(PortfolioCase::class, PortfolioCasePolicy::class);

        Gate::policy(LabPricingSetting::class, LabPricingSettingPolicy::class);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
