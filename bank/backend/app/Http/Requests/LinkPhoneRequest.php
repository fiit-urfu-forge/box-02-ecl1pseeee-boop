<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkPhoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'phone' => [
                'required',
                'string',
                'regex:/^\+[1-9]\d{7,14}$/',
                Rule::unique('users', 'phone')->ignore($userId),
            ],
            'account_id' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Телефон должен быть в формате E.164, например +79001234567',
        ];
    }
}
