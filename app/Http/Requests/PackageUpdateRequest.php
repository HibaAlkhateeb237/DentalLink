<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PackageUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('update', $this->route('package'));
    }

    public function rules(): array
    {
        $packageId = $this->route('package')->id;

        return [
            'name' => ['sometimes', 'string', 'max:255', 'unique:packages,name,'.$packageId],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_days' => ['sometimes', 'integer', 'min:0'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
