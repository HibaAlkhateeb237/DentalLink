<?php

namespace App\Http\Requests;

use App\Models\Package;
use Illuminate\Foundation\Http\FormRequest;

class PackageStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('create', Package::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:packages,name'],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_days' => ['required', 'integer', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
