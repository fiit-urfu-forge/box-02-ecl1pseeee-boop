import type { ErrorCode } from "@/types/api";

/**
 * Human-readable, end-user-facing copy for every API error code (§6.2).
 * Technical details are NEVER surfaced to the UI per §13.2 of SPEC.
 */
export const ERROR_COPY: Record<ErrorCode, string> = {
  INSUFFICIENT_FUNDS: "Недостаточно средств на счёте.",
  ACCOUNT_NOT_FOUND: "Счёт не найден.",
  ACCOUNT_FROZEN: "Один из счетов заморожен или недоступен.",
  DAILY_LIMIT_EXCEEDED: "Превышен суточный лимит переводов.",
  AMOUNT_TOO_LOW: "Сумма перевода меньше минимальной.",
  AMOUNT_TOO_HIGH: "Сумма перевода больше допустимой.",
  CURRENCY_MISMATCH: "Валюты счётов не совпадают.",
  IDEMPOTENCY_CONFLICT:
    "Операция уже выполняется или была отменена. Повторите попытку через пару секунд.",
  USER_NOT_FOUND: "Получатель не найден.",
  SELF_TRANSFER_SAME_ACCOUNT: "Нельзя переводить на тот же счёт.",
  UNAUTHENTICATED: "Необходимо войти в систему.",
  FORBIDDEN: "Недостаточно прав для этого действия.",
  VALIDATION_ERROR: "Проверьте правильность заполнения полей.",
  INTERNAL_ERROR: "Произошла ошибка. Мы уже разбираемся — попробуйте позже.",
  TOO_MANY_REQUESTS: "Слишком много запросов. Подождите минуту.",
  NOT_FOUND: "Ресурс не найден.",
  METHOD_NOT_ALLOWED: "Метод не поддерживается.",
};

export function messageFor(code: string | undefined, fallback = ERROR_COPY.INTERNAL_ERROR): string {
  if (!code) return fallback;
  return ERROR_COPY[code as ErrorCode] ?? fallback;
}
