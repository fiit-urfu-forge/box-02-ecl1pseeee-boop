"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { LogOut, Menu, UserCircle2 } from "lucide-react";
import { useAuthStore } from "@/stores/auth";
import { api, ApiException } from "@/lib/api";
import { toast } from "@/components/ui/Toast";
import { Button } from "@/components/ui/Button";
import { initialsOf } from "@/lib/format";
import { cn } from "@/lib/cn";

const NAV = [
  { href: "/dashboard", label: "Главная" },
  { href: "/accounts", label: "Счета" },
  { href: "/transfers", label: "Переводы" },
  { href: "/sbp", label: "СБП" },
  { href: "/profile", label: "Профиль" },
];

export function Header() {
  const router = useRouter();
  const user = useAuthStore((s) => s.user);
  const reset = useAuthStore((s) => s.reset);
  const [mobileOpen, setMobileOpen] = useState(false);

  const logout = async () => {
    try {
      await api.logout();
    } catch (e) {
      // Non-fatal; we still clear client state.
      if (e instanceof ApiException) toast.warning("Сессия уже завершена", e.userMessage);
    }
    reset();
    router.replace("/login");
  };

  return (
    <header className="sticky top-0 z-20 border-b border-slate-200 bg-white/75 backdrop-blur">
      <div className="flex h-16 items-center justify-between px-4 md:px-8">
        <button
          type="button"
          className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 md:hidden"
          onClick={() => setMobileOpen((o) => !o)}
          aria-label="Меню"
        >
          <Menu className="h-5 w-5" />
        </button>
        <div className="hidden md:block" />

        <div className="flex items-center gap-3">
          {user ? (
            <div className="hidden items-center gap-3 md:flex">
              <div className="text-right">
                <div className="text-sm font-medium text-slate-900">
                  {user.first_name} {user.last_name}
                </div>
                <div className="text-xs text-slate-500">{user.email}</div>
              </div>
              <Avatar url={user.avatar_url} initials={initialsOf(user.first_name, user.last_name)} />
            </div>
          ) : (
            <div className="h-9 w-24 animate-pulse rounded-md bg-slate-100" />
          )}
          <Button variant="ghost" size="sm" onClick={logout} leftIcon={<LogOut className="h-4 w-4" />}>
            Выйти
          </Button>
        </div>
      </div>

      {mobileOpen && (
        <nav className="border-t border-slate-200 bg-white md:hidden">
          <ul className="p-3">
            {NAV.map((n) => (
              <li key={n.href}>
                <Link
                  href={n.href}
                  onClick={() => setMobileOpen(false)}
                  className={cn(
                    "block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-100",
                  )}
                >
                  {n.label}
                </Link>
              </li>
            ))}
          </ul>
        </nav>
      )}
    </header>
  );
}

function Avatar({ url, initials }: { url: string | null; initials: string }) {
  if (url) {
    // eslint-disable-next-line @next/next/no-img-element
    return (
      <img
        src={url}
        alt="avatar"
        className="h-9 w-9 rounded-full border border-slate-200 object-cover"
      />
    );
  }
  return (
    <div className="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700">
      {initials || <UserCircle2 className="h-5 w-5" />}
    </div>
  );
}
