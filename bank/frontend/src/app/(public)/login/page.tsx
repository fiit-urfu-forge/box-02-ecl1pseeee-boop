"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { LogIn, Mail, Lock } from "lucide-react";
import { Card, CardTitle, CardSubtitle } from "@/components/ui/Card";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { Alert } from "@/components/ui/Alert";
import { api, ApiException } from "@/lib/api";
import { useAuthStore } from "@/stores/auth";
import { toast } from "@/components/ui/Toast";

const Schema = z.object({
  email: z.string().email("Введите корректный email"),
  password: z.string().min(1, "Введите пароль"),
});
type Form = z.infer<typeof Schema>;

export default function LoginPage() {
  const router = useRouter();
  const setUser = useAuthStore((s) => s.setUser);
  const [serverError, setServerError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<Form>({
    resolver: zodResolver(Schema),
    mode: "onBlur",
  });

  const onSubmit = async (values: Form) => {
    setServerError(null);
    try {
      const user = await api.login(values);
      setUser(user);
      toast.success("Добро пожаловать!", `Вы вошли как ${user.email}`);
      router.replace("/dashboard");
    } catch (e) {
      const msg = e instanceof ApiException ? e.userMessage : "Не удалось войти.";
      setServerError(msg);
    }
  };

  return (
    <div className="grid w-full max-w-5xl gap-10 md:grid-cols-2">
      <aside className="hidden flex-col justify-between md:flex">
        <div>
          <h1 className="text-3xl font-bold tracking-tight text-slate-900">
            Ваш цифровой банк —<br /> проще, чем кажется.
          </h1>
          <p className="mt-4 max-w-md text-slate-600">
            Откройте счёт, отправляйте переводы между своими и чужими счетами, а также по СБП —
            всё за пару кликов. Подробный аудит каждой операции и безопасная сессия Sanctum.
          </p>
        </div>
        <ul className="mt-10 space-y-3 text-sm text-slate-600">
          <li className="flex items-center gap-2">
            <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
            Мгновенные переводы между своими счетами
          </li>
          <li className="flex items-center gap-2">
            <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
            Атомарные операции с CHECK-гарантией баланса ≥ 0
          </li>
          <li className="flex items-center gap-2">
            <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
            Защита от дублирования через X-Idempotency-Key
          </li>
        </ul>
      </aside>

      <Card className="w-full max-w-md justify-self-end p-8">
        <div className="mb-6">
          <CardTitle className="text-xl">Вход в личный кабинет</CardTitle>
          <CardSubtitle className="mt-1">
            Войдите, чтобы просмотреть счета и совершать переводы.
          </CardSubtitle>
        </div>

        {serverError && (
          <Alert variant="danger" className="mb-4">
            {serverError}
          </Alert>
        )}

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
          <Input
            label="E-mail"
            type="email"
            autoComplete="email"
            placeholder="you@example.com"
            leftAdornment={<Mail className="h-4 w-4" />}
            error={errors.email?.message}
            {...register("email")}
          />
          <Input
            label="Пароль"
            type="password"
            autoComplete="current-password"
            placeholder="••••••••"
            leftAdornment={<Lock className="h-4 w-4" />}
            error={errors.password?.message}
            {...register("password")}
          />

          <Button
            type="submit"
            loading={isSubmitting}
            leftIcon={<LogIn className="h-4 w-4" />}
            fullWidth
            size="lg"
          >
            Войти
          </Button>
        </form>

        <p className="mt-6 text-center text-sm text-slate-600">
          Ещё нет аккаунта?{" "}
          <Link href="/register" className="font-medium text-brand-700 hover:text-brand-800">
            Зарегистрироваться
          </Link>
        </p>
      </Card>
    </div>
  );
}
