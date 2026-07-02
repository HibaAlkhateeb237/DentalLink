<?php

namespace App\Http\Requests;

use App\Support\DeliveryTaskDirection;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class DeliveryEmployeeTaskIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'tab' => ['nullable', 'string', 'in:assigned,completed'],
            'direction' => ['nullable', 'string', 'in:'.implode(',', DeliveryTaskDirection::ALL)],
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
