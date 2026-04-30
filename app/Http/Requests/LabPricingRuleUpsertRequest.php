<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabPricingRuleUpsertRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $lab = $this->route('lab');
        $rule = $this->route('labPricingRule');

        $ruleId = is_object($rule) ? $rule->id : null;
        $labId = is_object($lab) ? $lab->id : null;

        return [
            'code' => [
                'required',
                'string',
                'max:120',
                Rule::unique('lab_pricing_rules', 'code')
                    ->where(fn ($query) => $query
                        ->where('lab_id', $labId)
                        ->whereDate('effective_from', (string) $this->input('effective_from'))
                    )
                    ->ignore($ruleId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'effective_from' => ['required', 'date'],
            'applies_to' => ['required', 'string', Rule::in(['order', 'item'])],
            'kind' => ['required', 'string', Rule::in(['fixed_addon', 'multiplier', 'percent_discount'])],
            'value' => ['required', 'numeric', 'min:0'],
            'per_unit' => ['nullable', 'boolean'],
            'condition' => ['nullable', 'array'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'per_unit' => $this->boolean('per_unit'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }
}
