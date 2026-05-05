<?php

namespace App\Http\Requests\Auth;

use App\Rules\ValidEmailDomain;
use Illuminate\Foundation\Http\FormRequest;

class VerifyRegisterOtpRequest extends FormRequest
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
            'email' => ['required', 'email:rfc', new ValidEmailDomain(), 'max:255'],
            'code' => ['required', 'digits:6'],
        ];
    }
}
