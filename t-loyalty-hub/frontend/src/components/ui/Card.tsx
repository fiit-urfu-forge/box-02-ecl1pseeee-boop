import type { HTMLAttributes, PropsWithChildren } from 'react';

interface CardProps extends HTMLAttributes<HTMLDivElement> {
  className?: string;
}

export function Card({ children, className = '', ...rest }: PropsWithChildren<CardProps>) {
  return (
    <div
      className={`bg-white dark:bg-gray-800 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 ${className}`}
      {...rest}
    >
      {children}
    </div>
  );
}
