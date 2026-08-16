<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SystemLogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'level' => ['nullable', 'string', 'in:info,warning,error'],
            'event' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
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
