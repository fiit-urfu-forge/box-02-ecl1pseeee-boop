// Mirror of Backend §6 — keep in sync with App\Support\ErrorCode and
// controller payloads. Every API response is wrapped in this envelope.

export type ApiSuccess<T> = {
  success: true;
  data: T;
  meta: { timestamp: string; request_id: string };
  pagination?: Pagination;
};

export type ApiError = {
  success: false;
  error: {
    code: ErrorCode;
    message: string;
    details?: Record<string, unknown>;
  };
  meta: { timestamp: string; request_id: string };
};

export type ApiResponse<T> = ApiSuccess<T> | ApiError;

export type Pagination = {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
};

export type ErrorCode =
  | "INSUFFICIENT_FUNDS"
  | "ACCOUNT_NOT_FOUND"
  | "ACCOUNT_FROZEN"
  | "DAILY_LIMIT_EXCEEDED"
  | "AMOUNT_TOO_LOW"
  | "AMOUNT_TOO_HIGH"
  | "CURRENCY_MISMATCH"
  | "IDEMPOTENCY_CONFLICT"
  | "USER_NOT_FOUND"
  | "SELF_TRANSFER_SAME_ACCOUNT"
  | "UNAUTHENTICATED"
  | "FORBIDDEN"
  | "VALIDATION_ERROR"
  | "INTERNAL_ERROR"
  | "TOO_MANY_REQUESTS"
  | "NOT_FOUND"
  | "METHOD_NOT_ALLOWED";

// --------------------- Domain entities ---------------------

export type Currency = "RUB" | "USD";
export type AccountType = "checking" | "savings";
export type AccountStatus = "active" | "frozen" | "closed";

export type TransactionStatus =
  | "pending"
  | "processing"
  | "success"
  | "failed"
  | "cancelled";

export type TransactionType = "internal" | "sbp_out" | "sbp_in";

export type UserProfile = {
  id: string;
  first_name: string;
  last_name: string;
  email: string;
  email_verified_at: string | null;
  phone: string | null;
  status: "active" | "suspended" | "blocked";
  avatar_url: string | null;
};

export type Account = {
  id: string;
  account_number: string;
  balance: string;
  currency: Currency;
  type: AccountType;
  status: AccountStatus;
  daily_limit: string | null;
  created_at: string | null;
};

export type Transaction = {
  id: string;
  sender_account_id: string | null;
  receiver_account_id: string | null;
  amount: string;
  fee_amount: string;
  currency: Currency;
  status: TransactionStatus;
  type: TransactionType;
  description: string | null;
  error_code: string | null;
  processed_at: string | null;
  created_at: string | null;
};
