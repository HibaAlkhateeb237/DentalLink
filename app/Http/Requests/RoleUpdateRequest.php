<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleUpdateRequest extends FormRequest
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
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->ignore($this->route('role'))
                    ->where('guard_name', 'sanctum'),
            ],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ];
    }
}
