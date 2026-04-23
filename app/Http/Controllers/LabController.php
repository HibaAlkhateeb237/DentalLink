<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabIndexRequest;
use App\Http\Requests\LabSearchRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Services\LabService;
use App\Models\Lab;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        $limit = $this->resolveHomeLimit($request);

        return $this->apiResponse->success(
            $this->labService->getTopRatedLabs($limit),
            'Top rated labs retrieved successfully'
        );
    }

    public function nearby(LabIndexRequest $request): JsonResponse
    {
        $limit = $this->resolveHomeLimit($request);

        $doctorId = Auth::id();

        return $this->apiResponse->success(
            $this->labService->getNearbyLabs((int) $doctorId, $limit),
            'Nearby labs retrieved successfully'
        );
    }

    public function suggested(LabIndexRequest $request): JsonResponse
    {
        $limit = $this->resolveHomeLimit($request);

        return $this->apiResponse->success(
            $this->labService->getSuggestedLabs($limit),
            'Suggested labs retrieved successfully'
        );
    }

    public function mostOrdered(LabIndexRequest $request): JsonResponse
    {
        $limit = $this->resolveHomeLimit($request);

        return $this->apiResponse->success(
            $this->labService->getMostOrderedLabs($limit),
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

    private function resolveHomeLimit(Request $request): ?int
    {
        return $request->query('context') === 'home' ? 4 : null;
    }
}
