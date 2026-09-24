<?php

namespace App\Http\Requests\Auth;

use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Registrasi dibuka untuk publik
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'fullName' => ['required', 'string', 'max:255'],
            'phone'    => ['required', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Email sudah terdaftar.',
            'password.min' => 'Password minimal 8 karakter.',
        ];
    }

    /**
     * Format error validasi supaya konsisten dengan pola
     * "Validation failed with N errors." seperti di endpoint yang sudah ada.
     */
    protected function failedValidation(ValidatorContract $validator): void
    {
        $errors = collect($validator->errors()->toArray())
            ->map(fn ($messages, $field) => "- {$field}: " . implode(', ', $messages))
            ->implode("\n");

        $count = $validator->errors()->count();

        throw new HttpResponseException(
            ApiResponse::error(
                "Validation failed with {$count} errors.\n{$errors}",
                null,
                422
            )
        );
    }
}
