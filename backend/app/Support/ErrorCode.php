<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Error codes mirror §6.2 of the SPEC. Keep this list in sync with the
 * frontend `types/api.ts`.
 */
enum ErrorCode: string
{
    case INSUFFICIENT_FUNDS = 'INSUFFICIENT_FUNDS';
    case ACCOUNT_NOT_FOUND = 'ACCOUNT_NOT_FOUND';
    case ACCOUNT_FROZEN = 'ACCOUNT_FROZEN';
    case DAILY_LIMIT_EXCEEDED = 'DAILY_LIMIT_EXCEEDED';
    case AMOUNT_TOO_LOW = 'AMOUNT_TOO_LOW';
    case AMOUNT_TOO_HIGH = 'AMOUNT_TOO_HIGH';
    case CURRENCY_MISMATCH = 'CURRENCY_MISMATCH';
    case IDEMPOTENCY_CONFLICT = 'IDEMPOTENCY_CONFLICT';
    case USER_NOT_FOUND = 'USER_NOT_FOUND';
    case SELF_TRANSFER_SAME_ACCOUNT = 'SELF_TRANSFER_SAME_ACCOUNT';
    case UNAUTHENTICATED = 'UNAUTHENTICATED';
    case FORBIDDEN = 'FORBIDDEN';
    case VALIDATION_ERROR = 'VALIDATION_ERROR';
    case INTERNAL_ERROR = 'INTERNAL_ERROR';
    case TOO_MANY_REQUESTS = 'TOO_MANY_REQUESTS';
    case NOT_FOUND = 'NOT_FOUND';
    case METHOD_NOT_ALLOWED = 'METHOD_NOT_ALLOWED';

    public function httpStatus(): int
    {
        return match ($this) {
            self::UNAUTHENTICATED => 401,
            self::FORBIDDEN => 403,
            self::USER_NOT_FOUND,
            self::ACCOUNT_NOT_FOUND,
            self::NOT_FOUND => 404,
            self::METHOD_NOT_ALLOWED => 405,
            self::IDEMPOTENCY_CONFLICT => 409,
            self::TOO_MANY_REQUESTS => 429,
            self::INTERNAL_ERROR => 500,
            default => 422,
        };
    }

    public function defaultMessage(): string
    {
        return match ($this) {
            self::INSUFFICIENT_FUNDS => 'Недостаточно средств на счёте',
            self::ACCOUNT_NOT_FOUND => 'Счёт получателя не найден',
            self::ACCOUNT_FROZEN => 'Счёт отправителя или получателя заморожен',
            self::DAILY_LIMIT_EXCEEDED => 'Превышен дневной лимит переводов',
            self::AMOUNT_TOO_LOW => 'Сумма перевода ниже минимально допустимой',
            self::AMOUNT_TOO_HIGH => 'Сумма перевода превышает разовый лимит',
            self::CURRENCY_MISMATCH => 'Валюты счёта-отправителя и получателя не совпадают',
            self::IDEMPOTENCY_CONFLICT => 'Ключ идемпотентности занят другой операцией',
            self::USER_NOT_FOUND => 'Получатель не найден',
            self::SELF_TRANSFER_SAME_ACCOUNT => 'Нельзя переводить на тот же счёт',
            self::UNAUTHENTICATED => 'Пользователь не авторизован',
            self::FORBIDDEN => 'Нет доступа к ресурсу',
            self::VALIDATION_ERROR => 'Ошибка валидации входных данных',
            self::INTERNAL_ERROR => 'Внутренняя ошибка сервера',
            self::TOO_MANY_REQUESTS => 'Слишком много запросов',
            self::NOT_FOUND => 'Ресурс не найден',
            self::METHOD_NOT_ALLOWED => 'Метод не поддерживается',
        };
    }
}
