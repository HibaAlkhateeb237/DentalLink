<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LabDeliverySettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'normal_delivery_days' => ['required', 'integer', 'min:0', 'max:60'],
            'urgent_delivery_days' => ['required', 'integer', 'min:0', 'max:60'],
        ];
    }
}
