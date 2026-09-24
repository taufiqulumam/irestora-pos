<?php

namespace App\Http\Requests\Shift;

use Illuminate\Foundation\Http\FormRequest;

class CloseShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'closing_cash_actual' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'closing_cash_actual.required' => 'Kas fisik akhir wajib diisi',
            'closing_cash_actual.numeric' => 'Kas fisik harus berupa angka',
            'closing_cash_actual.min' => 'Kas fisik tidak boleh negatif',
        ];
    }
}