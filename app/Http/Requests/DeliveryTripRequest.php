<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class DeliveryTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['required', 'integer', 'exists:delivery_tasks,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'task_ids.required' => __('orders.tracking_task_ids_required'),
            'task_ids.array' => __('orders.tracking_task_ids_invalid'),
            'task_ids.min' => __('orders.tracking_task_ids_min_one'),
            'task_ids.*.exists' => __('orders.tracking_task_not_found'),
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'status' => 400,
            'message' => __('messages.validation_failed'),
            'data' => null,
            'errors' => $validator->errors(),
        ], 400));
    }
}
