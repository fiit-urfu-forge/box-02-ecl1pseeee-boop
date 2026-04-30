export type FinancialSegment = 'LOW' | 'MEDIUM' | 'HIGH';

export interface User {
  id: number;
  email: string;
  phone_number: string | null;
  full_name: string;
  financial_segment: FinancialSegment;
}

export interface ProgramSummary {
  loyalty_program_id: number;
  loyalty_program_name: string;
  cashback_currency: string;
  account_id: number;
  current_balance: number;
}

export interface CurrencyTotal {
  currency: string;
  total: number;
  transactions: number;
}

export interface LoyaltySummary {
  by_currency: CurrencyTotal[];
  totals: {
    rub: number;
    miles: number;
    bravo: number;
    total_transactions: number;
    total_balance: number;
  };
  programs: ProgramSummary[];
}

export interface LoyaltyHistoryItem {
  transaction_id: number;
  account_id: number;
  cashback_amount: number;
  payout_date: string;
  program?: {
    loyalty_program_id: number;
    loyalty_program_name: string;
    cashback_currency: string;
  } | null;
}

export interface Paginated<T> {
  data: T[];
  meta?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface Offer {
  id: number;
  partner_id: number;
  partner_name: string;
  short_description: string | null;
  logo_url: string | null;
  brand_color_hex: string | null;
  cashback_percent: number;
  financial_segment: string;
}

export interface ShadowPortfolio {
  real_cashback: number;
  shadow_cashback: number;
  gap: number;
  insight: string;
  health_score: number;
  is_stub?: boolean;
  is_fallback?: boolean;
}

export interface Nudging {
  message: string | null;
  category: string | null;
  boost_multiplier: number;
  trigger_time: string | null;
  is_stub?: boolean;
  is_fallback?: boolean;
}

export interface CrossSellItem {
  product_name: string;
  reason: string;
  potential_gain: number;
  priority: number;
}

export interface CrossSellResponse {
  items: CrossSellItem[];
  is_stub?: boolean;
}

export type ZeroClickIntent = 'COMMERCIAL' | 'INFORMATIONAL' | 'COMMERCIAL_NO_OFFER';

export interface ZeroClick {
  activated_offer: string | null;
  partner_name: string | null;
  probability: number;
  intent?: ZeroClickIntent | null;
  cashback_percent?: number | null;
  match_accuracy?: number | null;
  query?: string | null;
  is_stub?: boolean;
  is_fallback?: boolean;
}

export interface Gamification {
  health_score: number;
  loyalty_tier: string;
  streak_days: number;
  last_visit_date: string | null;
}

export interface ApiEnvelope<T> {
  data: T;
}
