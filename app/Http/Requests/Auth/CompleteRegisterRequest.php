<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class CompleteRegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'verification_token' => $this->bearerToken(),
        ]);
    }

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
            'verification_token' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:20'],
            'birthdate' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'location_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'location_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'profile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
