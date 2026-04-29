<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'currency' => ['required', 'string', Rule::in([Account::CURRENCY_RUB, Account::CURRENCY_USD])],
            'type' => ['required', 'string', Rule::in([Account::TYPE_CHECKING, Account::TYPE_SAVINGS])],
        ];
    }
}
