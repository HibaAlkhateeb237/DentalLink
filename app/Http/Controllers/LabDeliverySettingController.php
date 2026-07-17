<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabDeliverySettingRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Services\LabService;
use App\Models\Lab;
use Illuminate\Http\JsonResponse;

class LabDeliverySettingController extends Controller
{
    public function __construct(
        private LabService $labService,
        private ApiResponse $apiResponse,
    ) {}

    public function show(): JsonResponse
    {
        $lab = Lab::query()->findOrFail($this->labService->resolveManagerLabId(request()->user()));

        return $this->apiResponse->success(
            $this->labService->getDeliverySettings($lab),
            __('labs.delivery_settings_retrieved_successfully')
        );
    }

    public function update(LabDeliverySettingRequest $request): JsonResponse
    {
        $lab = Lab::query()->findOrFail($this->labService->resolveManagerLabId($request->user()));

        $settings = $this->labService->updateDeliverySettings($lab, $request->validated());

        return $this->apiResponse->success(
            $settings,
            __('labs.delivery_settings_updated_successfully')
        );
    }
}
