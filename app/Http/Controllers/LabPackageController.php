<?php

namespace App\Http\Controllers;

use App\Http\Resources\LabPackageHistoryResource;
use App\Http\Resources\PackageResource;
use App\Http\Responses\ApiResponse;
use App\Http\Services\LabService;
use App\Models\DepartmentUserRole;
use App\Models\Lab;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LabPackageController extends Controller
{
    public function __construct(
        private ApiResponse $apiResponse,
        private LabService $labService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $lab = $this->resolveLab($request->user());

        $lab->load('package');

        if (! $lab->package) {
            return $this->apiResponse->success(null, __('packages.no_package_assigned'));
        }

        $resource = new PackageResource($lab->package);

        return $this->apiResponse->success(
            $resource->toArray($request),
            __('packages.retrieved_successfully'),
        );
    }

    public function history(Request $request): JsonResponse
    {
        $lab = $this->resolveLab($request->user());

        $history = $this->labService->getLabPackageHistory(
            $lab->id,
            $request->integer('per_page', 20)
        );

        $resource = LabPackageHistoryResource::collection($history);

        return $this->apiResponse->success(
            $resource->response()->getData(true),
            __('packages.history_retrieved'),
        );
    }

    private function resolveLab($user): Lab
    {
        $labId = DepartmentUserRole::query()
            ->join('departments', 'departments.id', '=', 'department_user_roles.department_id')
            ->where('department_user_roles.user_id', $user->id)
            ->value('departments.lab_id');

        if (! $labId) {
            abort(404, __('messages.lab_not_found'));
        }

        return Lab::query()->findOrFail($labId);
    }
}
