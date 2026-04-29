"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  LayoutDashboard,
  Wallet,
  ArrowLeftRight,
  Smartphone,
  UserCircle2,
  ShieldCheck,
} from "lucide-react";
import { cn } from "@/lib/cn";

const NAV = [
  { href: "/dashboard", label: "Главная", icon: LayoutDashboard },
  { href: "/accounts", label: "Счета", icon: Wallet },
  { href: "/transfers", label: "Переводы", icon: ArrowLeftRight },
  { href: "/sbp", label: "СБП", icon: Smartphone },
  { href: "/profile", label: "Профиль", icon: UserCircle2 },
];

export function Sidebar() {
  const pathname = usePathname();

  return (
    <aside className="sticky top-0 hidden h-screen w-64 shrink-0 border-r border-slate-200 bg-white md:flex md:flex-col">
      <div className="flex h-16 items-center gap-2 border-b border-slate-200 px-5 font-semibold text-slate-900">
        <span className="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-brand-600 text-white">
          <ShieldCheck className="h-4 w-4" />
        </span>
        DigitalBank
      </div>
      <nav className="flex-1 space-y-1 p-3">
        {NAV.map((n) => {
          const Icon = n.icon;
          const active =
            pathname === n.href || (n.href !== "/" && pathname?.startsWith(n.href));
          return (
            <Link
              key={n.href}
              href={n.href}
              className={cn(
                "flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition",
                active
                  ? "bg-brand-50 text-brand-800"
                  : "text-slate-600 hover:bg-slate-100 hover:text-slate-900",
              )}
            >
              <Icon className={cn("h-4 w-4", active ? "text-brand-600" : "text-slate-500")} />
              {n.label}
            </Link>
          );
        })}
      </nav>
      <div className="border-t border-slate-200 p-4 text-xs text-slate-500">
        v1.0 MVP · Защищённое соединение
      </div>
    </aside>
  );
}
