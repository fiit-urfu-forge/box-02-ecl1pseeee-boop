"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, ArrowLeftRight, Copy } from "lucide-react";
import { api } from "@/lib/api";
import { Card, CardHeader, CardSubtitle, CardTitle } from "@/components/ui/Card";
import { Button } from "@/components/ui/Button";
import { Alert } from "@/components/ui/Alert";
import { Skeleton } from "@/components/ui/Skeleton";
import { AccountStatusBadge } from "@/components/domain/StatusBadge";
import { formatAccountNumber, formatDateTime, formatMoney } from "@/lib/format";
import { toast } from "@/components/ui/Toast";
import type { Account } from "@/types/api";

export default function AccountDetailPage() {
  const params = useParams<{ id: string }>();
  const id = params?.id ?? "";

  const q = useQuery({
    queryKey: ["account", id],
    queryFn: () => api.getAccount(id),
    refetchInterval: 30_000,
  });

  const copy = async (value: string) => {
    try {
      await navigator.clipboard.writeText(value);
      toast.success("Скопировано", "Номер счёта в буфере обмена");
    } catch {
      toast.error("Не удалось скопировать");
    }
  };

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <Link href="/accounts" className="inline-flex items-center gap-1 text-sm text-slate-600 hover:text-slate-900">
        <ArrowLeft className="h-4 w-4" /> К счетам
      </Link>

      {q.isLoading ? (
        <Skeleton className="h-64" />
      ) : q.isError ? (
        <Alert variant="danger">Не удалось загрузить счёт.</Alert>
      ) : q.data ? (
        <AccountBody account={q.data as Account} onCopy={copy} />
      ) : null}
    </div>
  );
}

function AccountBody({ account, onCopy }: { account: Account; onCopy: (s: string) => void }) {
  return (
    <Card>
      <CardHeader>
        <div>
          <CardTitle>
            {account.type === "checking" ? "Текущий" : "Сберегательный"} счёт · {account.currency}
          </CardTitle>
          <CardSubtitle>Открыт {formatDateTime(account.created_at)}</CardSubtitle>
        </div>
        <AccountStatusBadge status={account.status} />
      </CardHeader>

      <div className="rounded-2xl bg-gradient-to-br from-brand-600 to-brand-800 p-6 text-white shadow-card">
        <div className="text-sm opacity-80">Доступный баланс</div>
        <div className="mt-1 text-4xl font-semibold">
          {formatMoney(account.balance, account.currency)}
        </div>
        <div className="mt-6 flex items-center justify-between font-mono text-sm opacity-90">
          <span className="tracking-wider">{formatAccountNumber(account.account_number)}</span>
          <button
            type="button"
            onClick={() => onCopy(account.account_number)}
            className="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs hover:bg-white/10"
            aria-label="Скопировать номер"
          >
            <Copy className="h-3.5 w-3.5" /> Скопировать
          </button>
        </div>
      </div>

      <div className="mt-5 grid gap-3 sm:grid-cols-2">
        <Stat label="Валюта" value={account.currency} />
        <Stat label="Тип" value={account.type === "checking" ? "Текущий" : "Сберегательный"} />
        <Stat
          label="Суточный лимит"
          value={account.daily_limit ? formatMoney(account.daily_limit, account.currency) : "Системный лимит"}
        />
        <Stat label="Статус" value={<AccountStatusBadge status={account.status} />} />
      </div>

      <div className="mt-6 flex flex-wrap gap-3">
        <Link href={`/transfers/new?sender=${account.id}`}>
          <Button leftIcon={<ArrowLeftRight className="h-4 w-4" />} size="lg">
            Перевести со счёта
          </Button>
        </Link>
      </div>
    </Card>
  );
}

function Stat({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="rounded-xl border border-slate-100 bg-slate-50/50 px-4 py-3">
      <div className="text-xs uppercase tracking-wider text-slate-500">{label}</div>
      <div className="mt-1 text-sm font-medium text-slate-900">{value}</div>
    </div>
  );
}
