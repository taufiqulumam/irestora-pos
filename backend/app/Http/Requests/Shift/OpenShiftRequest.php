<?php

namespace App\Http\Requests\Shift;

use Illuminate\Foundation\Http\FormRequest;

class OpenShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'outlet_id' => ['required', 'uuid', 'exists:outlets,id'],
            'opening_cash' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'outlet_id.required' => 'Outlet wajib dipilih',
            'outlet_id.exists' => 'Outlet tidak ditemukan',
            'opening_cash.required' => 'Modal awal kas wajib diisi',
            'opening_cash.numeric' => 'Modal awal harus berupa angka',
            'opening_cash.min' => 'Modal awal tidak boleh negatif',
        ];
    }
}