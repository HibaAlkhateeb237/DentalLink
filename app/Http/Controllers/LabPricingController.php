<?php

namespace App\Http\Controllers;

use App\Http\Resources\DentalCompensationTypePriceResource;
use App\Http\Resources\LabPricingSettingResource;
use App\Http\Responses\ApiResponse;
use App\Http\Services\LabPricingService;
use App\Models\Lab;
use App\Models\LabPricingSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class LabPricingController extends Controller
{
    public function __construct(
        private LabPricingService $labPricingService,
        private ApiResponse $apiResponse,
    ) {}

    public function show(Lab $lab): JsonResponse
    {
        // Gate::authorize('viewAny', [LabPricingSetting::class, $lab]);

        $payload = $this->labPricingService->getLabPricing($lab);

        return $this->apiResponse->success(
            [
              //  'settings' => $payload['settings'] === null ? null : LabPricingSettingResource::make($payload['settings']),
                'items' => DentalCompensationTypePriceResource::collection($payload['items']),
            ],
            __('pricing.retrieved_successfully'),
            200,
        );
    }
}
