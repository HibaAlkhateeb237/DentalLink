<?php

namespace App\Http\Repositories;

use App\Models\Lab;
use App\Models\Order;
use App\Models\PortfolioCase;
use App\Models\TaskWorkSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class LabPortfolioRepository
{
    public function paginatePublishedByLab(Lab $lab, int $perPage = 15): LengthAwarePaginator
    {
        return PortfolioCase::query()
            ->whereHas('order', function (Builder $query) use ($lab): void {
                $query->where('lab_id', $lab->id);
            })
            ->where('is_published', true)
            ->latest()
            ->paginate($perPage);
    }

    public function findEligibleOrderForLab(int $orderId, Lab $lab): ?Order
    {
        return Order::query()
            ->whereKey($orderId)
            ->where('lab_id', $lab->id)
            ->where('status', 'completed')
            ->first();
    }

    public function portfolioCaseExistsForOrder(Order $order): bool
    {
        return PortfolioCase::query()
            ->where('order_id', $order->id)
            ->exists();
    }

    /**
     * @param  array{order_id:int,case_name:string,before_image_path:string,after_image_path:string,duration_minutes:int|null,is_published:bool}  $attributes
     */
    public function createCase(array $attributes): PortfolioCase
    {
        return PortfolioCase::query()->create($attributes);
    }

    public function calculateOrderDurationMinutes(Order $order): ?int
    {
        $completedSessions = TaskWorkSession::query()
            ->whereNotNull('end_time')
            ->whereHas('task', function (Builder $query) use ($order): void {
                $query->where('order_id', $order->id);
            })
            ->get(['start_time', 'end_time']);

        if ($completedSessions->isEmpty()) {
            return null;
        }

        $totalMinutes = $completedSessions->sum(function (TaskWorkSession $session): int {
            if ($session->end_time === null) {
                return 0;
            }

            return max($session->start_time->diffInMinutes($session->end_time), 0);
        });

        return (int) $totalMinutes;
    }

    public function updateCase(PortfolioCase $portfolioCase, array $attributes): PortfolioCase
    {
        $portfolioCase->update($attributes);

        return $portfolioCase->fresh();
    }

    public function findCaseByOrderId(int $orderId): ?PortfolioCase
    {
        return PortfolioCase::query()->where('order_id', $orderId)->first();
    }

    public function findPortfolioCaseForLab(int $portfolioCaseId, Lab $lab): ?PortfolioCase
    {
        return PortfolioCase::query()
            ->whereKey($portfolioCaseId)
            ->whereHas('order', function (Builder $query) use ($lab): void {
                $query->where('lab_id', $lab->id);
            })
            ->first();
    }
}
