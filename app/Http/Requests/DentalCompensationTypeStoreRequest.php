<?php

namespace App\Http\Requests;

use App\Models\DentalCompensationType;
use Illuminate\Foundation\Http\FormRequest;

class DentalCompensationTypeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('create', DentalCompensationType::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0'],
            'category' => ['nullable', 'string', 'max:255'],
        ];
    }
}
