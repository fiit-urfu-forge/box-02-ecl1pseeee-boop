<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Account;
use App\Models\Transaction;
use App\Support\ErrorCode;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Performs the atomic balance-transfer step for an internal transaction.
 *
 * Invariants (enforced here or at the DB):
 *   - One row per transition.
 *   - Both accounts are locked via SELECT … FOR UPDATE, **ordered by account
 *     id ascending** — this guarantees we cannot deadlock with a concurrent
 *     transfer that happens to use the same two accounts in reverse order.
 *   - Balances are NUMERIC(19,4) in the DB, manipulated via `brick/money`
 *     (BigDecimal) in PHP. `float` is banned (§14 of SPEC).
 *   - A single DB transaction wraps the entire move; any exception triggers
 *     ROLLBACK and marks the transaction as `failed` with an error_code.
 *   - audit_log receives one row per terminal transition.
 */
class TransferService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /**
     * Run the transfer. If `$transaction->status === 'pending'`, the status
     * is flipped to `processing` in a short transaction first so a retry of
     * the same job sees "already taken" and aborts cleanly.
     *
     * Returns the updated Transaction (always reloaded from DB).
     */
    public function execute(Transaction $transaction): Transaction
    {
        // Claim the job: pending → processing, but only if nobody else did.
        $claimed = DB::table('transactions')
            ->where('id', $transaction->id)
            ->where('status', Transaction::STATUS_PENDING)
            ->update(['status' => Transaction::STATUS_PROCESSING, 'updated_at' => now()]);

        if ($claimed === 0) {
            // Either already in flight or already finalised. Reload & return
            // so the caller can observe the latest state.
            return $transaction->refresh();
        }

        try {
            $this->moveFunds($transaction);
        } catch (ApiException $e) {
            $this->markFailed($transaction, $e->errorCode->value, $e->getMessage());
            return $transaction->refresh();
        } catch (\Throwable $e) {
            Log::channel('transfers')->error('transfer.unexpected', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
            $this->markFailed($transaction, ErrorCode::INTERNAL_ERROR->value, 'Внутренняя ошибка');
            return $transaction->refresh();
        }

        return $transaction->refresh();
    }

    private function moveFunds(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction): void {
            // Lock both accounts in a stable order to avoid deadlocks when
            // two opposite-direction transfers happen at the same time.
            $ids = array_values(array_unique(array_filter([
                $transaction->sender_account_id,
                $transaction->receiver_account_id,
            ])));
            sort($ids, SORT_STRING);

            /** @var array<string, Account> $locked */
            $locked = Account::query()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id')
                ->all();

            $sender = $locked[$transaction->sender_account_id] ?? null;
            $receiver = $locked[$transaction->receiver_account_id] ?? null;

            if ($sender === null || $receiver === null) {
                throw new ApiException(ErrorCode::ACCOUNT_NOT_FOUND);
            }
            if ($sender->status !== Account::STATUS_ACTIVE || $receiver->status !== Account::STATUS_ACTIVE) {
                throw new ApiException(ErrorCode::ACCOUNT_FROZEN);
            }
            if ($sender->currency !== $receiver->currency) {
                throw new ApiException(ErrorCode::CURRENCY_MISMATCH);
            }

            $amount = BigDecimal::of($transaction->amount);
            $senderBalance = BigDecimal::of($sender->balance);
            if ($senderBalance->isLessThan($amount)) {
                throw new ApiException(
                    ErrorCode::INSUFFICIENT_FUNDS,
                    details: [
                        'available' => $sender->balance,
                        'required' => (string) $amount,
                    ],
                );
            }

            // Daily limit is re-checked here in addition to the controller —
            // tests and concurrency may have slipped a limit-buster through.
            $dailyLimit = BigDecimal::of((string) config('digitalbank.transfer.daily_limit'));
            $spentToday = BigDecimal::of(
                (string) DB::table('transactions')
                    ->where('sender_account_id', $sender->id)
                    ->where('status', Transaction::STATUS_SUCCESS)
                    ->whereDate('created_at', now()->utc()->toDateString())
                    ->sum(DB::raw('amount + fee_amount'))
            );
            if ($spentToday->plus($amount)->isGreaterThan($dailyLimit)) {
                throw new ApiException(
                    ErrorCode::DAILY_LIMIT_EXCEEDED,
                    details: [
                        'daily_limit' => (string) $dailyLimit,
                        'already_spent' => (string) $spentToday,
                        'attempted' => (string) $amount,
                    ],
                );
            }

            $newSender = $senderBalance->minus($amount)->toScale(4, RoundingMode::UNNECESSARY);
            $newReceiver = BigDecimal::of($receiver->balance)->plus($amount)->toScale(4, RoundingMode::UNNECESSARY);

            // Use raw UPDATEs so the numeric column gets the exact string.
            DB::table('accounts')->where('id', $sender->id)->update([
                'balance' => (string) $newSender,
                'updated_at' => now(),
            ]);
            DB::table('accounts')->where('id', $receiver->id)->update([
                'balance' => (string) $newReceiver,
                'updated_at' => now(),
            ]);

            DB::table('transactions')->where('id', $transaction->id)->update([
                'status' => Transaction::STATUS_SUCCESS,
                'processed_at' => now(),
                'updated_at' => now(),
            ]);

            $this->audit->record(
                action: 'transfer.success',
                entityType: 'transaction',
                entityId: $transaction->id,
                userId: $sender->user_id,
                old: ['status' => Transaction::STATUS_PROCESSING],
                new: [
                    'status' => Transaction::STATUS_SUCCESS,
                    'sender_balance' => (string) $newSender,
                    'receiver_balance' => (string) $newReceiver,
                    'amount' => (string) $amount,
                ],
            );

            Log::channel('transfers')->info('transfer.success', [
                'transaction_id' => $transaction->id,
                'amount' => (string) $amount,
                'currency' => $transaction->currency,
            ]);
        });
    }

    private function markFailed(Transaction $transaction, string $code, string $message): void
    {
        DB::table('transactions')->where('id', $transaction->id)->update([
            'status' => Transaction::STATUS_FAILED,
            'error_code' => $code,
            'processed_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit->record(
            action: 'transfer.failed',
            entityType: 'transaction',
            entityId: $transaction->id,
            userId: null,
            new: ['status' => Transaction::STATUS_FAILED, 'error_code' => $code, 'message' => $message],
        );

        Log::channel('transfers')->warning('transfer.failed', [
            'transaction_id' => $transaction->id,
            'error_code' => $code,
            'message' => $message,
        ]);
    }
}
