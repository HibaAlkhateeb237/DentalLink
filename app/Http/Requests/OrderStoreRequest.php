<?php

namespace App\Http\Requests;

use App\Models\DentalCompensationTypePrice;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class OrderStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $labId = (int) $this->input('lab_id');

        $allowedTypeIds = DentalCompensationTypePrice::query()
            ->select('dental_compensation_type_prices.dental_compensation_type_id')
            ->join('dental_compensation_types', 'dental_compensation_types.id', '=', 'dental_compensation_type_prices.dental_compensation_type_id')
            ->where('dental_compensation_types.lab_id', $labId)
            ->where('dental_compensation_type_prices.is_active', true)
            ->whereDate('dental_compensation_type_prices.effective_from', '<=', Carbon::now()->toDateString())
            ->distinct()
            ->pluck('dental_compensation_type_prices.dental_compensation_type_id')
            ->all();

        $allowedToothNumbers = array_merge(range(11, 18), range(21, 28), range(31, 38), range(41, 48));

        return [
            'lab_id' => ['required', 'integer', Rule::exists('labs', 'id')],
            'patient_name' => ['required', 'string', 'max:255'],
            'tooth_shade_id' => ['required', 'integer', Rule::exists('tooth_shades', 'id')->where('is_active', true)],
            'dental_compensation_type_id' => ['required', 'integer', Rule::in($allowedTypeIds)],
            'priority' => ['required', Rule::in(['normal', 'urgent'])],
            'order_type' => ['nullable', Rule::in(['digital', 'physical', 'hybrid'])],
            'case_type' => ['required', Rule::in(['normal', 'implant', 'bridge'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'teeth' => ['required', 'array', 'min:1', 'max:32'],
            'teeth.*.tooth_number' => ['required', 'integer', Rule::in($allowedToothNumbers), 'distinct'],
            'teeth.*.notes' => ['nullable', 'string', 'max:1000'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'mimetypes:application/sla,application/octet-stream,application/zip,image/jpeg,image/png,image/gif,image/webp', 'max:51200'],
        ];
    }

    // 'mimes:stl,zip,jpg,jpeg,png,gif,webp'
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