<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $user_id
 * @property string $account_number
 * @property string $balance  numeric(19,4), cast as string to avoid float drift
 * @property string $currency
 * @property string $type
 * @property string $status
 * @property string|null $daily_limit
 */
class Account extends Model
{
    use HasUuids;
    use SoftDeletes;

    public const CURRENCY_RUB = 'RUB';
    public const CURRENCY_USD = 'USD';

    public const TYPE_CHECKING = 'checking';
    public const TYPE_SAVINGS = 'savings';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_FROZEN = 'frozen';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'user_id',
        'account_number',
        'balance',
        'currency',
        'type',
        'status',
        'daily_limit',
    ];

    protected function casts(): array
    {
        // NOTE: balance & daily_limit intentionally left as string casts —
        // brick/money wraps the string and any `float` cast would corrupt
        // the least-significant digits (§14 of SPEC: floats are banned).
        return [
            'balance' => 'string',
            'daily_limit' => 'string',
        ];
    }

    /** @return BelongsTo<User, Account> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Transaction> */
    public function outgoing(): HasMany
    {
        return $this->hasMany(Transaction::class, 'sender_account_id');
    }

    /** @return HasMany<Transaction> */
    public function incoming(): HasMany
    {
        return $this->hasMany(Transaction::class, 'receiver_account_id');
    }
}
