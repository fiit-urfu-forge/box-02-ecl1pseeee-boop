"use client";

import Link from "next/link";
import { Suspense, useEffect, useState } from "react";
import { useSearchParams } from "next/navigation";
import axios from "axios";
import { Card, CardSubtitle, CardTitle } from "@/components/ui/Card";
import { Button } from "@/components/ui/Button";
import { Alert } from "@/components/ui/Alert";

/**
 * Convenience page for the user who just clicked the link in their inbox.
 * The real verification happens against a signed GET endpoint on the API
 * (see routes/api.php → verification.verify). We simply replay the
 * URL's query string against the API and report the result.
 */
export default function VerifyEmailPage() {
  return (
    <Suspense fallback={<div className="h-64 w-full animate-pulse rounded-2xl bg-slate-100" />}>
      <VerifyEmailBody />
    </Suspense>
  );
}

function VerifyEmailBody() {
  const qs = useSearchParams();
  const id = qs.get("id");
  const hash = qs.get("hash");
  const [status, setStatus] = useState<"idle" | "loading" | "ok" | "error">("idle");
  const [message, setMessage] = useState<string>("");

  useEffect(() => {
    if (!id || !hash) {
      setStatus("error");
      setMessage("Ссылка не содержит обязательных параметров.");
      return;
    }
    setStatus("loading");
    const api = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost/api";
    const url = `${api}/auth/email/verify/${id}/${hash}?${qs.toString()}`;
    axios
      .get(url, { withCredentials: true })
      .then(() => {
        setStatus("ok");
        setMessage("E-mail подтверждён. Теперь вы можете войти в систему.");
      })
      .catch((err) => {
        setStatus("error");
        const body = err?.response?.data;
        setMessage(body?.error?.message || "Ссылка недействительна или просрочена.");
      });
  }, [id, hash, qs]);

  return (
    <Card className="w-full max-w-md p-8">
      <CardTitle className="text-xl">Подтверждение e-mail</CardTitle>
      <CardSubtitle className="mt-1">Проверяем ссылку…</CardSubtitle>

      <div className="mt-6">
        {status === "loading" && (
          <Alert variant="info">Идёт проверка подписи ссылки.</Alert>
        )}
        {status === "ok" && <Alert variant="success">{message}</Alert>}
        {status === "error" && <Alert variant="danger">{message}</Alert>}
      </div>

      <div className="mt-6">
        <Link href="/login">
          <Button fullWidth>Перейти ко входу</Button>
        </Link>
      </div>
    </Card>
  );
}
