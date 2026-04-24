<?php

namespace App\Http\Repositories;

use App\Models\Lab;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class LabRepository
{
    private function queryWithReviewStats(): Builder
    {
        $avgRatingSubQuery = '(SELECT AVG(reviews.rating) FROM reviews INNER JOIN orders ON orders.id = reviews.order_id WHERE orders.lab_id = labs.id)';
        $reviewsCountSubQuery = '(SELECT COUNT(*) FROM reviews INNER JOIN orders ON orders.id = reviews.order_id WHERE orders.lab_id = labs.id)';

        return Lab::query()
            ->select('labs.*')
            ->selectRaw('COALESCE('.$avgRatingSubQuery.', 0) as rating')
            ->selectRaw('COALESCE('.$reviewsCountSubQuery.', 0) as reviews_count');
    }

    public function paginateAll(int $perPage = 15): LengthAwarePaginator
    {
        return $this->queryWithReviewStats()
           // ->orderByDesc('rating')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getTopRated(?int $limit = null): Collection
    {
        $confidenceBoost = 2;
        $confidenceScale = 10;

        $query = $this->queryWithReviewStats()
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

        $query = $this->queryWithReviewStats()
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
        $query = $this->queryWithReviewStats()->inRandomOrder();

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function getMostOrdered(?int $limit = null): Collection
    {
        $query = $this->queryWithReviewStats()
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

        return $this->queryWithReviewStats()
            ->where(function (Builder $query) use ($term): void {
                $query->where('name', 'like', '%'.$term.'%')
                    ->orWhere('address', 'like', '%'.$term.'%');
            })
            ->orderByDesc('rating')
            ->orderBy('name')
            ->paginate($perPage);
    }
}
