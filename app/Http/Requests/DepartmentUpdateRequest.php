<?php

namespace App\Http\Requests;

use App\Models\Department;
use App\Models\DepartmentUserRole;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class DepartmentUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $department = $this->route('department');

        if (! $department instanceof Department) {
            return false;
        }

        return $this->user()?->can('update', $department) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $managerLabId = $this->resolveManagerLabId();
        $department = $this->route('department');
        $departmentId = $department instanceof Department ? $department->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')
                    ->where(function ($query) use ($managerLabId): void {
                        if ($managerLabId !== null) {
                            $query->where('lab_id', $managerLabId);

                            return;
                        }

                        $query->whereRaw('1 = 0');
                    })
                    ->ignore($departmentId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $failedRules = $validator->failed();
        $hasUniqueConflict = collect($failedRules)
            ->flatMap(fn(array $rules): array => array_keys($rules))
            ->contains(fn(string $rule): bool => $rule === 'Unique');

        $status = $hasUniqueConflict ? 409 : 400;

        throw new HttpResponseException(response()->json([
            'success' => false,
            'status' => $status,
            'message' => __('messages.validation_failed'),
            'data' => null,
            'errors' => $validator->errors(),
        ], $status));
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
