<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SbpTransferRequest extends FormRequest
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
            'receiver_phone' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
            'amount' => ['required', 'string', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
