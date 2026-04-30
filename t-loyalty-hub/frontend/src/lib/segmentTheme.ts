import type { FinancialSegment } from '@/types';

export interface SegmentTheme {
  label: string;
  tierName: string;
  tagline: string;
  pageBg: string;
  heroBg: string;
  heroText: string;
  heroAccentText: string;
  badgeBg: string;
  badgeText: string;
  ringFrom: string;
  ringTo: string;
  iconBg: string;
  iconText: string;
  cardAccentBorder: string;
  glow: string;
}

export const SEGMENT_THEMES: Record<FinancialSegment, SegmentTheme> = {
  HIGH: {
    label: 'Premium · Black',
    tierName: 'PREMIUM',
    tagline: 'Привилегии и максимум кэшбэка',
    pageBg:
      'bg-gradient-to-b from-zinc-50 via-white to-amber-50/30 dark:from-black dark:via-zinc-950 dark:to-amber-950/20',
    heroBg:
      'bg-gradient-to-br from-zinc-900 via-zinc-800 to-amber-900/60 dark:from-black dark:via-zinc-900 dark:to-amber-950',
    heroText: 'text-amber-50',
    heroAccentText: 'text-amber-300',
    badgeBg: 'bg-gradient-to-r from-zinc-800 to-amber-600 dark:from-zinc-900 dark:to-amber-700',
    badgeText: 'text-amber-100',
    ringFrom: '#fde68a',
    ringTo: '#f59e0b',
    iconBg: 'bg-amber-100 dark:bg-amber-900/40',
    iconText: 'text-amber-700 dark:text-amber-300',
    cardAccentBorder: 'border-amber-200/60 dark:border-amber-900/40',
    glow: 'shadow-amber-200/40 dark:shadow-amber-900/20',
  },
  MEDIUM: {
    label: 'Standard',
    tierName: 'STANDARD',
    tagline: 'Растёшь — растёт и выгода',
    pageBg:
      'bg-gradient-to-b from-yellow-50/50 via-white to-white dark:from-yellow-950/10 dark:via-gray-950 dark:to-gray-950',
    heroBg:
      'bg-gradient-to-br from-yellow-300 via-yellow-200 to-amber-100 dark:from-yellow-600/40 dark:via-yellow-700/30 dark:to-amber-800/30',
    heroText: 'text-zinc-900 dark:text-yellow-50',
    heroAccentText: 'text-amber-700 dark:text-amber-200',
    badgeBg: 'bg-yellow-400 dark:bg-yellow-500',
    badgeText: 'text-zinc-900',
    ringFrom: '#fde047',
    ringTo: '#eab308',
    iconBg: 'bg-yellow-100 dark:bg-yellow-900/40',
    iconText: 'text-yellow-700 dark:text-yellow-300',
    cardAccentBorder: 'border-yellow-200 dark:border-yellow-900/40',
    glow: 'shadow-yellow-200/40 dark:shadow-yellow-900/20',
  },
  LOW: {
    label: 'Старт',
    tierName: 'СТАРТ',
    tagline: 'Открой больше возможностей',
    pageBg:
      'bg-gradient-to-b from-sky-50/50 via-white to-white dark:from-sky-950/10 dark:via-gray-950 dark:to-gray-950',
    heroBg:
      'bg-gradient-to-br from-sky-500 via-sky-400 to-cyan-300 dark:from-sky-700 dark:via-sky-800 dark:to-cyan-900',
    heroText: 'text-white',
    heroAccentText: 'text-cyan-100',
    badgeBg: 'bg-sky-500 dark:bg-sky-600',
    badgeText: 'text-white',
    ringFrom: '#7dd3fc',
    ringTo: '#0ea5e9',
    iconBg: 'bg-sky-100 dark:bg-sky-900/40',
    iconText: 'text-sky-700 dark:text-sky-300',
    cardAccentBorder: 'border-sky-200 dark:border-sky-900/40',
    glow: 'shadow-sky-200/40 dark:shadow-sky-900/20',
  },
};

export function getSegmentTheme(segment: FinancialSegment | string | null | undefined): SegmentTheme {
  if (segment && segment in SEGMENT_THEMES) {
    return SEGMENT_THEMES[segment as FinancialSegment];
  }
  return SEGMENT_THEMES.MEDIUM;
}
