<?php

namespace App\Http\Services;

use App\Http\Repositories\LabRepository;
use App\Models\Lab;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class LabService
{
    public function __construct(
        protected LabRepository $labRepository
    ) {}

    public function getLabs(int $perPage = 15): LengthAwarePaginator
    {
        return $this->labRepository->paginateAll($perPage);
    }

    public function searchLabs(string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->labRepository->searchPaginated($search, $perPage);
    }

    public function getTopRatedLabs(?int $limit = null): Collection
    {
        return $this->labRepository->getTopRated($limit);
    }

    public function getNearbyLabs(int $doctorId, ?int $limit = null): Collection
    {
        $doctor = User::query()
            ->select(['id', 'location_lat', 'location_lng'])
            ->findOrFail($doctorId);

        if ($doctor->location_lat === null || $doctor->location_lng === null) {
            throw ValidationException::withMessages([
                'doctor_id' => ['The selected doctor must have a location.'],
            ]);
        }

        return $this->labRepository->getNearby(
            (float) $doctor->location_lat,
            (float) $doctor->location_lng,
            $limit
        );
    }

    public function getSuggestedLabs(?int $limit = null): Collection
    {
        return $this->labRepository->getSuggested($limit);
    }

    public function getMostOrderedLabs(?int $limit = null): Collection
    {
        return $this->labRepository->getMostOrdered($limit);
    }

    public function getLabDetails(Lab $lab): array
    {
        $averageRating = Lab::query()
            ->whereKey($lab->id)
            ->selectRaw('COALESCE((SELECT AVG(reviews.rating) FROM reviews INNER JOIN orders ON orders.id = reviews.order_id WHERE orders.lab_id = labs.id), 0) as rating')
            ->value('rating');

        return [
            'id' => $lab->id,
            'name' => $lab->name,
            'phone' => $lab->phone,
            'address' => $lab->address,
            'latitude' => $lab->latitude,
            'longitude' => $lab->longitude,
            'rating' => number_format((float) $averageRating, 2, '.', ''),
        ];
    }
}
