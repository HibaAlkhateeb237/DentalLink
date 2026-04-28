<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LabPortfolioStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'case_name' => ['required', 'string', 'max:255'],
            'before_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'after_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_published' => ['nullable', 'boolean'],
        ];
    }
}
