import { Badge } from "@/components/ui/Badge";
import type { AccountStatus, TransactionStatus } from "@/types/api";

const ACCOUNT: Record<AccountStatus, { label: string; variant: Parameters<typeof Badge>[0]["variant"] }> = {
  active: { label: "Активен", variant: "success" },
  frozen: { label: "Заморожен", variant: "warning" },
  closed: { label: "Закрыт", variant: "neutral" },
};

const TX: Record<TransactionStatus, { label: string; variant: Parameters<typeof Badge>[0]["variant"] }> = {
  pending: { label: "В очереди", variant: "pending" },
  processing: { label: "Обрабатывается", variant: "info" },
  success: { label: "Успешно", variant: "success" },
  failed: { label: "Ошибка", variant: "danger" },
  cancelled: { label: "Отменено", variant: "neutral" },
};

export function AccountStatusBadge({ status }: { status: AccountStatus }) {
  const it = ACCOUNT[status];
  return <Badge variant={it.variant}>{it.label}</Badge>;
}

export function TransactionStatusBadge({ status }: { status: TransactionStatus }) {
  const it = TX[status];
  return <Badge variant={it.variant}>{it.label}</Badge>;
}
