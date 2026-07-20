<?php

namespace App\Http\Controllers;

use App\Http\Requests\PackageStoreRequest;
use App\Http\Requests\PackageUpdateRequest;
use App\Http\Resources\PackageResource;
use App\Http\Responses\ApiResponse;
use App\Models\Package;
use App\Services\PackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PackageController extends Controller
{
    public function __construct(
        private PackageService $service,
        private ApiResponse $apiResponse,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->service->search($request->query('q'));
        $perPage = $request->integer('per_page', 20);
        $perPage = max(1, min($perPage, 100));
        $packages = $query->paginate($perPage);

        $resource = PackageResource::collection($packages);
        $resourceArray = $resource->response()->getData(true);

        $data = [
            'data' => $resourceArray['data'],
            'total' => $resourceArray['meta']['total'] ?? 0,
            'per_page' => $resourceArray['meta']['per_page'] ?? $perPage,
            'current_page' => $resourceArray['meta']['current_page'] ?? 1,
            'last_page' => $resourceArray['meta']['last_page'] ?? 1,
        ];

        return $this->apiResponse->success($data, __('packages.retrieved_successfully'));
    }

    public function store(PackageStoreRequest $request): JsonResponse
    {
        $package = $this->service->create($request->validated(), $request->user());

        $resource = new PackageResource($package);

        return $this->apiResponse->success(
            $resource->toArray($request),
            __('packages.created_successfully'),
            Response::HTTP_CREATED,
        );
    }

    public function show(Package $package): JsonResponse
    {
        $resource = new PackageResource($package);

        return $this->apiResponse->success(
            $resource->toArray(request()),
            __('packages.retrieved_successfully'),
        );
    }

    public function update(PackageUpdateRequest $request, Package $package): JsonResponse
    {
        $package = $this->service->update($package, $request->validated(), $request->user());

        $resource = new PackageResource($package);

        return $this->apiResponse->success(
            $resource->toArray($request),
            __('packages.updated_successfully'),
        );
    }

    public function destroy(Request $request, Package $package): JsonResponse
    {
        $this->service->delete($package, $request->user());

        return $this->apiResponse->success(null, __('packages.deleted_successfully'));
    }
}
