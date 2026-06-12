<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DoctorOrderTrackRequest extends FormRequest
{
    public function authorize(): bool
    {

        $order = $this->route('order');
        return $order && $order->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [];
    }
}
