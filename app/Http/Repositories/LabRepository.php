<?php

namespace App\Http\Repositories;

use App\Models\Lab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class LabRepository
{
    private function queryWithReviewStats(): Builder
    {
        return Lab::query()
            ->select('labs.*')
            ->selectRaw('(SELECT AVG(reviews.rating) FROM reviews INNER JOIN orders ON orders.id = reviews.order_id WHERE orders.lab_id = labs.id) as rating')
            ->selectRaw('(SELECT COUNT(*) FROM reviews INNER JOIN orders ON orders.id = reviews.order_id WHERE orders.lab_id = labs.id) as reviews_count');
    }

    private function queryActiveWithReviewStats(): Builder
    {
        return $this->queryWithReviewStats()->where('is_active', true);
    }

    public function paginateAll(int $perPage = 15): LengthAwarePaginator
    {
        return $this->queryWithReviewStats()
            // ->orderByDesc('rating')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function paginateActive(int $perPage = 15): LengthAwarePaginator
    {
        return $this->queryActiveWithReviewStats()
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getTopRated(?int $limit = null): Collection
    {
        $confidenceBoost = 2;
        $confidenceScale = 10;

        $query = $this->queryActiveWithReviewStats()
            ->orderByRaw(
                '(rating + ((reviews_count * ? * 1.0) / (reviews_count + ?))) DESC',
                [$confidenceBoost, $confidenceScale]
            )
            ->orderByDesc('rating')
            ->orderByDesc('created_at');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function getNearby(float $latitude, float $longitude, ?int $limit = null): Collection
    {
        $radiusKm = 10.0;
        $kmPerDegree = 111.045;
        $latDelta = $radiusKm / $kmPerDegree;
        $lngKmPerDegree = $kmPerDegree * max(cos(deg2rad($latitude)), 0.000001);
        $lngDelta = $radiusKm / $lngKmPerDegree;

        $distanceSql = '((latitude - ?)*(latitude - ?) * ? * ? + (longitude - ?)*(longitude - ?) * ? * ?)';
        $distanceBindings = [
            $latitude,
            $latitude,
            $kmPerDegree,
            $kmPerDegree,
            $longitude,
            $longitude,
            $lngKmPerDegree,
            $lngKmPerDegree,
        ];

        $query = $this->queryActiveWithReviewStats()
            ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
            ->whereBetween('longitude', [$longitude - $lngDelta, $longitude + $lngDelta])
            ->whereRaw($distanceSql.' <= ?', [...$distanceBindings, $radiusKm * $radiusKm])
            ->orderByRaw($distanceSql.' asc', $distanceBindings)
            ->orderByDesc('rating');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function getSuggested(?int $limit = null): Collection
    {
        $query = $this->queryActiveWithReviewStats()->inRandomOrder();

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function getMostOrdered(?int $limit = null): Collection
    {
        $query = $this->queryActiveWithReviewStats()
            ->withCount('orders')
            ->orderByDesc('orders_count')
            ->orderByDesc('rating');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function searchPaginated(string $search, int $perPage = 15): LengthAwarePaginator
    {
        $term = trim($search);

        return $this->queryActiveWithReviewStats()
            ->where(function (Builder $query) use ($term): void {
                $query->where('name', 'like', '%'.$term.'%')
                    ->orWhere('address', 'like', '%'.$term.'%');
            })
            ->orderByDesc('rating')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function getInactiveLabs(int $perPage = 15): LengthAwarePaginator
    {
        $avgRatingSubQuery = '(SELECT AVG(reviews.rating) FROM reviews INNER JOIN orders ON orders.id = reviews.order_id WHERE orders.lab_id = labs.id)';
        $reviewsCountSubQuery = '(SELECT COUNT(*) FROM reviews INNER JOIN orders ON orders.id = reviews.order_id WHERE orders.lab_id = labs.id)';

        return $this->queryWithReviewStats()
            ->where('is_active', false)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getWithReviewStats(int $labId): Lab
    {
        return $this->queryWithReviewStats()
            ->findOrFail($labId);
    }
}
