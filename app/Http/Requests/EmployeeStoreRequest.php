<?php

namespace App\Http\Requests;

use App\Models\DepartmentUserRole;
use App\Models\Role;
use App\Models\User;
use App\Support\EmployeeRoles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class EmployeeStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    public function rules(): array
    {
        $managerLabId = $this->resolveManagerLabId();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'regex:/^\d+$/'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
            'birthdate' => ['required', 'date'],
            'joined_at' => ['required', 'date'],
            'profile_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(function ($query) use ($managerLabId): void {
                    if ($managerLabId !== null) {
                        $query->where('lab_id', $managerLabId);

                        return;
                    }

                    $query->whereRaw('1 = 0');
                }),
            ],
            'departments_ids' => ['nullable', 'array', 'min:1'],
            'departments_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('departments', 'id')->where(function ($query) use ($managerLabId): void {
                    if ($managerLabId !== null) {
                        $query->where('lab_id', $managerLabId);

                        return;
                    }

                    $query->whereRaw('1 = 0');
                }),
            ],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(function ($query): void {
                    $query
                        ->where('guard_name', 'sanctum')
                        ->whereIn('name', EmployeeRoles::allowed());
                }),
            ],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $roleName = $this->resolveRoleName();

                if ($roleName === 'department_manager') {
                    if (! $this->filled('departments_ids')) {
                        $validator->errors()->add('departments_ids', __('validation.required'));
                    }

                    if ($this->filled('department_id')) {
                        $validator->errors()->add('department_id', __('validation.prohibited'));
                    }

                    return;
                }

                if ($this->filled('departments_ids')) {
                    $validator->errors()->add('departments_ids', __('validation.prohibited'));
                }

                if (! $this->filled('department_id')) {
                    $validator->errors()->add('department_id', __('validation.required'));
                }
            },
        ];
    }

    private function resolveRoleName(): ?string
    {
        $roleId = $this->integer('role_id') ?: null;

        if ($roleId === null) {
            return null;
        }

        return Role::query()->where('id', $roleId)->value('name');
    }

    private function resolveManagerLabId(): ?int
    {
        $user = $this->user();

        if ($user === null) {
            return null;
        }

        return DepartmentUserRole::query()
            ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
            ->join('departments', 'departments.id', '=', 'department_user_roles.department_id')
            ->where('department_user_roles.user_id', $user->id)
            ->where('roles.name', 'lab_manager')
            ->where('roles.guard_name', 'sanctum')
            ->value('departments.lab_id');
    }
}
