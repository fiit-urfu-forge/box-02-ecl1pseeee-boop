"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { Plus, Wallet } from "lucide-react";
import { api } from "@/lib/api";
import { Card } from "@/components/ui/Card";
import { Button } from "@/components/ui/Button";
import { Skeleton } from "@/components/ui/Skeleton";
import { EmptyState } from "@/components/ui/EmptyState";
import { AccountStatusBadge } from "@/components/domain/StatusBadge";
import { formatAccountNumber, formatMoney } from "@/lib/format";
import type { Account } from "@/types/api";

export default function AccountsPage() {
  const q = useQuery({
    queryKey: ["accounts"],
    queryFn: api.listAccounts,
    refetchInterval: 30_000,
  });

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">Счета</h1>
          <p className="mt-1 text-sm text-slate-500">
            Все ваши счета в одном месте. Баланс обновляется каждые 30 секунд.
          </p>
        </div>
        <Link href="/accounts/new">
          <Button leftIcon={<Plus className="h-4 w-4" />} size="lg">
            Открыть счёт
          </Button>
        </Link>
      </div>

      {q.isLoading ? (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {[0, 1, 2].map((i) => (
            <Skeleton key={i} className="h-36" />
          ))}
        </div>
      ) : (q.data ?? []).length === 0 ? (
        <EmptyState
          icon={<Wallet className="h-8 w-8" />}
          title="Нет ни одного счёта"
          body="Откройте свой первый счёт за считанные секунды."
          action={
            <Link href="/accounts/new">
              <Button leftIcon={<Plus className="h-4 w-4" />}>Открыть счёт</Button>
            </Link>
          }
        />
      ) : (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {(q.data as Account[]).map((a) => (
            <AccountTile key={a.id} account={a} />
          ))}
        </div>
      )}
    </div>
  );
}

function AccountTile({ account }: { account: Account }) {
  return (
    <Link
      href={`/accounts/${account.id}`}
      className="group block"
    >
      <Card className="h-full transition group-hover:-translate-y-0.5 group-hover:shadow-lift">
        <div className="flex items-center justify-between">
          <span className="text-xs uppercase tracking-wider text-slate-500">
            {account.type === "checking" ? "Текущий" : "Сберегательный"} · {account.currency}
          </span>
          <AccountStatusBadge status={account.status} />
        </div>
        <div className="mt-6 text-3xl font-semibold text-slate-900">
          {formatMoney(account.balance, account.currency)}
        </div>
        <div className="mt-6 font-mono text-sm text-slate-500">
          {formatAccountNumber(account.account_number)}
        </div>
      </Card>
    </Link>
  );
}
