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

class EmployeeUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        if (! $employee instanceof User) {
            return false;
        }

        return $this->user()?->can('update', $employee) ?? false;
    }

    public function rules(): array
    {
        $employee = $this->route('employee');
        $employeeId = $employee instanceof User ? $employee->id : (int) $employee;
        $managerLabId = $this->resolveManagerLabId();
        $roleName = $this->resolveRoleName($employee instanceof User ? $employee : null);

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employeeId)],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^\d+$/'],
            'password' => ['sometimes', 'required', 'string', 'confirmed', Password::min(8)],
            'birthdate' => ['sometimes', 'required', 'date'],
            'joined_at' => ['sometimes', 'required', 'date'],
            'profile_image' => ['sometimes', 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'departments_ids' => ['sometimes', 'array', 'min:1'],
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
                'sometimes',
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
                $employee = $this->route('employee');
                $roleName = $this->resolveRoleName($employee instanceof User ? $employee : null);

                if ($roleName === 'department_manager') {
                    if ($this->filled('role_id') && ! $this->filled('departments_ids')) {
                        $validator->errors()->add('departments_ids', __('validation.required'));
                    }

                    return;
                }

                if ($this->filled('role_id') && ! $this->filled('departments_ids')) {
                    $validator->errors()->add('departments_ids', __('validation.required'));
                }

                $departments = $this->input('departments_ids', []);

                if (is_array($departments) && count($departments) > 1) {
                    $validator->errors()->add('departments_ids', __('validation.max.array', ['max' => 1]));
                }
            },
        ];
    }

    private function resolveRoleName(?User $employee): ?string
    {
        $roleId = $this->integer('role_id') ?: null;

        if ($roleId !== null) {
            return Role::query()->where('id', $roleId)->value('name');
        }

        if ($employee === null) {
            return null;
        }

        return DepartmentUserRole::query()
            ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
            ->where('department_user_roles.user_id', $employee->id)
            ->where('roles.guard_name', 'sanctum')
            ->whereIn('roles.name', EmployeeRoles::allowed())
            ->value('roles.name');
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
