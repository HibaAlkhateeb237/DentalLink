<?php

namespace App\Http\Services;

use App\Http\Repositories\LabPricingRepository;
use App\Models\Lab;
use App\Models\ToothShade;

class LabPricingService
{
    public function __construct(
        private LabPricingRepository $labPricingRepository,
    ) {}

    /**
     * @return array{settings: mixed, items: mixed}
     */
    public function getLabPricing(Lab $lab): array
    {
        return [
            'settings' => $this->labPricingRepository->getActiveSettingForLab($lab),
            'items' => $this->labPricingRepository->getActivePricesForLab($lab),
            'tooth_shades' => ToothShade::query()
                ->select(['id', 'code', 'name', 'sort_order'])
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get(),
        ];
    }
}
