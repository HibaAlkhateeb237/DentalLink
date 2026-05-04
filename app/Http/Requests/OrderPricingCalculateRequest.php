<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderPricingCalculateRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var Order|null $order */
        $order = $this->route('order');

        return [
            'compensation_code' => [
                'required',
                'string',
                Rule::exists('dental_compensation_types', 'code')
                    ->where(fn ($query) => $query
                        ->where('lab_id', $order?->lab_id)
                    ),
            ],
            'units' => ['nullable', 'integer', 'min:1', 'max:32'],
            'is_implant' => ['nullable', 'boolean'],
            'is_long_bridge_or_high' => ['nullable', 'boolean'],
            'include_lisi_connect_etching' => ['nullable', 'boolean'],
            'include_intraoral_print_examples' => ['nullable', 'boolean'],
            'is_vip' => ['nullable', 'boolean'],
            'apply_student_discount' => ['nullable', 'boolean'],
            'student_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'persist' => ['nullable', 'boolean'],
        ];
    }
}
