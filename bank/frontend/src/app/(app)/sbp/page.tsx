"use client";

import { useRef, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { Phone, Smartphone, Hash, CircleDollarSign, Send } from "lucide-react";
import { api, ApiException, newIdempotencyKey } from "@/lib/api";
import { Card, CardHeader, CardSubtitle, CardTitle } from "@/components/ui/Card";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import { Button } from "@/components/ui/Button";
import { Alert } from "@/components/ui/Alert";
import { Skeleton } from "@/components/ui/Skeleton";
import { toast } from "@/components/ui/Toast";
import { useAuthStore } from "@/stores/auth";
import { formatAccountNumber, formatMoney } from "@/lib/format";
import type { Account } from "@/types/api";

const LinkSchema = z.object({
  phone: z.string().regex(/^\+[1-9]\d{7,14}$/, "Формат: +79001234567"),
  account_id: z.string().uuid("Выберите счёт"),
});
type LinkForm = z.infer<typeof LinkSchema>;

const TransferSchema = z.object({
  sender_account_id: z.string().uuid("Выберите счёт"),
  receiver_phone: z.string().regex(/^\+[1-9]\d{7,14}$/, "Формат: +79001234567"),
  amount: z.string().regex(/^\d{1,15}(?:\.\d{1,4})?$/, "Сумма — число"),
  description: z.string().max(255).optional().or(z.literal("")),
});
type TransferForm = z.infer<typeof TransferSchema>;

export default function SbpPage() {
  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <div>
        <h1 className="text-2xl font-semibold text-slate-900">СБП — переводы по телефону</h1>
        <p className="mt-1 text-sm text-slate-500">
          Привяжите телефон к счёту и отправляйте переводы по номеру. В MVP подключён mock-шлюз:
          операция создаётся, но не исполняется внешним партнёром.
        </p>
      </div>

      <LinkPhoneCard />
      <SbpTransferCard />
    </div>
  );
}

function LinkPhoneCard() {
  const user = useAuthStore((s) => s.user);
  const accountsQ = useQuery({ queryKey: ["accounts"], queryFn: api.listAccounts });
  const [serverError, setServerError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<LinkForm>({
    resolver: zodResolver(LinkSchema),
    defaultValues: { phone: user?.phone ?? "", account_id: "" },
  });

  const onSubmit = async (values: LinkForm) => {
    setServerError(null);
    try {
      await api.linkPhone(values);
      toast.success("Телефон привязан", values.phone);
    } catch (e) {
      setServerError(e instanceof ApiException ? e.userMessage : "Не удалось привязать телефон.");
    }
  };

  const accounts = (accountsQ.data ?? []) as Account[];

  return (
    <Card>
      <CardHeader>
        <div>
          <CardTitle>Привязка телефона</CardTitle>
          <CardSubtitle>Номер в формате E.164: +7..., +1..., и т.п.</CardSubtitle>
        </div>
        <Phone className="h-6 w-6 text-brand-600" />
      </CardHeader>

      {serverError && <Alert variant="danger" className="mb-4">{serverError}</Alert>}

      {accountsQ.isLoading ? (
        <Skeleton className="h-40" />
      ) : accounts.length === 0 ? (
        <Alert variant="warning">Сначала откройте счёт.</Alert>
      ) : (
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
          <Input
            label="Телефон"
            placeholder="+79001234567"
            leftAdornment={<Phone className="h-4 w-4" />}
            error={errors.phone?.message}
            {...register("phone")}
          />
          <Select
            label="Счёт для СБП"
            error={errors.account_id?.message}
            {...register("account_id")}
            options={[
              { value: "", label: "— Выберите счёт —" },
              ...accounts.map((a) => ({
                value: a.id,
                label: `${formatAccountNumber(a.account_number)} · ${formatMoney(a.balance, a.currency)}`,
              })),
            ]}
          />
          <Button type="submit" loading={isSubmitting} leftIcon={<Phone className="h-4 w-4" />}>
            Привязать телефон
          </Button>
        </form>
      )}
    </Card>
  );
}

function SbpTransferCard() {
  const qc = useQueryClient();
  const keyRef = useRef(newIdempotencyKey());
  const [serverError, setServerError] = useState<string | null>(null);

  const accountsQ = useQuery({ queryKey: ["accounts"], queryFn: api.listAccounts });

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<TransferForm>({
    resolver: zodResolver(TransferSchema),
    defaultValues: { sender_account_id: "", receiver_phone: "", amount: "", description: "" },
  });

  const onSubmit = async (values: TransferForm) => {
    setServerError(null);
    try {
      const tx = await api.sbpTransfer(keyRef.current, {
        sender_account_id: values.sender_account_id,
        receiver_phone: values.receiver_phone,
        amount: values.amount,
        description: values.description || undefined,
      });
      qc.invalidateQueries({ queryKey: ["accounts"] });
      qc.invalidateQueries({ queryKey: ["transfers-recent"] });
      toast.info(
        "Запрос отправлен в СБП",
        `Статус: ${tx.status}. В MVP операция остаётся в pending.`,
      );
      keyRef.current = newIdempotencyKey();
    } catch (e) {
      setServerError(e instanceof ApiException ? e.userMessage : "Не удалось отправить перевод.");
    }
  };

  const accounts = (accountsQ.data ?? []) as Account[];

  return (
    <Card>
      <CardHeader>
        <div>
          <CardTitle>Перевод по номеру</CardTitle>
          <CardSubtitle>
            MVP-заглушка: баланс не списывается, транзакция создаётся в статусе «в очереди».
          </CardSubtitle>
        </div>
        <Smartphone className="h-6 w-6 text-brand-600" />
      </CardHeader>

      {serverError && <Alert variant="danger" className="mb-4">{serverError}</Alert>}

      {accountsQ.isLoading ? (
        <Skeleton className="h-56" />
      ) : accounts.length === 0 ? (
        <Alert variant="warning">Сначала откройте счёт.</Alert>
      ) : (
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
          <Select
            label="Счёт списания"
            error={errors.sender_account_id?.message}
            {...register("sender_account_id")}
            options={[
              { value: "", label: "— Выберите счёт —" },
              ...accounts.map((a) => ({
                value: a.id,
                label: `${formatAccountNumber(a.account_number)} · ${formatMoney(a.balance, a.currency)}`,
              })),
            ]}
          />
          <Input
            label="Телефон получателя"
            placeholder="+79001234567"
            leftAdornment={<Hash className="h-4 w-4" />}
            error={errors.receiver_phone?.message}
            {...register("receiver_phone")}
          />
          <Input
            label="Сумма"
            placeholder="0.00"
            leftAdornment={<CircleDollarSign className="h-4 w-4" />}
            error={errors.amount?.message}
            {...register("amount")}
          />
          <Input
            label="Назначение (необязательно)"
            error={errors.description?.message}
            {...register("description")}
          />
          <Button type="submit" loading={isSubmitting} leftIcon={<Send className="h-4 w-4" />}>
            Отправить
          </Button>
        </form>
      )}
    </Card>
  );
}
