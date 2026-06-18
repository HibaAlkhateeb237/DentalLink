<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleMatrixUpdateRequest extends FormRequest
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
            'matrix' => ['required', 'array'],
            'matrix.*.role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'matrix.*.permissions' => ['present', 'array'],
            'matrix.*.permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ];
    }
}
