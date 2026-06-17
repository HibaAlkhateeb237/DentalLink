<?php

namespace App\Http\Requests;

use App\Models\DeliveryTask;
use App\Support\DeliveryStatus;
use App\Support\DeliveryTaskDirection;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class DeliveryEmployeeUpdateStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $deliveryTask = $this->route('deliveryTask');

        if (! $deliveryTask instanceof DeliveryTask) {
            return false;
        }

        return $this->user()?->can('update', $deliveryTask) ?? false;
    }

    public function rules(): array
    {
        $deliveryTask = $this->route('deliveryTask');
        $currentStatus = $deliveryTask->status;

        if ($deliveryTask->direction === DeliveryTaskDirection::TO_DOCTOR) {
            $allowedNextStatuses = DeliveryStatus::TRANSITIONS_To_Doctor[$currentStatus] ?? [];
        } elseif ($deliveryTask->direction === DeliveryTaskDirection::TO_LAB) {
            $allowedNextStatuses = DeliveryStatus::TRANSITIONS_To_Lab[$currentStatus] ?? [];
        } else {
            $allowedNextStatuses = [];
        }




        return [
            'status' => [
                'required',
                'string',
                'in:'.implode(',', $allowedNextStatuses),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => __('orders.delivery_status_transition_invalid'),
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
