<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string|\Illuminate\Contracts\Validation\ValidationRule>> */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'min:1', 'max:100'],
            'last_name' => ['required', 'string', 'min:1', 'max:100'],
            'email' => ['required', 'string', 'email:rfc,strict', 'max:255', 'unique:users,email'],
            // Password policy: ≥ 8 chars, mixed case, number, symbol.
            'password' => ['required', 'string', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'phone' => ['nullable', 'string', 'regex:/^\+[1-9]\d{7,14}$/', 'unique:users,phone'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Телефон должен быть в формате E.164, например +79001234567',
        ];
    }
}
