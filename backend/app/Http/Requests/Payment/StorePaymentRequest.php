<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'method' => ['required', Rule::in(['cash', 'qris', 'card', 'other'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'reference_number' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'method.required' => 'Metode pembayaran wajib dipilih',
            'method.in' => 'Metode pembayaran tidak valid',
            'amount.required' => 'Jumlah pembayaran wajib diisi',
            'amount.numeric' => 'Jumlah pembayaran harus berupa angka',
            'amount.min' => 'Jumlah pembayaran tidak boleh negatif',
        ];
    }
}