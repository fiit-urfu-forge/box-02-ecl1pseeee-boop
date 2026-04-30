const RUB_FORMATTER = new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 });
const MILES_FORMATTER = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 });

export function formatRub(value: number): string {
  return RUB_FORMATTER.format(value);
}

export function formatNumber(value: number): string {
  return MILES_FORMATTER.format(value);
}

export function formatByCurrency(value: number, currency: string): string {
  switch (currency.toUpperCase()) {
    case 'RUB':
      return formatRub(value);
    case 'MILES':
      return `${formatNumber(value)} миль`;
    case 'BRAVO':
      return `${formatNumber(value)} Bravo`;
    default:
      return `${formatNumber(value)} ${currency}`;
  }
}

export function formatDate(iso: string | null | undefined): string {
  if (!iso) return '';
  const d = new Date(iso);
  return d.toLocaleDateString('ru-RU', { day: '2-digit', month: 'short', year: 'numeric' });
}
