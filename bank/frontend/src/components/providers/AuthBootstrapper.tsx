"use client";

import { useEffect } from "react";
import { useRouter, usePathname } from "next/navigation";
import { api } from "@/lib/api";
import { useAuthStore } from "@/stores/auth";

const PUBLIC_PATHS = ["/login", "/register", "/verify-email"];

/**
 * Runs once on mount: asks the backend who the current user is. If the
 * session is valid, populates the store; otherwise redirects to /login
 * from any authenticated page.
 */
export function AuthBootstrapper({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const setUser = useAuthStore((s) => s.setUser);
  const reset = useAuthStore((s) => s.reset);
  const status = useAuthStore((s) => s.status);

  useEffect(() => {
    let cancelled = false;
    api
      .profile()
      .then((u) => {
        if (!cancelled) setUser(u);
      })
      .catch(() => {
        if (!cancelled) reset();
      });
    return () => {
      cancelled = true;
    };
  }, [setUser, reset]);

  useEffect(() => {
    if (status === "guest" && !PUBLIC_PATHS.some((p) => pathname?.startsWith(p))) {
      router.replace("/login");
    }
  }, [status, pathname, router]);

  return <>{children}</>;
}
