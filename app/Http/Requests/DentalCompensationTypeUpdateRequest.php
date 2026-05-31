<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DentalCompensationTypeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('update', $this->route('dental_compensation_type'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'category' => ['nullable', 'string', 'max:255'],
        ];
    }
}
