import type { Currency } from "@/types/api";

const SYMBOL: Record<Currency, string> = { RUB: "₽", USD: "$" };

/**
 * Formats a NUMERIC(19,4) string as a human currency string. Strings are
 * kept string-typed throughout the stack (per §14 of SPEC — no floats),
 * so formatting is done with `Intl.NumberFormat` over a parsed Number just
 * for presentation. Never reuse the Number for calculations.
 */
export function formatMoney(amount: string | number, currency: Currency): string {
  const numeric = typeof amount === "string" ? Number.parseFloat(amount) : amount;
  const formatter = new Intl.NumberFormat("ru-RU", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  return `${formatter.format(numeric)} ${SYMBOL[currency]}`;
}

/** Groups a 20-digit account number as 4 · 4 · 4 · 4 · 4 for readability. */
export function formatAccountNumber(n: string): string {
  return n.match(/.{1,4}/g)?.join(" ") ?? n;
}

export function formatDateTime(iso: string | null | undefined): string {
  if (!iso) return "—";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "—";
  return new Intl.DateTimeFormat("ru-RU", {
    day: "2-digit",
    month: "long",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(d);
}

export function initialsOf(firstName: string, lastName: string): string {
  return `${firstName.charAt(0)}${lastName.charAt(0)}`.toUpperCase();
}
