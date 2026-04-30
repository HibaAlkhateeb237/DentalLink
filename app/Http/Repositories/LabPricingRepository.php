<?php

namespace App\Http\Repositories;

use App\Models\DentalCompensationTypePrice;
use App\Models\Lab;
use App\Models\LabPricingSetting;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class LabPricingRepository
{
    public function getActiveSettingForLab(Lab $lab, ?CarbonImmutable $at = null): ?LabPricingSetting
    {
        $at ??= CarbonImmutable::now();

        return LabPricingSetting::query()
            ->where('lab_id', $lab->id)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $at->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return Collection<int, DentalCompensationTypePrice>
     */
    public function getActivePricesForLab(Lab $lab, ?CarbonImmutable $at = null): Collection
    {
        $at ??= CarbonImmutable::now();

        return DentalCompensationTypePrice::query()
            ->select('dental_compensation_type_prices.*')
            ->join('dental_compensation_types', 'dental_compensation_types.id', '=', 'dental_compensation_type_prices.dental_compensation_type_id')
            ->where('dental_compensation_types.lab_id', $lab->id)
            ->where('dental_compensation_type_prices.is_active', true)
            ->whereDate('dental_compensation_type_prices.effective_from', '<=', $at->toDateString())
            ->orderBy('dental_compensation_types.category')
            ->orderBy('dental_compensation_types.name')
            ->with('dentalCompensationType')
            ->get();
    }

    public function findActivePriceByCode(Lab $lab, string $code, ?CarbonImmutable $at = null): ?DentalCompensationTypePrice
    {
        $at ??= CarbonImmutable::now();

        return DentalCompensationTypePrice::query()
            ->select('dental_compensation_type_prices.*')
            ->join('dental_compensation_types', 'dental_compensation_types.id', '=', 'dental_compensation_type_prices.dental_compensation_type_id')
            ->where('dental_compensation_types.lab_id', $lab->id)
            ->where('dental_compensation_types.code', $code)
            ->where('dental_compensation_type_prices.is_active', true)
            ->whereDate('dental_compensation_type_prices.effective_from', '<=', $at->toDateString())
            ->orderByDesc('dental_compensation_type_prices.effective_from')
            ->orderByDesc('dental_compensation_type_prices.id')
            ->with('dentalCompensationType')
            ->first();
    }
}
