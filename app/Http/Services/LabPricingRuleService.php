<?php

namespace App\Http\Services;

use App\Models\Lab;
use App\Models\LabPricingRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class LabPricingRuleService
{
    /**
     * @param  array{code:string,name:string,effective_from:string,applies_to:string,kind:string,value:numeric,per_unit?:bool,condition?:array|null,sort_order?:int,is_active?:bool}  $validated
     */
    public function create(Lab $lab, array $validated): LabPricingRule
    {
        return DB::transaction(function () use ($lab, $validated): LabPricingRule {
            $rule = new LabPricingRule;
            $rule->fill([
                'lab_id' => $lab->id,
                'code' => $validated['code'],
                'name' => $validated['name'],
                'effective_from' => $validated['effective_from'],
                'applies_to' => $validated['applies_to'],
                'kind' => $validated['kind'],
                'value' => $validated['value'],
                'per_unit' => (bool) ($validated['per_unit'] ?? false),
                'condition' => Arr::get($validated, 'condition'),
                'sort_order' => (int) ($validated['sort_order'] ?? 100),
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);
            $rule->save();

            return $rule->fresh();
        });
    }

    /**
     * @param  array{code:string,name:string,effective_from:string,applies_to:string,kind:string,value:numeric,per_unit?:bool,condition?:array|null,sort_order?:int,is_active?:bool}  $validated
     */
    public function update(LabPricingRule $rule, array $validated): LabPricingRule
    {
        return DB::transaction(function () use ($rule, $validated): LabPricingRule {
            $rule->fill([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'effective_from' => $validated['effective_from'],
                'applies_to' => $validated['applies_to'],
                'kind' => $validated['kind'],
                'value' => $validated['value'],
                'per_unit' => (bool) ($validated['per_unit'] ?? false),
                'condition' => Arr::get($validated, 'condition'),
                'sort_order' => (int) ($validated['sort_order'] ?? 100),
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);
            $rule->save();

            return $rule->fresh();
        });
    }

    public function disable(LabPricingRule $rule): LabPricingRule
    {
        return DB::transaction(function () use ($rule): LabPricingRule {
            $rule->fill(['is_active' => false]);
            $rule->save();

            return $rule->fresh();
        });
    }
}
