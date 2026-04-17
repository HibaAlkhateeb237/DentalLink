<?php

namespace App\Http\Requests\Auth;

use App\Support\Authorization\Rbac;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignRoleRequest extends FormRequest
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
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'role' => ['required', 'string', Rule::in(Rbac::roles())],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
        ];
    }
}
