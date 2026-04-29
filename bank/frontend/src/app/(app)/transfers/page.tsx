"use client";

import Link from "next/link";
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeftRight, ChevronLeft, ChevronRight, Plus } from "lucide-react";
import { api } from "@/lib/api";
import { Card } from "@/components/ui/Card";
import { Button } from "@/components/ui/Button";
import { Skeleton } from "@/components/ui/Skeleton";
import { EmptyState } from "@/components/ui/EmptyState";
import { TransactionStatusBadge } from "@/components/domain/StatusBadge";
import { formatDateTime, formatMoney } from "@/lib/format";
import type { Transaction } from "@/types/api";

const PER_PAGE = 20;

export default function TransfersListPage() {
  const [page, setPage] = useState(1);

  const q = useQuery({
    queryKey: ["transfers", page],
    queryFn: () => api.listTransfers(page, PER_PAGE),
    placeholderData: (prev) => prev,
  });

  const items = (q.data?.data ?? []) as Transaction[];
  const pag = q.data?.pagination;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">История переводов</h1>
          <p className="mt-1 text-sm text-slate-500">
            Все операции отображаются с актуальным статусом и кодом ошибки (если он есть).
          </p>
        </div>
        <Link href="/transfers/new">
          <Button leftIcon={<Plus className="h-4 w-4" />} size="lg">
            Новый перевод
          </Button>
        </Link>
      </div>

      {q.isLoading ? (
        <Skeleton className="h-64" />
      ) : items.length === 0 ? (
        <EmptyState
          icon={<ArrowLeftRight className="h-8 w-8" />}
          title="Операций пока нет"
          body="Все ваши переводы появятся здесь."
          action={
            <Link href="/transfers/new">
              <Button leftIcon={<Plus className="h-4 w-4" />}>Совершить перевод</Button>
            </Link>
          }
        />
      ) : (
        <>
          <Card className="p-0">
            <table className="w-full text-left">
              <thead>
                <tr className="text-xs uppercase tracking-wider text-slate-500">
                  <th className="px-6 py-3 font-medium">Дата</th>
                  <th className="px-6 py-3 font-medium">Тип</th>
                  <th className="px-6 py-3 font-medium">Статус</th>
                  <th className="px-6 py-3 text-right font-medium">Сумма</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {items.map((t) => (
                  <tr key={t.id} className="hover:bg-slate-50/50">
                    <td className="px-6 py-3 align-top">
                      <Link
                        href={`/transfers/${t.id}`}
                        className="text-sm text-slate-900 hover:underline"
                      >
                        {formatDateTime(t.created_at)}
                      </Link>
                      <div className="mt-0.5 max-w-sm truncate text-xs text-slate-500">
                        {t.description || "Без описания"}
                      </div>
                    </td>
                    <td className="px-6 py-3 text-sm text-slate-700">
                      {t.type === "internal"
                        ? "Внутренний"
                        : t.type === "sbp_out"
                          ? "СБП · исходящий"
                          : "СБП · входящий"}
                    </td>
                    <td className="px-6 py-3">
                      <TransactionStatusBadge status={t.status} />
                      {t.error_code && (
                        <div className="mt-1 text-xs text-rose-600">{t.error_code}</div>
                      )}
                    </td>
                    <td className="px-6 py-3 text-right text-sm font-semibold text-slate-900">
                      {formatMoney(t.amount, t.currency)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </Card>

          {pag && pag.last_page > 1 && (
            <nav className="flex items-center justify-between text-sm" aria-label="Пагинация">
              <div className="text-slate-500">
                Страница {pag.current_page} из {pag.last_page} · всего {pag.total}
              </div>
              <div className="flex items-center gap-2">
                <Button
                  type="button"
                  variant="secondary"
                  size="sm"
                  disabled={page <= 1}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  leftIcon={<ChevronLeft className="h-4 w-4" />}
                >
                  Назад
                </Button>
                <Button
                  type="button"
                  variant="secondary"
                  size="sm"
                  disabled={page >= pag.last_page}
                  onClick={() => setPage((p) => p + 1)}
                  rightIcon={<ChevronRight className="h-4 w-4" />}
                >
                  Далее
                </Button>
              </div>
            </nav>
          )}
        </>
      )}
    </div>
  );
}
