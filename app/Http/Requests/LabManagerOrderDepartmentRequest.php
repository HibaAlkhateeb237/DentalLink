<?php

namespace App\Http\Requests;

use App\Models\DepartmentUserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LabManagerOrderDepartmentRequest extends FormRequest
{
    private ?int $managerLabId = null;

    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->hasRole('lab_manager')) {
            return false;
        }

        $this->managerLabId = $this->resolveManagerLabId($user);

        return true;
    }

    public function rules(): array
    {
        return [
            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('departments', 'id')->where(function ($query): void {
                    $query->where('lab_id', $this->managerLabId)
                        ->where('sort_order', '>', 0)
                        ->where('is_management', false);
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'department_ids.*.exists' => __('orders.department_not_in_lab'),
        ];
    }

    private function resolveManagerLabId(User $manager): int
    {
        $labId = DepartmentUserRole::query()
            ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
            ->join('departments', 'departments.id', '=', 'department_user_roles.department_id')
            ->where('department_user_roles.user_id', $manager->id)
            ->where('roles.name', 'lab_manager')
            ->where('roles.guard_name', 'sanctum')
            ->value('departments.lab_id');

        if ($labId === null) {
            throw ValidationException::withMessages([
                'lab_id' => [__('messages.not_found')],
            ]);
        }

        return (int) $labId;
    }

    public function getManagerLabId(): int
    {
        return $this->managerLabId;
    }
}
