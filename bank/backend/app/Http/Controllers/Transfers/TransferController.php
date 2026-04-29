<?php

declare(strict_types=1);

namespace App\Http\Controllers\Transfers;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTransferRequest;
use App\Jobs\ProcessTransferJob;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Brick\Math\BigDecimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->mustHaveUser($request);

        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $myAccountIds = Account::query()->where('user_id', $user->id)->pluck('id');

        $paginator = Transaction::query()
            ->where(function ($q) use ($myAccountIds) {
                $q->whereIn('sender_account_id', $myAccountIds)
                  ->orWhereIn('receiver_account_id', $myAccountIds);
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return ApiResponse::paginated(
            $paginator,
            fn (Transaction $t) => $this->payload($t),
            $request,
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = $this->mustHaveUser($request);

        /** @var Transaction|null $tx */
        $tx = Transaction::query()->find($id);
        if ($tx === null) {
            throw new ApiException(ErrorCode::NOT_FOUND);
        }

        // Authorisation: the user must own at least one of the two legs.
        $ownedIds = Account::query()->where('user_id', $user->id)->pluck('id')->all();
        if (! in_array($tx->sender_account_id, $ownedIds, true)
            && ! in_array($tx->receiver_account_id, $ownedIds, true)) {
            throw new ApiException(ErrorCode::NOT_FOUND);
        }

        return ApiResponse::success($this->payload($tx), request: $request);
    }

    public function store(CreateTransferRequest $request): JsonResponse
    {
        $user = $this->mustHaveUser($request);
        $data = $request->validated();

        /** @var Account|null $sender */
        $sender = Account::query()->find($data['sender_account_id']);
        if ($sender === null || $sender->user_id !== $user->id) {
            throw new ApiException(ErrorCode::ACCOUNT_NOT_FOUND, 'Счёт отправителя не найден');
        }
        if ($sender->status === Account::STATUS_FROZEN) {
            throw new ApiException(ErrorCode::ACCOUNT_FROZEN, 'Счёт отправителя заморожен');
        }
        if ($sender->status !== Account::STATUS_ACTIVE) {
            throw new ApiException(ErrorCode::ACCOUNT_FROZEN, 'Счёт отправителя недоступен');
        }

        /** @var Account|null $receiver */
        $receiver = null;
        if (! empty($data['receiver_account_id'])) {
            $receiver = Account::query()->find($data['receiver_account_id']);
        } elseif (! empty($data['receiver_account_number'])) {
            $receiver = Account::query()->where('account_number', $data['receiver_account_number'])->first();
        }
        if ($receiver === null) {
            throw new ApiException(ErrorCode::ACCOUNT_NOT_FOUND, 'Счёт получателя не найден');
        }
        if ($sender->id === $receiver->id) {
            throw new ApiException(ErrorCode::SELF_TRANSFER_SAME_ACCOUNT);
        }
        if ($receiver->status === Account::STATUS_FROZEN) {
            throw new ApiException(ErrorCode::ACCOUNT_FROZEN, 'Счёт получателя заморожен');
        }
        if ($receiver->status !== Account::STATUS_ACTIVE) {
            throw new ApiException(ErrorCode::ACCOUNT_FROZEN, 'Счёт получателя недоступен');
        }
        if ($sender->currency !== $receiver->currency) {
            throw new ApiException(ErrorCode::CURRENCY_MISMATCH);
        }

        // Amount envelope checks (§4.1).
        $amount = BigDecimal::of((string) $data['amount']);
        $min = BigDecimal::of((string) config('digitalbank.transfer.min_amount'));
        $max = BigDecimal::of((string) config('digitalbank.transfer.max_amount'));
        if ($amount->isLessThan($min)) {
            throw new ApiException(
                ErrorCode::AMOUNT_TOO_LOW,
                details: ['min' => (string) $min, 'provided' => (string) $amount],
            );
        }
        if ($amount->isGreaterThan($max)) {
            throw new ApiException(
                ErrorCode::AMOUNT_TOO_HIGH,
                details: ['max' => (string) $max, 'provided' => (string) $amount],
            );
        }

        // Daily limit pre-check — the worker re-checks under lock.
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

        // Create the pending transaction row. The idempotency key comes from
        // the header (validated by IdempotencyMiddleware upstream) and is
        // `UNIQUE` on the table — so concurrent retries of the same key can
        // never create two rows.
        $key = (string) $request->header('X-Idempotency-Key');
        $transaction = Transaction::create([
            'sender_account_id' => $sender->id,
            'receiver_account_id' => $receiver->id,
            'amount' => (string) $amount,
            'fee_amount' => '0.0000',
            'currency' => $sender->currency,
            'status' => Transaction::STATUS_PENDING,
            'type' => Transaction::TYPE_INTERNAL,
            'idempotency_key' => $key,
            'description' => $data['description'] ?? null,
        ]);

        $this->audit->record(
            action: 'transfer.created',
            entityType: 'transaction',
            entityId: $transaction->id,
            userId: $user->id,
            new: [
                'sender_account_id' => $sender->id,
                'receiver_account_id' => $receiver->id,
                'amount' => (string) $amount,
                'currency' => $sender->currency,
            ],
            request: $request,
        );

        ProcessTransferJob::dispatch($transaction->id);

        // If the queue is synchronous (local/tests), the job has already
        // flipped the status. Refresh so we return the final state.
        $transaction = $transaction->refresh();

        return ApiResponse::success(
            $this->payload($transaction),
            status: 201,
            request: $request,
        );
    }

    private function mustHaveUser(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new ApiException(ErrorCode::UNAUTHENTICATED);
        }
        return $user;
    }

    /** @return array<string, mixed> */
    private function payload(Transaction $t): array
    {
        return [
            'id' => $t->id,
            'sender_account_id' => $t->sender_account_id,
            'receiver_account_id' => $t->receiver_account_id,
            'amount' => $t->amount,
            'fee_amount' => $t->fee_amount,
            'currency' => $t->currency,
            'status' => $t->status,
            'type' => $t->type,
            'description' => $t->description,
            'error_code' => $t->error_code,
            'processed_at' => $t->processed_at?->toIso8601String(),
            'created_at' => $t->created_at?->toIso8601String(),
        ];
    }
}
