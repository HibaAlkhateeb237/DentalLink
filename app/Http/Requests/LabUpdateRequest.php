<?php

namespace App\Http\Requests;

use App\Models\Lab;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class LabUpdateRequest extends FormRequest
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
        /** @var Lab $lab */
        $lab = $this->route('lab');

        $manager = User::query()
            ->select(['id'])
            ->where('lab_id', $lab->id)
            ->whereHas('roles', function ($query): void {
                $query->where('name', 'lab_manager')->where('guard_name', 'sanctum');
            })
            ->orderBy('id')
            ->first();

        return [
            'lab_name' => ['required', 'string', 'max:255', Rule::unique('labs', 'name')->ignore($lab->id)],
            'manager_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'location' => ['required', 'string', 'max:1000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($manager?->id),
            ],
            'password' => ['nullable', 'string', 'confirmed', Password::min(8)],
        ];
    }
}
