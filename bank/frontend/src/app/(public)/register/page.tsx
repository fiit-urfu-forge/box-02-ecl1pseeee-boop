"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { UserPlus, Mail, Lock, Phone, User } from "lucide-react";
import { Card, CardTitle, CardSubtitle } from "@/components/ui/Card";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { Alert } from "@/components/ui/Alert";
import { api, ApiException } from "@/lib/api";
import { toast } from "@/components/ui/Toast";

const Schema = z.object({
  first_name: z.string().min(1, "Укажите имя").max(100),
  last_name: z.string().min(1, "Укажите фамилию").max(100),
  email: z.string().email("Некорректный email").max(255),
  phone: z
    .string()
    .trim()
    .optional()
    .or(z.literal(""))
    .refine((v) => !v || /^\+[1-9]\d{7,14}$/.test(v), {
      message: "Формат телефона: +79001234567",
    }),
  password: z
    .string()
    .min(8, "Минимум 8 символов")
    .regex(/[a-z]/, "Добавьте строчную букву")
    .regex(/[A-Z]/, "Добавьте заглавную букву")
    .regex(/\d/, "Добавьте цифру")
    .regex(/[^A-Za-z0-9]/, "Добавьте специальный символ"),
});
type Form = z.infer<typeof Schema>;

export default function RegisterPage() {
  const router = useRouter();
  const [serverError, setServerError] = useState<string | null>(null);
  const [done, setDone] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<Form>({ resolver: zodResolver(Schema), mode: "onBlur" });

  const onSubmit = async (values: Form) => {
    setServerError(null);
    try {
      await api.register({
        first_name: values.first_name,
        last_name: values.last_name,
        email: values.email,
        password: values.password,
        phone: values.phone || undefined,
      });
      setDone(true);
      toast.success(
        "Регистрация прошла успешно",
        "Мы отправили письмо для подтверждения e-mail",
      );
    } catch (e) {
      setServerError(
        e instanceof ApiException ? e.userMessage : "Не удалось создать аккаунт.",
      );
    }
  };

  if (done) {
    return (
      <Card className="w-full max-w-md p-8 text-center">
        <CardTitle className="text-xl">Подтвердите e-mail</CardTitle>
        <CardSubtitle className="mt-2">
          Мы отправили письмо со ссылкой для подтверждения. После подтверждения
          вы сможете войти в аккаунт.
        </CardSubtitle>
        <div className="mt-6">
          <Button onClick={() => router.push("/login")} fullWidth>
            Перейти ко входу
          </Button>
        </div>
      </Card>
    );
  }

  return (
    <Card className="w-full max-w-md p-8">
      <div className="mb-6">
        <CardTitle className="text-xl">Создание аккаунта</CardTitle>
        <CardSubtitle className="mt-1">
          Бесплатно за минуту. Подтверждение e-mail потребуется перед первым входом.
        </CardSubtitle>
      </div>

      {serverError && (
        <Alert variant="danger" className="mb-4">
          {serverError}
        </Alert>
      )}

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
        <div className="grid grid-cols-2 gap-3">
          <Input
            label="Имя"
            leftAdornment={<User className="h-4 w-4" />}
            error={errors.first_name?.message}
            autoComplete="given-name"
            {...register("first_name")}
          />
          <Input
            label="Фамилия"
            error={errors.last_name?.message}
            autoComplete="family-name"
            {...register("last_name")}
          />
        </div>
        <Input
          label="E-mail"
          type="email"
          leftAdornment={<Mail className="h-4 w-4" />}
          error={errors.email?.message}
          autoComplete="email"
          placeholder="you@example.com"
          {...register("email")}
        />
        <Input
          label="Телефон (необязательно)"
          leftAdornment={<Phone className="h-4 w-4" />}
          placeholder="+79001234567"
          error={errors.phone?.message}
          autoComplete="tel"
          {...register("phone")}
        />
        <Input
          label="Пароль"
          type="password"
          leftAdornment={<Lock className="h-4 w-4" />}
          error={errors.password?.message}
          autoComplete="new-password"
          hint="Минимум 8 символов, буквы разных регистров, цифра и спецсимвол."
          {...register("password")}
        />

        <Button
          type="submit"
          loading={isSubmitting}
          leftIcon={<UserPlus className="h-4 w-4" />}
          fullWidth
          size="lg"
        >
          Создать аккаунт
        </Button>
      </form>

      <p className="mt-6 text-center text-sm text-slate-600">
        Уже с нами?{" "}
        <Link href="/login" className="font-medium text-brand-700 hover:text-brand-800">
          Войти
        </Link>
      </p>
    </Card>
  );
}
