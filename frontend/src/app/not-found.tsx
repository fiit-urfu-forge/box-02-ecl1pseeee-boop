import Link from "next/link";
import { Button } from "@/components/ui/Button";

export default function NotFound() {
  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4">
      <div className="mx-auto max-w-md text-center">
        <div className="text-6xl font-semibold text-brand-700">404</div>
        <h1 className="mt-4 text-2xl font-semibold text-slate-900">Страница не найдена</h1>
        <p className="mt-2 text-slate-600">
          Возможно, вы перешли по устаревшей ссылке, или страница была перемещена.
        </p>
        <div className="mt-6 flex justify-center gap-3">
          <Link href="/dashboard">
            <Button>На главную</Button>
          </Link>
        </div>
      </div>
    </div>
  );
}
