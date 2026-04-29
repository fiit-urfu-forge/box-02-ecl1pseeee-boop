"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, ArrowRightLeft } from "lucide-react";
import { api } from "@/lib/api";
import { Card, CardHeader, CardSubtitle, CardTitle } from "@/components/ui/Card";
import { Alert } from "@/components/ui/Alert";
import { Skeleton } from "@/components/ui/Skeleton";
import { TransactionStatusBadge } from "@/components/domain/StatusBadge";
import { formatDateTime, formatMoney } from "@/lib/format";
import type { Transaction } from "@/types/api";

export default function TransferDetailPage() {
  const params = useParams<{ id: string }>();
  const id = params?.id ?? "";

  const q = useQuery({
    queryKey: ["transfer", id],
    queryFn: () => api.getTransfer(id),
    // Keep polling while the transfer is not terminal.
    refetchInterval: (q) => {
      const data = q.state.data as Transaction | undefined;
      if (!data) return 4000;
      return ["pending", "processing"].includes(data.status) ? 4000 : false;
    },
  });

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <Link href="/transfers" className="inline-flex items-center gap-1 text-sm text-slate-600 hover:text-slate-900">
        <ArrowLeft className="h-4 w-4" /> К истории
      </Link>

      {q.isLoading ? (
        <Skeleton className="h-64" />
      ) : q.isError || !q.data ? (
        <Alert variant="danger">Не удалось загрузить операцию.</Alert>
      ) : (
        <TransferBody tx={q.data as Transaction} />
      )}
    </div>
  );
}

function TransferBody({ tx }: { tx: Transaction }) {
  return (
    <Card>
      <CardHeader>
        <div>
          <CardTitle>Перевод</CardTitle>
          <CardSubtitle>{formatDateTime(tx.created_at)}</CardSubtitle>
        </div>
        <TransactionStatusBadge status={tx.status} />
      </CardHeader>

      <div className="rounded-2xl bg-slate-50 p-6 text-center">
        <div className="text-sm text-slate-500">Сумма операции</div>
        <div className="mt-2 text-4xl font-semibold text-slate-900">
          {formatMoney(tx.amount, tx.currency)}
        </div>
        {tx.description && (
          <div className="mt-2 text-sm text-slate-600">«{tx.description}»</div>
        )}
      </div>

      {tx.status === "failed" && tx.error_code && (
        <Alert variant="danger" className="mt-5" title="Операция не выполнена">
          Код: {tx.error_code}. Баланс не изменён.
        </Alert>
      )}

      {(tx.status === "pending" || tx.status === "processing") && (
        <Alert variant="info" className="mt-5" title="Операция обрабатывается">
          Обычно это занимает пару секунд. Статус обновится автоматически.
        </Alert>
      )}

      <div className="mt-6 grid gap-3 sm:grid-cols-2">
        <Stat label="Тип" value={labelForType(tx.type)} icon={<ArrowRightLeft className="h-4 w-4" />} />
        <Stat label="Комиссия" value={formatMoney(tx.fee_amount, tx.currency)} />
        <Stat label="ID операции" value={<span className="font-mono text-xs">{tx.id}</span>} />
        <Stat
          label="Завершена"
          value={tx.processed_at ? formatDateTime(tx.processed_at) : "Ещё нет"}
        />
      </div>
    </Card>
  );
}

function labelForType(t: Transaction["type"]): string {
  return t === "internal" ? "Внутренний" : t === "sbp_out" ? "СБП · исходящий" : "СБП · входящий";
}

function Stat({ label, value, icon }: { label: string; value: React.ReactNode; icon?: React.ReactNode }) {
  return (
    <div className="rounded-xl border border-slate-100 bg-white px-4 py-3">
      <div className="flex items-center gap-2 text-xs uppercase tracking-wider text-slate-500">
        {icon}
        {label}
      </div>
      <div className="mt-1 text-sm font-medium text-slate-900">{value}</div>
    </div>
  );
}
