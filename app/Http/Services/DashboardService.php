<?php

namespace App\Http\Services;

use App\Models\Department;
use App\Models\Lab;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
use App\Support\OrderStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class DashboardService
{
    public function __construct(
        protected ?Carbon $carbon = null,
    ) {
        $this->carbon = $carbon ?? Carbon::now();
    }

    public function getDashboardData(?int $labId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? $this->carbon->copy()->startOfMonth();
        $to = $to ?? $this->carbon->copy()->endOfMonth();

        $labIds = $labId ? [$labId] : $this->getLabIdsForUser();

        return [
            'average_delivery_time' => $this->getAverageDeliveryTime($labIds, $from, $to),
            'monthly_revenue' => $this->getMonthlyRevenue($labIds, $from, $to),
            'technician_productivity' => $this->getTechnicianProductivity($labIds, $from, $to),
            'department_workload' => $this->getDepartmentWorkload($labIds, $from, $to),
            'top_clinics' => $this->getTopClinics($labIds, $from, $to),
            'yearly_performance_chart' => $this->getYearlyPerformanceChart($labIds, $from, $to),
            'date_range' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
            ],
        ];
    }

    private function getLabIdsForUser(): array
    {
        return Lab::query()->pluck('id')->toArray();
    }

    private function getAverageDeliveryTime(array $labIds, Carbon $from, Carbon $to): array
    {
        $orders = Order::query()
            ->whereIn('lab_id', $labIds)
            ->whereNotNull('delivered_at')
            ->where('status', OrderStatus::COMPLETED)
            ->whereYear('delivered_at', $from->year)
            ->get(['received_at', 'delivered_at']);

        if ($orders->isEmpty()) {
            return [
                'average_days' => 0,
                'average_hours' => 0,
                'total_completed' => 0,
            ];
        }

        $totalMinutes = $orders->sum(function (Order $order): int {
            return $order->received_at->diffInMinutes($order->delivered_at);
        });

        $count = $orders->count();
        $averageMinutes = $totalMinutes / $count;

        return [
            'average_days' => round($averageMinutes / (24 * 60), 2),
            'average_hours' => round($averageMinutes / 60, 2),
            'average_minutes' => round($averageMinutes, 2),
            'total_completed' => $count,
        ];
    }

    private function getMonthlyRevenue(array $labIds, Carbon $from, Carbon $to): array
    {
        $revenue = Payment::query()
            ->join('payment_order', 'payment_order.payment_id', '=', 'payments.id')
            ->join('orders', 'orders.id', '=', 'payment_order.order_id')
            ->whereIn('orders.lab_id', $labIds)
            ->where('payments.payment_status', 'paid')
            ->whereBetween('payments.paid_at', [$from, $to])
            ->sum('payment_order.amount');

        $ordersCount = Order::query()
            ->whereIn('lab_id', $labIds)
            ->whereBetween('received_at', [$from, $to])
            ->count();

        $avgOrderValue = $ordersCount > 0 ? $revenue / $ordersCount : 0;

        return [
            'total_revenue' => round($revenue, 2),
            'orders_count' => $ordersCount,
            'average_order_value' => round($avgOrderValue, 2),
        ];
    }

    private function getTechnicianProductivity(array $labIds, Carbon $from, Carbon $to): array
    {
        $year = $from->year;
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31)->endOfDay();

        $technicians = User::query()
            ->whereHas('roles', fn (Builder $q) => $q->where('name', 'lab_technician'))
            ->whereHas('departmentUserRoles', fn (Builder $q) => $q->whereIn('department_id', function ($query) use ($labIds) {
                $query->select('id')->from('departments')->whereIn('lab_id', $labIds);
            }))
            ->with(['tasks' => fn ($q) => $q->whereBetween('approved_at', [$yearStart, $yearEnd])->where('status', 'completed')->with('workSessions')])
            ->get();

        $productivity = $technicians->map(function (User $technician): array {
            $completedTasks = $technician->tasks->filter(fn (Task $t) => $t->status === 'completed');

            return [
                'technician_id' => $technician->id,
                'name' => $technician->name,
                'completed_tasks_count' => $completedTasks->count(),
            ];
        });

        return [
            'technicians' => $productivity->values()->toArray(),
            'total_technicians' => $productivity->count(),
            'total_completed_tasks' => $productivity->sum('completed_tasks_count'),
        ];
    }

    private function getDepartmentWorkload(array $labIds, Carbon $from, Carbon $to): array
    {
        $year = $from->year;
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31)->endOfDay();

        $departments = Department::query()
            ->whereIn('lab_id', $labIds)
            ->with(['tasks' => fn ($q) => $q->whereBetween('approved_at', [$yearStart, $yearEnd])])
            ->get();

        $workload = $departments->map(function (Department $dept): array {
            $tasks = $dept->tasks;
            $pending = $tasks->where('status', 'pending')->count();
            $inProgress = $tasks->where('status', 'in_progress')->count();
            $completed = $tasks->where('status', 'completed')->count();
            $total = $tasks->count();

            return [
                'department_id' => $dept->id,
                'name' => $dept->name,
                'pending_tasks' => $pending,
                'in_progress_tasks' => $inProgress,
                'completed_tasks' => $completed,
                'total_tasks' => $total,
                'completion_rate' => $total > 0 ? round($completed / $total * 100, 2) : 0,
            ];
        });

        return [
            'departments' => $workload->values()->toArray(),
            'total_tasks' => $workload->sum('total_tasks'),
            'total_completed' => $workload->sum('completed_tasks'),
        ];
    }

    private function getTopClinics(array $labIds, Carbon $from, Carbon $to): array
    {
        $topDoctors = User::query()
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.phone',
            ])
            ->join('orders', 'orders.user_id', '=', 'users.id')
            ->whereIn('orders.lab_id', $labIds)
            ->whereBetween('orders.received_at', [$from, $to])
            ->groupBy('users.id', 'users.name', 'users.email', 'users.phone')
            ->selectRaw('COUNT(orders.id) as orders_count')
            ->selectRaw('COALESCE(SUM(orders.price), 0) as total_spent')
            ->orderByDesc('orders_count')
            ->limit(10)
            ->get();

        return [
            'doctors' => $topDoctors->map(function (User $doctor): array {
                return [
                    'doctor_id' => $doctor->id,
                    'name' => $doctor->name,
                    'email' => $doctor->email,
                    'phone' => $doctor->phone,
                    'orders_count' => (int) $doctor->orders_count,
                    'total_spent' => round((float) $doctor->total_spent, 2),
                ];
            })->values()->toArray(),
        ];
    }

    private function getYearlyPerformanceChart(array $labIds, Carbon $from, Carbon $to): array
    {
        $year = $from->year;
        // Only show months up to the current month (or the 'to' month)
        $currentMonth = min($to->month, now()->month);

        $months = collect(range(1, $currentMonth))->map(function (int $month) use ($labIds, $year): array {
            $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $orders = Order::query()
                ->whereIn('lab_id', $labIds)
                ->whereBetween('received_at', [$monthStart, $monthEnd])
                ->get(['id', 'price', 'status', 'delivered_at']);

            $completed = $orders->where('status', OrderStatus::COMPLETED);
            $revenue = $completed->sum('price');
            $ordersCount = $orders->count();
            $completedCount = $completed->count();

            return [
                'month' => $month,
                'month_name' => $monthStart->format('M'),
                'orders_count' => $ordersCount,
                'completed_count' => $completedCount,
                'revenue' => round($revenue, 2),
                'completion_rate' => $ordersCount > 0 ? round($completedCount / $ordersCount * 100, 2) : 0,
            ];
        });

        return [
            'year' => $year,
            'months' => $months->toArray(),
            'yearly_total_revenue' => round($months->sum('revenue'), 2),
            'yearly_total_orders' => $months->sum('orders_count'),
            'yearly_completed_orders' => $months->sum('completed_count'),
        ];
    }
}
