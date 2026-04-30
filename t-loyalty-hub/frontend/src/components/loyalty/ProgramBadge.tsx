import { Badge } from '@/components/ui/Badge';

interface Props {
  name: string;
  currency: string;
}

const TONE_BY_NAME: Record<string, 'default' | 'info' | 'warning' | 'success'> = {
  Black: 'default',
  Platinum: 'info',
  'All Airlines': 'info',
  Bravo: 'warning',
};

const CURRENCY_LABEL: Record<string, string> = {
  RUB: '₽',
  MILES: 'миль',
  BRAVO: 'Bravo',
};

export function ProgramBadge({ name, currency }: Props) {
  return (
    <Badge tone={TONE_BY_NAME[name] ?? 'default'}>
      {name} · {CURRENCY_LABEL[currency] ?? currency}
    </Badge>
  );
}
