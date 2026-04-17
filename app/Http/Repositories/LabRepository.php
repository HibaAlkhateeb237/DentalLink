<?php

namespace App\Http\Repositories;

use App\Models\Lab;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class LabRepository
{
    public function paginateAll(int $perPage = 15): LengthAwarePaginator
    {
        return Lab::query()
            ->orderByDesc('rating')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function paginateTopRated(int $perPage = 15): LengthAwarePaginator
    {
        return Lab::query()
            ->orderByDesc('rating')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function paginateNearby(float $latitude, float $longitude, int $perPage = 15): LengthAwarePaginator
    {
        return Lab::query()
            ->orderByRaw(
                '((latitude - ?)*(latitude - ?) + (longitude - ?)*(longitude - ?)) asc',
                [$latitude, $latitude, $longitude, $longitude]
            )
            ->orderByDesc('rating')
            ->paginate($perPage);
    }

    public function paginateSuggested(int $perPage = 15): LengthAwarePaginator
    {
        return Lab::query()
            ->inRandomOrder()
            ->paginate($perPage);
    }

    public function paginateMostOrdered(int $perPage = 15): LengthAwarePaginator
    {
        return Lab::query()
            ->withCount('orders')
            ->orderByDesc('orders_count')
            ->orderByDesc('rating')
            ->paginate($perPage);
    }

    public function searchPaginated(string $search, int $perPage = 15): LengthAwarePaginator
    {
        $term = trim($search);

        return Lab::query()
            ->where(function (Builder $query) use ($term): void {
                $query->where('name', 'like', '%'.$term.'%')
                    ->orWhere('address', 'like', '%'.$term.'%');
            })
            ->orderByDesc('rating')
            ->orderBy('name')
            ->paginate($perPage);
    }
}
