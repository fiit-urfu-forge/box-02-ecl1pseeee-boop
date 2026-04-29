"use client";

import { useRef, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { Mail, Phone, User, Camera, LogOut, ShieldAlert } from "lucide-react";
import { api, ApiException } from "@/lib/api";
import { Card, CardHeader, CardSubtitle, CardTitle } from "@/components/ui/Card";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { Alert } from "@/components/ui/Alert";
import { Badge } from "@/components/ui/Badge";
import { toast } from "@/components/ui/Toast";
import { useAuthStore } from "@/stores/auth";
import { initialsOf } from "@/lib/format";

const Schema = z.object({
  first_name: z.string().min(1, "Укажите имя").max(100),
  last_name: z.string().min(1, "Укажите фамилию").max(100),
  phone: z
    .string()
    .trim()
    .optional()
    .or(z.literal(""))
    .refine((v) => !v || /^\+[1-9]\d{7,14}$/.test(v), {
      message: "Формат: +79001234567",
    }),
});
type Form = z.infer<typeof Schema>;

export default function ProfilePage() {
  const router = useRouter();
  const user = useAuthStore((s) => s.user);
  const setUser = useAuthStore((s) => s.setUser);
  const reset = useAuthStore((s) => s.reset);
  const qc = useQueryClient();
  const fileRef = useRef<HTMLInputElement>(null);
  const [serverError, setServerError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting, isDirty },
  } = useForm<Form>({
    resolver: zodResolver(Schema),
    values: {
      first_name: user?.first_name ?? "",
      last_name: user?.last_name ?? "",
      phone: user?.phone ?? "",
    },
  });

  const onSubmit = async (values: Form) => {
    setServerError(null);
    try {
      const updated = await api.updateProfile({
        first_name: values.first_name,
        last_name: values.last_name,
        phone: values.phone || null,
      });
      setUser(updated);
      toast.success("Профиль обновлён");
    } catch (e) {
      setServerError(e instanceof ApiException ? e.userMessage : "Не удалось сохранить.");
    }
  };

  const onFile = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    try {
      const updated = await api.uploadAvatar(file);
      setUser(updated);
      toast.success("Аватар обновлён");
    } catch (err) {
      toast.error("Не удалось загрузить аватар", err instanceof ApiException ? err.userMessage : undefined);
    } finally {
      if (fileRef.current) fileRef.current.value = "";
    }
  };

  const logoutAll = async () => {
    try {
      await api.logoutAll();
    } catch (e) {
      if (e instanceof ApiException) toast.warning(e.userMessage);
    }
    reset();
    router.replace("/login");
  };

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <div>
        <h1 className="text-2xl font-semibold text-slate-900">Профиль</h1>
        <p className="mt-1 text-sm text-slate-500">
          Управляйте своими данными, аватаром и сессиями.
        </p>
      </div>

      <Card>
        <CardHeader>
          <div>
            <CardTitle>Персональные данные</CardTitle>
            <CardSubtitle>Имя и фамилия показываются на страницах переводов.</CardSubtitle>
          </div>
          {user?.email_verified_at ? (
            <Badge variant="success">E-mail подтверждён</Badge>
          ) : (
            <Badge variant="warning">E-mail не подтверждён</Badge>
          )}
        </CardHeader>

        {serverError && <Alert variant="danger" className="mb-4">{serverError}</Alert>}

        <div className="mb-6 flex items-center gap-4">
          <Avatar url={user?.avatar_url ?? null} initials={initialsOf(user?.first_name ?? "", user?.last_name ?? "")} />
          <div className="flex flex-col">
            <Button
              type="button"
              variant="secondary"
              size="sm"
              leftIcon={<Camera className="h-4 w-4" />}
              onClick={() => fileRef.current?.click()}
            >
              Сменить аватар
            </Button>
            <input
              ref={fileRef}
              type="file"
              accept="image/jpeg,image/png,image/webp"
              className="hidden"
              onChange={onFile}
            />
            <p className="mt-1 text-xs text-slate-500">JPEG / PNG / WEBP · до 5 МБ</p>
          </div>
        </div>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
          <div className="grid grid-cols-2 gap-3">
            <Input
              label="Имя"
              leftAdornment={<User className="h-4 w-4" />}
              error={errors.first_name?.message}
              {...register("first_name")}
            />
            <Input
              label="Фамилия"
              error={errors.last_name?.message}
              {...register("last_name")}
            />
          </div>
          <Input
            label="E-mail"
            leftAdornment={<Mail className="h-4 w-4" />}
            value={user?.email ?? ""}
            readOnly
            hint="Смена e-mail временно недоступна."
          />
          <Input
            label="Телефон"
            leftAdornment={<Phone className="h-4 w-4" />}
            placeholder="+79001234567"
            error={errors.phone?.message}
            {...register("phone")}
          />

          <div className="flex gap-3 pt-1">
            <Button type="submit" loading={isSubmitting} disabled={!isDirty}>
              Сохранить
            </Button>
          </div>
        </form>
      </Card>

      <Card>
        <CardHeader>
          <div>
            <CardTitle>Безопасность</CardTitle>
            <CardSubtitle>Сессии и выход со всех устройств.</CardSubtitle>
          </div>
          <ShieldAlert className="h-6 w-6 text-amber-600" />
        </CardHeader>
        <p className="text-sm text-slate-600">
          Если заметили подозрительную активность или забыли выйти на другом устройстве —
          завершите все активные сессии.
        </p>
        <div className="mt-4">
          <Button variant="danger" leftIcon={<LogOut className="h-4 w-4" />} onClick={logoutAll}>
            Выйти со всех устройств
          </Button>
        </div>
      </Card>
    </div>
  );
}

function Avatar({ url, initials }: { url: string | null; initials: string }) {
  if (url) {
    // eslint-disable-next-line @next/next/no-img-element
    return <img src={url} alt="avatar" className="h-16 w-16 rounded-full border border-slate-200 object-cover" />;
  }
  return (
    <div className="flex h-16 w-16 items-center justify-center rounded-full bg-brand-100 text-lg font-semibold text-brand-700">
      {initials || "?"}
    </div>
  );
}
