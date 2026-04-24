import Link from "next/link";
import { ShieldCheck } from "lucide-react";

export default function PublicLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 via-white to-brand-50">
      <header className="border-b border-slate-200/60 bg-white/60 backdrop-blur">
        <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-6">
          <Link href="/" className="flex items-center gap-2 font-semibold text-slate-900">
            <span className="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-brand-600 text-white">
              <ShieldCheck className="h-4 w-4" />
            </span>
            DigitalBank
          </Link>
          <div className="text-sm text-slate-500">
            Безопасно. Быстро. Прозрачно.
          </div>
        </div>
      </header>
      <main className="mx-auto flex w-full max-w-6xl justify-center px-6 py-14">
        {children}
      </main>
    </div>
  );
}
