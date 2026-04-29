<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sbp;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LinkPhoneRequest;
use App\Http\Requests\SbpTransferRequest;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Sbp\SbpGatewayInterface;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Brick\Math\BigDecimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * §5.4 — MVP stub. Phone linking is real; outbound SBP transfers create a
 * `sbp_out` transaction in `pending` and call `MockSbpGateway` instead of
 * an external provider. The transaction stays `pending` until a real
 * adapter replaces the mock (post-MVP).
 */
class SbpController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SbpGatewayInterface $gateway,
    ) {
    }

    public function linkPhone(LinkPhoneRequest $request): JsonResponse
    {
        $user = $this->mustHaveUser($request);
        $data = $request->validated();

        /** @var Account|null $account */
        $account = Account::query()->find($data['account_id']);
        if ($account === null || $account->user_id !== $user->id) {
            throw new ApiException(ErrorCode::ACCOUNT_NOT_FOUND);
        }
        if ($account->status !== Account::STATUS_ACTIVE) {
            throw new ApiException(ErrorCode::ACCOUNT_FROZEN);
        }

        $old = ['phone' => $user->phone];
        $user->phone = $data['phone'];
        $user->save();

        $this->audit->record(
            action: 'sbp.phone_linked',
            entityType: 'user',
            entityId: $user->id,
            userId: $user->id,
            old: $old,
            new: ['phone' => $user->phone, 'account_id' => $account->id],
            request: $request,
        );

        return ApiResponse::success(
            [
                'phone' => $user->phone,
                'account_id' => $account->id,
            ],
            request: $request,
        );
    }

    public function transfer(SbpTransferRequest $request): JsonResponse
    {
        $user = $this->mustHaveUser($request);
        $data = $request->validated();

        /** @var Account|null $sender */
        $sender = Account::query()->find($data['sender_account_id']);
        if ($sender === null || $sender->user_id !== $user->id) {
            throw new ApiException(ErrorCode::ACCOUNT_NOT_FOUND, 'Счёт отправителя не найден');
        }
        if ($sender->status !== Account::STATUS_ACTIVE) {
            throw new ApiException(ErrorCode::ACCOUNT_FROZEN);
        }

        // MVP envelope validation (full daily limit / FOR UPDATE flow happens
        // only when a real adapter is plugged in — out of scope for MVP).
        $amount = BigDecimal::of((string) $data['amount']);
        $min = BigDecimal::of((string) config('digitalbank.transfer.min_amount'));
        $max = BigDecimal::of((string) config('digitalbank.transfer.max_amount'));
        if ($amount->isLessThan($min)) {
            throw new ApiException(ErrorCode::AMOUNT_TOO_LOW);
        }
        if ($amount->isGreaterThan($max)) {
            throw new ApiException(ErrorCode::AMOUNT_TOO_HIGH);
        }

        $key = (string) $request->header('X-Idempotency-Key');

        // sbp_out: only sender is present; no receiver_account_id in our DB.
        $tx = Transaction::create([
            'sender_account_id' => $sender->id,
            'receiver_account_id' => null,
            'amount' => (string) $amount,
            'fee_amount' => '0.0000',
            'currency' => $sender->currency,
            'status' => Transaction::STATUS_PENDING,
            'type' => Transaction::TYPE_SBP_OUT,
            'idempotency_key' => $key,
            'description' => $data['description'] ?? ('SBP → '.$data['receiver_phone']),
        ]);

        // Mock gateway — no external call, no funds moved in MVP.
        $providerResp = $this->gateway->initiateTransfer([
            'transaction_id' => $tx->id,
            'amount' => (string) $amount,
            'currency' => $sender->currency,
            'receiver_phone' => $data['receiver_phone'],
        ]);

        $this->audit->record(
            action: 'sbp.transfer_initiated',
            entityType: 'transaction',
            entityId: $tx->id,
            userId: $user->id,
            new: [
                'receiver_phone' => $data['receiver_phone'],
                'amount' => (string) $amount,
                'provider_id' => $providerResp['provider_id'],
                'provider_status' => $providerResp['status'],
            ],
            request: $request,
        );

        return ApiResponse::success(
            [
                'id' => $tx->id,
                'status' => $tx->status,
                'amount' => $tx->amount,
                'currency' => $tx->currency,
                'type' => $tx->type,
                'provider_id' => $providerResp['provider_id'],
                'provider_status' => $providerResp['status'],
                'receiver_phone' => $data['receiver_phone'],
            ],
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
}
