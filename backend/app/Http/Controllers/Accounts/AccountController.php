<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounts;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateAccountRequest;
use App\Models\Account;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\AccountNumberGenerator;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->mustHaveUser($request);

        $accounts = Account::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn (Account $a) => $this->payload($a))
            ->all();

        return ApiResponse::success($accounts, request: $request);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = $this->mustHaveUser($request);

        /** @var Account|null $account */
        $account = Account::query()->find($id);
        if ($account === null) {
            throw new ApiException(ErrorCode::ACCOUNT_NOT_FOUND);
        }
        if ($account->user_id !== $user->id) {
            // Do not leak existence of accounts belonging to someone else.
            throw new ApiException(ErrorCode::ACCOUNT_NOT_FOUND);
        }

        return ApiResponse::success($this->payload($account), request: $request);
    }

    public function store(CreateAccountRequest $request): JsonResponse
    {
        $user = $this->mustHaveUser($request);
        $data = $request->validated();

        $maxPerUser = (int) config('digitalbank.accounts.max_per_user', 5);

        // Atomic guard against racing double-creates — counted WITHIN the
        // transaction so the `exists` query and the INSERT happen under the
        // same visibility window. INSERT will also fail at the DB level if
        // two requests squeezed past (UNIQUE on account_number).
        $account = DB::transaction(function () use ($user, $data, $maxPerUser): Account {
            // Lock the users row so concurrent POSTs see a consistent count.
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first();

            $current = Account::query()->where('user_id', $user->id)->count();
            if ($current >= $maxPerUser) {
                throw new ApiException(
                    ErrorCode::VALIDATION_ERROR,
                    "Превышен лимит счетов на пользователя ($maxPerUser)",
                    ['limit' => $maxPerUser, 'current' => $current],
                );
            }

            return Account::create([
                'user_id' => $user->id,
                'account_number' => AccountNumberGenerator::forCurrency($data['currency']),
                'currency' => $data['currency'],
                'type' => $data['type'],
                'status' => Account::STATUS_ACTIVE,
                'balance' => '0.0000',
            ]);
        });

        $this->audit->record(
            action: 'account.created',
            entityType: 'account',
            entityId: $account->id,
            userId: $user->id,
            new: $account->only(['account_number', 'currency', 'type', 'status']),
            request: $request,
        );

        return ApiResponse::success($this->payload($account), status: 201, request: $request);
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
    private function payload(Account $a): array
    {
        return [
            'id' => $a->id,
            'account_number' => $a->account_number,
            'balance' => $a->balance,
            'currency' => $a->currency,
            'type' => $a->type,
            'status' => $a->status,
            'daily_limit' => $a->daily_limit,
            'created_at' => $a->created_at?->toIso8601String(),
        ];
    }
}
