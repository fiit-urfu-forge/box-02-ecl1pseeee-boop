<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $sender_account_id
 * @property string|null $receiver_account_id
 * @property string $amount
 * @property string $fee_amount
 * @property string $currency
 * @property string $status
 * @property string $type
 * @property string $idempotency_key
 * @property string|null $description
 * @property string|null $error_code
 * @property \Illuminate\Support\Carbon|null $processed_at
 */
class Transaction extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_INTERNAL = 'internal';
    public const TYPE_SBP_OUT = 'sbp_out';
    public const TYPE_SBP_IN = 'sbp_in';

    /** §4.2 — valid state transitions. */
    public const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_PROCESSING, self::STATUS_CANCELLED],
        self::STATUS_PROCESSING => [self::STATUS_SUCCESS, self::STATUS_FAILED],
        self::STATUS_SUCCESS => [],
        self::STATUS_FAILED => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'sender_account_id',
        'receiver_account_id',
        'amount',
        'fee_amount',
        'currency',
        'status',
        'type',
        'idempotency_key',
        'description',
        'error_code',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'string',
            'fee_amount' => 'string',
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Account, Transaction> */
    public function senderAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'sender_account_id');
    }

    /** @return BelongsTo<Account, Transaction> */
    public function receiverAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'receiver_account_id');
    }

    public function canTransitionTo(string $next): bool
    {
        return in_array($next, self::TRANSITIONS[$this->status] ?? [], true);
    }
}
