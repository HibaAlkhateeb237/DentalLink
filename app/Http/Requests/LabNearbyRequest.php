<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LabNearbyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'doctor_id' => ['required', 'integer', 'exists:users,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get the validation "after" hooks for the request.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $doctorId = $this->integer('doctor_id');

                if ($doctorId === 0) {
                    return;
                }

                $doctor = User::query()
                    ->select(['id', 'location_lat', 'location_lng'])
                    ->find($doctorId);

                if ($doctor === null) {
                    return;
                }

                if ($doctor->location_lat === null || $doctor->location_lng === null) {
                    $validator->errors()->add('doctor_id', 'The selected doctor must have a location.');
                }
            },
        ];
    }
}
