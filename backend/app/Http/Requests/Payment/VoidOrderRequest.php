<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class VoidOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
            'approved_by_pin' => ['required', 'string', 'min:4', 'max:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan void wajib diisi',
            'approved_by_pin.required' => 'PIN approval wajib diisi',
            'approved_by_pin.min' => 'PIN minimal 4 digit',
            'approved_by_pin.max' => 'PIN maksimal 6 digit',
        ];
    }
}