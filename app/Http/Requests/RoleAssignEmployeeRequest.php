<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleAssignEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
        ];
    }
}
