import type { PropsWithChildren } from 'react';

interface Props {
  tone?: 'default' | 'success' | 'warning' | 'info' | 'demo';
  className?: string;
}

const TONES: Record<NonNullable<Props['tone']>, string> = {
  default: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
  success: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
  warning: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
  info: 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
  demo: 'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-900/40 dark:text-fuchsia-300',
};

export function Badge({ tone = 'default', className = '', children }: PropsWithChildren<Props>) {
  return (
    <span className={`inline-flex items-center text-xs font-medium px-2.5 py-1 rounded-full ${TONES[tone]} ${className}`}>
      {children}
    </span>
  );
}
