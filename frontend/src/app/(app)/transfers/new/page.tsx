"use client";

import Link from "next/link";
import { Suspense, useEffect, useMemo, useRef, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, ArrowRight, Hash, CircleDollarSign } from "lucide-react";
import { api, ApiException, newIdempotencyKey } from "@/lib/api";
import { Card, CardHeader, CardSubtitle, CardTitle } from "@/components/ui/Card";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import { Button } from "@/components/ui/Button";
import { Alert } from "@/components/ui/Alert";
import { Skeleton } from "@/components/ui/Skeleton";
import { formatAccountNumber, formatMoney } from "@/lib/format";
import { toast } from "@/components/ui/Toast";
import type { Account } from "@/types/api";

const Schema = z.object({
  sender_account_id: z.string().uuid("Выберите счёт-отправитель"),
  receiver_account_number: z
    .string()
    .regex(/^\d{20}$/, "Номер счёта получателя — 20 цифр"),
  amount: z
    .string()
    .regex(/^\d{1,15}(?:\.\d{1,4})?$/, "Сумма — число до 4 знаков после точки")
    .refine((v) => Number.parseFloat(v) > 0, { message: "Сумма должна быть больше 0" }),
  description: z.string().max(255).optional().or(z.literal("")),
});
type Form = z.infer<typeof Schema>;

export default function NewTransferPage() {
  return (
    <Suspense fallback={<div className="h-64 animate-pulse rounded-2xl bg-slate-100" />}>
      <NewTransferForm />
    </Suspense>
  );
}

function NewTransferForm() {
  const router = useRouter();
  const search = useSearchParams();
  const qc = useQueryClient();

  // Per §13.3 — generate the idempotency key once on mount, reset only on success.
  const keyRef = useRef(newIdempotencyKey());
  const [serverError, setServerError] = useState<string | null>(null);

  const accountsQ = useQuery({ queryKey: ["accounts"], queryFn: api.listAccounts });

  const {
    register,
    handleSubmit,
    setValue,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<Form>({
    resolver: zodResolver(Schema),
    mode: "onBlur",
    defaultValues: {
      sender_account_id: search.get("sender") ?? "",
      receiver_account_number: "",
      amount: "",
      description: "",
    },
  });

  const accounts = (accountsQ.data ?? []) as Account[];
  const senderId = watch("sender_account_id");
  const sender = useMemo(
    () => accounts.find((a) => a.id === senderId),
    [accounts, senderId],
  );

  // Preselect first active account if user landed without ?sender=.
  useEffect(() => {
    if (senderId || accounts.length === 0) return;
    const first = accounts.find((a) => a.status === "active") ?? accounts[0];
    setValue("sender_account_id", first.id);
  }, [accounts, senderId, setValue]);

  const onSubmit = async (values: Form) => {
    setServerError(null);
    try {
      const tx = await api.createTransfer(keyRef.current, {
        sender_account_id: values.sender_account_id,
        receiver_account_number: values.receiver_account_number,
        amount: values.amount,
        description: values.description || undefined,
      });
      qc.invalidateQueries({ queryKey: ["accounts"] });
      qc.invalidateQueries({ queryKey: ["transfers-recent"] });

      if (tx.status === "failed") {
        toast.error("Перевод не выполнен", tx.error_code ?? "Проверьте детали операции");
      } else if (tx.status === "success") {
        toast.success("Перевод отправлен", `${values.amount} ${sender?.currency ?? ""}`);
      } else {
        toast.info("Перевод принят в обработку", "Статус можно отслеживать на странице операции");
      }
      // §13.3 — reset key on successful dispatch (regardless of terminal state).
      keyRef.current = newIdempotencyKey();
      router.push(`/transfers/${tx.id}`);
    } catch (e) {
      setServerError(e instanceof ApiException ? e.userMessage : "Не удалось выполнить перевод.");
    }
  };

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <Link href="/transfers" className="inline-flex items-center gap-1 text-sm text-slate-600 hover:text-slate-900">
        <ArrowLeft className="h-4 w-4" /> К переводам
      </Link>

      <Card>
        <CardHeader>
          <div>
            <CardTitle>Новый перевод</CardTitle>
            <CardSubtitle>
              Между своими счетами или на номер счёта другого клиента.
            </CardSubtitle>
          </div>
          <ArrowRight className="h-6 w-6 text-brand-600" />
        </CardHeader>

        {serverError && (
          <Alert variant="danger" className="mb-5">
            {serverError}
          </Alert>
        )}

        {accountsQ.isLoading ? (
          <Skeleton className="h-64" />
        ) : accounts.length === 0 ? (
          <Alert variant="warning" title="Сначала откройте счёт">
            У вас нет ни одного активного счёта. {" "}
            <Link href="/accounts/new" className="font-medium underline">
              Открыть счёт
            </Link>
          </Alert>
        ) : (
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-5" noValidate>
            <Select
              label="Счёт списания"
              error={errors.sender_account_id?.message}
              {...register("sender_account_id")}
              options={accounts.map((a) => ({
                value: a.id,
                label: `${formatAccountNumber(a.account_number)} · ${formatMoney(a.balance, a.currency)}`,
              }))}
            />

            <Input
              label="Номер счёта получателя"
              placeholder="20 цифр"
              inputMode="numeric"
              leftAdornment={<Hash className="h-4 w-4" />}
              error={errors.receiver_account_number?.message}
              hint="Номер выдаёт банк получателя. Начинается на 810 (RUB) или 840 (USD)."
              {...register("receiver_account_number")}
            />

            <Input
              label="Сумма"
              placeholder="0.00"
              inputMode="decimal"
              leftAdornment={<CircleDollarSign className="h-4 w-4" />}
              rightAdornment={<span className="text-sm text-slate-500">{sender?.currency ?? ""}</span>}
              error={errors.amount?.message}
              hint="Минимум 1 ₽ / 0.01 $, максимум 100 000 ₽ / 2 000 $ за операцию."
              {...register("amount")}
            />

            <Input
              label="Назначение (необязательно)"
              placeholder="Например: оплата аренды"
              error={errors.description?.message}
              {...register("description")}
            />

            <div className="flex items-center gap-3 pt-1">
              <Button type="submit" loading={isSubmitting} size="lg" leftIcon={<ArrowRight className="h-4 w-4" />}>
                Отправить перевод
              </Button>
              <Link href="/transfers">
                <Button type="button" variant="ghost" size="lg">
                  Отмена
                </Button>
              </Link>
            </div>
          </form>
        )}
      </Card>
    </div>
  );
}
