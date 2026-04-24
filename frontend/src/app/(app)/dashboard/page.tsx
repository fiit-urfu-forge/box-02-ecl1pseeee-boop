"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import {
  ArrowLeftRight,
  CircleDollarSign,
  Plus,
  Smartphone,
  Wallet,
} from "lucide-react";
import { api } from "@/lib/api";
import { Card, CardHeader, CardSubtitle, CardTitle } from "@/components/ui/Card";
import { Button } from "@/components/ui/Button";
import { Skeleton } from "@/components/ui/Skeleton";
import { EmptyState } from "@/components/ui/EmptyState";
import { AccountStatusBadge, TransactionStatusBadge } from "@/components/domain/StatusBadge";
import { formatAccountNumber, formatDateTime, formatMoney } from "@/lib/format";
import { useAuthStore } from "@/stores/auth";
import type { Account, Transaction, Currency } from "@/types/api";

export default function DashboardPage() {
  const user = useAuthStore((s) => s.user);

  const accountsQ = useQuery({
    queryKey: ["accounts"],
    queryFn: api.listAccounts,
    // Real-time balance per §13.1 — poll every 30 s.
    refetchInterval: 30_000,
  });

  const transfersQ = useQuery({
    queryKey: ["transfers-recent"],
    queryFn: () => api.listTransfers(1, 5),
  });

  const totals = sumByCurrency((accountsQ.data ?? []) as Account[]);

  return (
    <div className="space-y-8">
      {/* Greeting + totals */}
      <section className="grid gap-4 md:grid-cols-3">
        <Card className="md:col-span-2">
          <CardSubtitle>Добро пожаловать</CardSubtitle>
          <h1 className="mt-1 text-2xl font-semibold text-slate-900">
            {user ? `${user.first_name}, рады снова видеть вас 👋` : "Ваш банк"}
          </h1>
          <p className="mt-1 max-w-xl text-sm text-slate-500">
            Быстрый доступ ко всем счетам и операциям. Балансы обновляются автоматически каждые 30 секунд.
          </p>
          <div className="mt-6 flex flex-wrap gap-3">
            <Link href="/transfers/new">
              <Button leftIcon={<ArrowLeftRight className="h-4 w-4" />} size="lg">
                Перевести
              </Button>
            </Link>
            <Link href="/accounts/new">
              <Button variant="secondary" leftIcon={<Plus className="h-4 w-4" />} size="lg">
                Открыть счёт
              </Button>
            </Link>
            <Link href="/sbp">
              <Button variant="ghost" leftIcon={<Smartphone className="h-4 w-4" />} size="lg">
                СБП
              </Button>
            </Link>
          </div>
        </Card>

        <Card>
          <CardSubtitle>Итого по всем счетам</CardSubtitle>
          <div className="mt-2 space-y-2">
            {accountsQ.isLoading ? (
              <>
                <Skeleton className="h-7 w-40" />
                <Skeleton className="h-7 w-32" />
              </>
            ) : totals.length === 0 ? (
              <div className="text-sm text-slate-500">Пока нет счетов.</div>
            ) : (
              totals.map((t) => (
                <div key={t.currency} className="flex items-baseline gap-2">
                  <CircleDollarSign className="h-4 w-4 text-slate-400" />
                  <span className="text-2xl font-semibold text-slate-900">
                    {formatMoney(t.total, t.currency)}
                  </span>
                </div>
              ))
            )}
          </div>
        </Card>
      </section>

      {/* Accounts grid */}
      <section>
        <div className="mb-3 flex items-center justify-between">
          <h2 className="text-lg font-semibold text-slate-900">Ваши счета</h2>
          <Link href="/accounts" className="text-sm font-medium text-brand-700 hover:text-brand-800">
            Все счета →
          </Link>
        </div>
        {accountsQ.isLoading ? (
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {[0, 1, 2].map((i) => (
              <Skeleton key={i} className="h-36" />
            ))}
          </div>
        ) : (accountsQ.data ?? []).length === 0 ? (
          <EmptyState
            icon={<Wallet className="h-8 w-8" />}
            title="У вас ещё нет счетов"
            body="Откройте первый счёт, чтобы начать пользоваться банком."
            action={
              <Link href="/accounts/new">
                <Button leftIcon={<Plus className="h-4 w-4" />}>Открыть счёт</Button>
              </Link>
            }
          />
        ) : (
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {(accountsQ.data as Account[]).map((a) => (
              <AccountCard key={a.id} a={a} />
            ))}
          </div>
        )}
      </section>

      {/* Recent transfers */}
      <section>
        <div className="mb-3 flex items-center justify-between">
          <h2 className="text-lg font-semibold text-slate-900">Последние переводы</h2>
          <Link href="/transfers" className="text-sm font-medium text-brand-700 hover:text-brand-800">
            История целиком →
          </Link>
        </div>
        <Card className="p-0">
          {transfersQ.isLoading ? (
            <div className="space-y-3 p-6">
              {[0, 1, 2].map((i) => (
                <Skeleton key={i} className="h-10" />
              ))}
            </div>
          ) : (transfersQ.data?.data ?? []).length === 0 ? (
            <div className="p-6">
              <EmptyState
                icon={<ArrowLeftRight className="h-8 w-8" />}
                title="Ещё не было переводов"
                body="Отправьте первый перевод — он появится здесь."
                action={
                  <Link href="/transfers/new">
                    <Button>Совершить перевод</Button>
                  </Link>
                }
                className="border-none bg-transparent"
              />
            </div>
          ) : (
            <ul className="divide-y divide-slate-100">
              {(transfersQ.data!.data as Transaction[]).map((t) => (
                <li key={t.id} className="flex items-center justify-between gap-3 px-6 py-3.5">
                  <div className="min-w-0">
                    <div className="flex items-center gap-2">
                      <TransactionStatusBadge status={t.status} />
                      <span className="truncate text-sm text-slate-500">
                        {t.type === "internal"
                          ? "Внутренний перевод"
                          : t.type === "sbp_out"
                            ? "СБП · исходящий"
                            : "СБП · входящий"}
                      </span>
                    </div>
                    <div className="mt-1 truncate text-sm text-slate-900">
                      {t.description || "Без описания"}
                    </div>
                  </div>
                  <div className="text-right">
                    <div className="text-sm font-semibold text-slate-900">
                      {formatMoney(t.amount, t.currency)}
                    </div>
                    <div className="text-xs text-slate-500">{formatDateTime(t.created_at)}</div>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </section>
    </div>
  );
}

function AccountCard({ a }: { a: Account }) {
  return (
    <Link
      href={`/accounts/${a.id}`}
      className="group flex flex-col gap-3 rounded-2xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-5 shadow-card transition hover:-translate-y-0.5 hover:shadow-lift"
    >
      <div className="flex items-center justify-between">
        <span className="text-xs uppercase tracking-wider text-slate-500">
          {a.type === "checking" ? "Текущий счёт" : "Сберегательный"}
        </span>
        <AccountStatusBadge status={a.status} />
      </div>
      <div>
        <div className="text-xs text-slate-500">Баланс</div>
        <div className="mt-1 text-2xl font-semibold text-slate-900">
          {formatMoney(a.balance, a.currency)}
        </div>
      </div>
      <div className="mt-auto font-mono text-xs tracking-wider text-slate-500">
        {formatAccountNumber(a.account_number)}
      </div>
    </Link>
  );
}

function sumByCurrency(accounts: Account[]): Array<{ currency: Currency; total: string }> {
  const totals = new Map<Currency, number>();
  for (const a of accounts) {
    const cur = a.currency;
    const v = Number.parseFloat(a.balance);
    totals.set(cur, (totals.get(cur) ?? 0) + (Number.isFinite(v) ? v : 0));
  }
  return Array.from(totals, ([currency, total]) => ({ currency, total: total.toFixed(2) }));
}
