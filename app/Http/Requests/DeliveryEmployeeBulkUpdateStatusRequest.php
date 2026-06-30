<?php

namespace App\Http\Requests;

use App\Support\DeliveryStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class DeliveryEmployeeBulkUpdateStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('delivery.update-status') ?? false;
    }

    public function rules(): array
    {
        return [
            'delivery_task_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'delivery_task_ids.*' => [
                'required',
                'integer',
                'exists:delivery_tasks,id',
            ],
            'status' => [
                'required',
                'string',
                'in:'.implode(',', DeliveryStatus::ALL),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'delivery_task_ids.required' => __('orders.delivery_task_ids_required'),
            'delivery_task_ids.min' => __('orders.delivery_task_ids_min_one'),
            'delivery_task_ids.*.exists' => __('orders.delivery_task_id_not_found'),
            'status.in' => __('orders.delivery_status_invalid'),
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

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'status' => 403,
            'message' => __('auth.forbidden'),
            'data' => null,
            'errors' => null,
        ], 403));
    }
}
