<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only. DB triggers forbid UPDATE and DELETE.
 *
 * @property int $id
 * @property string|null $user_id
 * @property string $action
 * @property string $entity_type
 * @property string|null $entity_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon $created_at
 */
class AuditLogEntry extends Model
{
    protected $table = 'audit_log';
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, AuditLogEntry> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
