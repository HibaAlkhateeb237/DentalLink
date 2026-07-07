<?php

namespace App\Http\Controllers;

use App\Http\Requests\DentalCompensationTypeStoreRequest;
use App\Http\Requests\DentalCompensationTypeUpdateRequest;
use App\Http\Resources\DentalCompensationTypeResource;
use App\Http\Responses\ApiResponse;
use App\Models\DentalCompensationType;
use App\Services\DentalCompensationTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DentalCompensationTypeController extends Controller
{
    public function __construct(
        private DentalCompensationTypeService $service,
        private ApiResponse $apiResponse,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->service->search($request->query('q'), $request->user());
        $compensations = $query->paginate(20);

        $resource = DentalCompensationTypeResource::collection($compensations);
        $resourceArray = $resource->response()->getData(true);

        $data = [
            'data' => $resourceArray['data'],
            'total' => $resourceArray['meta']['total'] ?? 0,
            'per_page' => $resourceArray['meta']['per_page'] ?? 20,
            'current_page' => $resourceArray['meta']['current_page'] ?? 1,
            'last_page' => $resourceArray['meta']['last_page'] ?? 1,
        ];

        return $this->apiResponse->success($data, __('pricing.compensation_retrieved_successfully'));
    }

    public function store(DentalCompensationTypeStoreRequest $request): JsonResponse
    {
        $compensation = $this->service->create($request->validated(), $request->user());

        $resource = new DentalCompensationTypeResource($compensation);

        return $this->apiResponse->success(
            $resource->toArray($request),
            __('pricing.compensation_created_successfully'),
            Response::HTTP_CREATED,
        );
    }

    public function show(DentalCompensationType $dental_compensation_type): JsonResponse
    {
        $resource = new DentalCompensationTypeResource($dental_compensation_type);

        return $this->apiResponse->success(
            $resource->toArray(request()),
            __('pricing.compensation_retrieved_successfully'),
        );
    }

    public function update(DentalCompensationTypeUpdateRequest $request, DentalCompensationType $dental_compensation_type): JsonResponse
    {
        $compensation = $this->service->update($dental_compensation_type, $request->validated(), $request->user());

        $resource = new DentalCompensationTypeResource($compensation);

        return $this->apiResponse->success(
            $resource->toArray($request),
            __('pricing.compensation_updated_successfully'),
        );
    }

    public function destroy(Request $request, DentalCompensationType $dental_compensation_type): JsonResponse
    {
        $this->service->delete($dental_compensation_type, $request->user());

        return $this->apiResponse->success(null, __('pricing.compensation_deleted_successfully'));
    }
}
