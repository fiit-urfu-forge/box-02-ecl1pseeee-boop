"use client";

import Link from "next/link";
import { useState } from "react";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, Check, Wallet } from "lucide-react";
import { api, ApiException } from "@/lib/api";
import { Card, CardHeader, CardSubtitle, CardTitle } from "@/components/ui/Card";
import { Button } from "@/components/ui/Button";
import { Select } from "@/components/ui/Select";
import { Alert } from "@/components/ui/Alert";
import { toast } from "@/components/ui/Toast";
import { cn } from "@/lib/cn";

const Schema = z.object({
  currency: z.enum(["RUB", "USD"]),
  type: z.enum(["checking", "savings"]),
});
type Form = z.infer<typeof Schema>;

export default function NewAccountPage() {
  const router = useRouter();
  const qc = useQueryClient();
  const [serverError, setServerError] = useState<string | null>(null);

  const {
    handleSubmit,
    watch,
    setValue,
    formState: { isSubmitting },
  } = useForm<Form>({
    resolver: zodResolver(Schema),
    defaultValues: { currency: "RUB", type: "checking" },
  });

  const currency = watch("currency");
  const type = watch("type");

  const onSubmit = async (values: Form) => {
    setServerError(null);
    try {
      const created = await api.createAccount(values);
      qc.invalidateQueries({ queryKey: ["accounts"] });
      toast.success("Счёт открыт", `${values.currency} · ${labelForType(values.type)}`);
      router.push(`/accounts/${created.id}`);
    } catch (e) {
      setServerError(e instanceof ApiException ? e.userMessage : "Не удалось открыть счёт.");
    }
  };

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <Link href="/accounts" className="inline-flex items-center gap-1 text-sm text-slate-600 hover:text-slate-900">
        <ArrowLeft className="h-4 w-4" /> К счетам
      </Link>

      <Card>
        <CardHeader>
          <div>
            <CardTitle>Новый счёт</CardTitle>
            <CardSubtitle>Настройте валюту и тип. Комиссия за открытие — 0 ₽.</CardSubtitle>
          </div>
          <Wallet className="h-6 w-6 text-brand-600" />
        </CardHeader>

        {serverError && (
          <Alert variant="danger" className="mb-5">
            {serverError}
          </Alert>
        )}

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6" noValidate>
          <fieldset>
            <legend className="label mb-3">Валюта</legend>
            <div className="grid grid-cols-2 gap-3">
              <CurrencyOption
                selected={currency === "RUB"}
                onClick={() => setValue("currency", "RUB", { shouldDirty: true })}
                title="Рубль"
                subtitle="RUB · 810"
                symbol="₽"
              />
              <CurrencyOption
                selected={currency === "USD"}
                onClick={() => setValue("currency", "USD", { shouldDirty: true })}
                title="Доллар"
                subtitle="USD · 840"
                symbol="$"
              />
            </div>
          </fieldset>

          <fieldset>
            <legend className="label mb-3">Тип счёта</legend>
            <div className="grid grid-cols-2 gap-3">
              <TypeOption
                selected={type === "checking"}
                onClick={() => setValue("type", "checking", { shouldDirty: true })}
                title="Текущий"
                subtitle="Для ежедневных операций"
              />
              <TypeOption
                selected={type === "savings"}
                onClick={() => setValue("type", "savings", { shouldDirty: true })}
                title="Сберегательный"
                subtitle="Для накоплений"
              />
            </div>
          </fieldset>

          <div className="flex gap-3 pt-2">
            <Button type="submit" loading={isSubmitting} size="lg" leftIcon={<Check className="h-4 w-4" />}>
              Открыть счёт
            </Button>
            <Link href="/accounts">
              <Button type="button" variant="ghost" size="lg">
                Отмена
              </Button>
            </Link>
          </div>
        </form>
      </Card>
    </div>
  );
}

function labelForType(t: "checking" | "savings"): string {
  return t === "checking" ? "Текущий" : "Сберегательный";
}

function CurrencyOption({
  selected,
  onClick,
  title,
  subtitle,
  symbol,
}: {
  selected: boolean;
  onClick: () => void;
  title: string;
  subtitle: string;
  symbol: string;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={selected}
      className={cn(
        "flex items-center justify-between rounded-2xl border p-4 text-left transition",
        selected
          ? "border-brand-500 bg-brand-50 ring-2 ring-brand-200"
          : "border-slate-200 bg-white hover:border-slate-300",
      )}
    >
      <div>
        <div className="text-base font-semibold text-slate-900">{title}</div>
        <div className="text-xs uppercase tracking-wider text-slate-500">{subtitle}</div>
      </div>
      <div className={cn("text-2xl font-semibold", selected ? "text-brand-600" : "text-slate-400")}>
        {symbol}
      </div>
    </button>
  );
}

function TypeOption({
  selected,
  onClick,
  title,
  subtitle,
}: {
  selected: boolean;
  onClick: () => void;
  title: string;
  subtitle: string;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={selected}
      className={cn(
        "flex flex-col rounded-2xl border p-4 text-left transition",
        selected
          ? "border-brand-500 bg-brand-50 ring-2 ring-brand-200"
          : "border-slate-200 bg-white hover:border-slate-300",
      )}
    >
      <span className="text-base font-semibold text-slate-900">{title}</span>
      <span className="mt-1 text-sm text-slate-500">{subtitle}</span>
    </button>
  );
}
