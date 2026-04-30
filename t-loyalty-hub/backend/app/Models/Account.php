<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $fillable = ['account_id', 'user_id', 'loyalty_program_id', 'current_balance'];

    protected $primaryKey = 'account_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $casts = [
        'current_balance' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loyaltyProgram(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'loyalty_program_id', 'loyalty_program_id');
    }

    public function loyaltyHistory(): HasMany
    {
        return $this->hasMany(LoyaltyHistory::class, 'account_id', 'account_id');
    }
}
