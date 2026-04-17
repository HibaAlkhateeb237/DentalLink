<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabIndexRequest;
use App\Http\Requests\LabNearbyRequest;
use App\Http\Requests\LabSearchRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Services\LabService;
use App\Models\Lab;
use Illuminate\Http\JsonResponse;

class LabController extends Controller
{
    public function __construct(
        protected LabService $labService,
        protected ApiResponse $apiResponse,
    ) {}

    public function index(LabIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);

        $labs = $this->labService->getLabs($perPage);

        return $this->apiResponse->success($labs, 'Labs retrieved successfully');
    }

    public function search(LabSearchRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);

        $labs = $this->labService->searchLabs($validated['search'], $perPage);

        return $this->apiResponse->success($labs, 'Labs search results retrieved successfully');
    }

    public function topRated(LabIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);

        return $this->apiResponse->success(
            $this->labService->getTopRatedLabs($perPage),
            'Top rated labs retrieved successfully'
        );
    }

    public function nearby(LabNearbyRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return $this->apiResponse->success(
            $this->labService->getNearbyLabs((int) $validated['doctor_id'], (int) ($validated['per_page'] ?? 15)),
            'Nearby labs retrieved successfully'
        );
    }

    public function suggested(LabIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);

        return $this->apiResponse->success(
            $this->labService->getSuggestedLabs($perPage),
            'Suggested labs retrieved successfully'
        );
    }

    public function mostOrdered(LabIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);

        return $this->apiResponse->success(
            $this->labService->getMostOrderedLabs($perPage),
            'Most ordered labs retrieved successfully'
        );
    }

    public function show(Lab $lab): JsonResponse
    {
        return $this->apiResponse->success(
            $this->labService->getLabDetails($lab),
            'Lab details retrieved successfully'
        );
    }
}
