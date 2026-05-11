<?php

namespace App\Http\Requests;

use App\Models\DepartmentUserRole;
use App\Models\User;
use App\Support\EmployeeRoles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employeeId)],
            'password' => ['sometimes', 'required', 'string', 'confirmed', Password::min(8)],
            'birthdate' => ['sometimes', 'required', 'date'],
            'joined_at' => ['sometimes', 'required', 'date'],
            'profile_image' => ['sometimes', 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'department_id' => [
                'sometimes',
                Rule::requiredIf($this->filled('role_id')),
                'integer',
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
                Rule::requiredIf($this->filled('department_id')),
                'integer',
                Rule::exists('roles', 'id')->where(function ($query): void {
                    $query
                        ->where('guard_name', 'sanctum')
                        ->whereIn('name', EmployeeRoles::allowed());
                }),
            ],
        ];
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
