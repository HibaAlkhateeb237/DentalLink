<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Support\OrderStatus;

class ReceptionistOrderIndexRequest extends FormRequest
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
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', Rule::in(OrderStatus::ALL)],
            'priority' => ['nullable', Rule::in(['normal', 'urgent'])],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'lab_id' => ['nullable', 'integer', 'exists:labs,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'sort_by' => ['nullable', Rule::in(['created_at', 'status', 'priority', 'price'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'requires_resubmission' => ['nullable', 'boolean'],
        ];
    }
}