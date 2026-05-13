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
        $labRouteParameter = $this->route('lab');
        $labId = $labRouteParameter instanceof Lab ? $labRouteParameter->id : (int) $labRouteParameter;

        $manager = User::query()
            ->select(['users.id'])
            ->join('department_user_roles', 'department_user_roles.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
            ->join('departments', 'departments.id', '=', 'department_user_roles.department_id')
            ->where('departments.lab_id', $labId)
            ->where('roles.name', 'lab_manager')
            ->where('roles.guard_name', 'sanctum')
            ->orderBy('users.id')
            ->first();

        return [
            'lab_name' => ['required', 'string', 'max:255', Rule::unique('labs', 'name')->ignore($labId)],
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
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:5120', 'dimensions:min_width=100,min_height=100'],
        ];
    }
}
