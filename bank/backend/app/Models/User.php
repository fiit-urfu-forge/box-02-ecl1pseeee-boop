<?php

declare(strict_types=1);

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password_hash
 * @property string|null $phone
 * @property string|null $avatar_path
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $failed_login_at
 * @property int $failed_login_count
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use HasUuids;
    use Notifiable;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_BLOCKED = 'blocked';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'email_verified_at',
        'password_hash',
        'phone',
        'avatar_path',
        'status',
        'failed_login_at',
        'failed_login_count',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'failed_login_at' => 'datetime',
            'failed_login_count' => 'integer',
        ];
    }

    /**
     * Laravel's Authenticatable contract expects `getAuthPassword()` to
     * return the hashed password. Our column is `password_hash`.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /** @return HasMany<Account> */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /**
     * Use the queueable notification defined in App\Notifications instead
     * of Laravel's default so the email is sent via the `notifications` queue.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }
}
