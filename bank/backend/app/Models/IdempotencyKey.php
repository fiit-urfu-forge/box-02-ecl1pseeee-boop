<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $key
 * @property string $user_id
 * @property string $endpoint
 * @property int $response_status
 * @property array<string, mixed> $response_body
 * @property \Illuminate\Support\Carbon $expires_at
 */
class IdempotencyKey extends Model
{
    protected $table = 'idempotency_keys';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    // Only created_at — entries are effectively immutable once saved.
    public const UPDATED_AT = null;

    protected $fillable = [
        'key',
        'user_id',
        'endpoint',
        'response_status',
        'response_body',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'response_status' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, IdempotencyKey> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
