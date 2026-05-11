<?php

namespace App\Http\Controllers;

use App\Http\Resources\DentalCompensationTypePriceResource;
use App\Http\Resources\ToothShadeResource;
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

        if (! data_get($lab, 'is_active', true)) {
            return $this->apiResponse->error(__('messages.not_found'), 404);
        }

        $payload = $this->labPricingService->getLabPricing($lab);

        $items = DentalCompensationTypePriceResource::collection($payload['items'])->toArray(request());
        $toothShades = ToothShadeResource::collection($payload['tooth_shades'])->toArray(request());

        return $this->apiResponse->success(
            [
                'items' => $items,
                'tooth_shades' => $toothShades,
            ],
            __('pricing.retrieved_successfully'),
            200,
        );
    }
}
