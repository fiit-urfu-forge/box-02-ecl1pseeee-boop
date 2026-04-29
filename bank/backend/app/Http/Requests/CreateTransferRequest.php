<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'sender_account_id' => ['required', 'uuid'],
            // Accept either the receiver's 20-digit account number or an account UUID.
            'receiver_account_number' => ['required_without:receiver_account_id', 'string', 'regex:/^\d{20}$/'],
            'receiver_account_id' => ['required_without:receiver_account_number', 'uuid'],
            // amount is always a string — NEVER cast to float. Validate as a decimal
            // with up to 4 fractional digits.
            'amount' => ['required', 'string', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.regex' => 'Сумма должна быть десятичным числом с не более чем 4 знаками после запятой',
            'receiver_account_number.regex' => 'Номер счёта должен состоять из 20 цифр',
        ];
    }
}
