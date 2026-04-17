<?php

namespace App\Http\Services;

use App\Http\Repositories\LabRepository;
use App\Models\Lab;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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

    public function getTopRatedLabs(int $perPage = 15): LengthAwarePaginator
    {
        return $this->labRepository->paginateTopRated($perPage);
    }

    public function getNearbyLabs(int $doctorId, int $perPage = 15): LengthAwarePaginator
    {
        $doctor = User::query()
            ->select(['id', 'location_lat', 'location_lng'])
            ->findOrFail($doctorId);

        return $this->labRepository->paginateNearby(
            (float) $doctor->location_lat,
            (float) $doctor->location_lng,
            $perPage
        );
    }

    public function getSuggestedLabs(int $perPage = 15): LengthAwarePaginator
    {
        return $this->labRepository->paginateSuggested($perPage);
    }

    public function getMostOrderedLabs(int $perPage = 15): LengthAwarePaginator
    {
        return $this->labRepository->paginateMostOrdered($perPage);
    }

    public function getLabDetails(Lab $lab): array
    {
        return $lab->only([
            'id',
            'name',
            'phone',
            'address',
            'latitude',
            'longitude',
            'rating',
        ]);
    }
}
