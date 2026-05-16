<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabIndexRequest;
use App\Http\Requests\LabSearchRequest;
use App\Http\Requests\LabStoreRequest;
use App\Http\Requests\LabUpdateRequest;
use App\Http\Resources\LabResource;
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

        return $this->apiResponse->success($labs, __('labs.retrieved_successfully'));
    }

    public function search(LabSearchRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);

        $labs = $this->labService->searchLabs($validated['search'], $perPage);

        $resource = LabResource::collection($labs);
        $resourceArray = $resource->response()->getData(true);

        $data = [
            'data' => $resourceArray['data'],
            'total' => $resourceArray['meta']['total'] ?? 0,
            'per_page' => $resourceArray['meta']['per_page'] ?? $perPage,
            'current_page' => $resourceArray['meta']['current_page'] ?? 1,
        ];

        return $this->apiResponse->success($data, __('labs.search_results_retrieved_successfully'));
    }

    public function topRated(LabIndexRequest $request): JsonResponse
    {
        $limit = $this->resolveHomeLimit($request);

        $labs = $this->labService->getTopRatedLabs($limit)
            ->filter(fn($lab) => data_get($lab, 'is_active', true));

        $resource = LabResource::collection($labs)->toArray(request());

        return $this->apiResponse->success($resource, __('labs.top_rated_retrieved_successfully'));
    }

    public function nearby(LabIndexRequest $request): JsonResponse
    {
        $limit = $this->resolveHomeLimit($request);

        $doctorId = Auth::id();

        if ($doctorId === null) {
            return $this->apiResponse->error(
                __('auth.unauthenticated'),
                401
            );
        }

        $labs = $this->labService->getNearbyLabs((int) $doctorId, $limit)
            ->filter(fn($lab) => data_get($lab, 'is_active', true));

        $resource = LabResource::collection($labs)->toArray(request());

        return $this->apiResponse->success($resource, __('labs.nearby_retrieved_successfully'));
    }

    public function suggested(LabIndexRequest $request): JsonResponse
    {
        $limit = $this->resolveHomeLimit($request);

        $labs = $this->labService->getSuggestedLabs($limit)
            ->filter(fn($lab) => data_get($lab, 'is_active', true));

        $resource = LabResource::collection($labs)->toArray(request());

        return $this->apiResponse->success($resource, __('labs.suggested_retrieved_successfully'));
    }

    public function mostOrdered(LabIndexRequest $request): JsonResponse
    {
        $limit = $this->resolveHomeLimit($request);

        $labs = $this->labService->getMostOrderedLabs($limit)
            ->filter(fn($lab) => data_get($lab, 'is_active', true));

        $resource = LabResource::collection($labs)->toArray(request());

        return $this->apiResponse->success($resource, __('labs.most_ordered_retrieved_successfully'));
    }

    public function inactiveLabs(LabIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);

        $labs = $this->labService->getInactiveLabs($perPage);

        return $this->apiResponse->success($labs, __('labs.inactive_retrieved_successfully'));
    }

    public function show(Lab $lab): JsonResponse
    {
        if (! data_get($lab, 'is_active', true)) {
            return $this->apiResponse->error(__('messages.not_found'), 404);
        }

        // Fetch lab with review stats to ensure calculated fields are available
        $lab = $this->labService->getLabById($lab->id);

        $resource = new LabResource($lab);

        return $this->apiResponse->success($resource->toArray(request()), __('labs.details_retrieved_successfully'));
    }

    private function resolveHomeLimit(Request $request): ?int
    {
        return $request->query('context') === 'home' ? 4 : null;
    }

    public function adminIndex(LabIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);

        return $this->apiResponse->success(
            $this->labService->getAdminLabs($perPage),
            __('labs.retrieved_successfully')
        );
    }

    public function adminShow(Lab $lab): JsonResponse
    {
        return $this->apiResponse->success(
            $this->labService->getAdminLabDetails($lab),
            __('labs.details_retrieved_successfully')
        );
    }

    public function store(LabStoreRequest $request): JsonResponse
    {
        $result = $this->labService->createLabWithManager($request->validated());

        return $this->apiResponse->success(
            $result,
            __('labs.created_successfully'),
            201,
        );
    }

    public function update(LabUpdateRequest $request, Lab $lab): JsonResponse
    {
        $result = $this->labService->updateLabWithManager($lab, $request->validated());

        return $this->apiResponse->success(
            $result,
            __('labs.updated_successfully')
        );
    }

    public function destroy(Lab $lab): JsonResponse
    {
        $this->labService->deleteLab($lab);

        return $this->apiResponse->success(null, __('labs.deleted_successfully'));
    }
}
