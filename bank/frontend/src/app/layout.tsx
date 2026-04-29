import type { Metadata, Viewport } from "next";
import { Inter } from "next/font/google";
import "./globals.css";
import { QueryProvider } from "@/components/providers/QueryProvider";
import { AuthBootstrapper } from "@/components/providers/AuthBootstrapper";
import { ToastHost } from "@/components/ui/Toast";

const inter = Inter({
  subsets: ["latin", "cyrillic"],
  variable: "--font-inter",
  display: "swap",
});

export const metadata: Metadata = {
  title: {
    default: "DigitalBank",
    template: "%s · DigitalBank",
  },
  description:
    "Ваш цифровой банк: счета, переводы и СБП в одном личном кабинете.",
  icons: { icon: "/favicon.svg" },
};

export const viewport: Viewport = {
  themeColor: "#4F46E5",
  width: "device-width",
  initialScale: 1,
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="ru" className={inter.variable}>
      <body>
        <QueryProvider>
          <AuthBootstrapper>
            {children}
            <ToastHost />
          </AuthBootstrapper>
        </QueryProvider>
      </body>
    </html>
  );
}
