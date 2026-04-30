<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyHistory extends Model
{
    use HasFactory;

    protected $table = 'loyalty_history';

    protected $fillable = ['transaction_id', 'account_id', 'cashback_amount', 'payout_date'];

    protected $primaryKey = 'transaction_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $casts = [
        'payout_date' => 'date',
        'cashback_amount' => 'decimal:2',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id', 'account_id');
    }
}
