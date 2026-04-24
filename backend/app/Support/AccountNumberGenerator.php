<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ApiException;
use App\Models\Account;

/**
 * Generates 20-digit account numbers where the first three digits encode
 * the currency per §5.2 of the SPEC (810 for RUB, 840 for USD). The
 * remaining 17 digits are random.
 *
 * Uniqueness is enforced at the DB level via a UNIQUE constraint. We retry
 * a bounded number of times to dodge the astronomically small collision
 * window; anything beyond that is a sign of something much more wrong
 * (e.g. exhausted randomness source) and is surfaced as INTERNAL_ERROR.
 */
final class AccountNumberGenerator
{
    private const MAX_ATTEMPTS = 8;

    public static function forCurrency(string $currency): string
    {
        $prefix = config("digitalbank.accounts.currency_prefix.$currency");
        if (! is_string($prefix) || $prefix === '') {
            throw new ApiException(
                ErrorCode::VALIDATION_ERROR,
                "Неподдерживаемая валюта: $currency",
                ['currency' => $currency],
            );
        }

        for ($i = 0; $i < self::MAX_ATTEMPTS; $i++) {
            $candidate = $prefix.self::randomDigits(20 - strlen($prefix));
            if (! Account::query()->where('account_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new ApiException(
            ErrorCode::INTERNAL_ERROR,
            'Не удалось сгенерировать уникальный номер счёта',
        );
    }

    private static function randomDigits(int $length): string
    {
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= (string) random_int(0, 9);
        }
        return $out;
    }
}
