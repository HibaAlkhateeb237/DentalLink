<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Password;

class LabStoreRequest extends FormRequest
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
            'lab_name' => ['required', 'string', 'max:255', 'unique:labs,name'],
            'manager_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'location' => ['required', 'string', 'max:1000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $failedRules = $validator->failed();
        $hasUniqueConflict = collect($failedRules)
            ->flatMap(fn (array $rules): array => array_keys($rules))
            ->contains(fn (string $rule): bool => $rule === 'Unique');

        $status = $hasUniqueConflict ? 409 : 400;

        throw new HttpResponseException(response()->json([
            'success' => false,
            'status' => $status,
            'message' => __('messages.validation_failed'),
            'data' => null,
            'errors' => $validator->errors(),
        ], $status));
    }
}
