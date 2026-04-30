<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyProgram extends Model
{
    use HasFactory;

    protected $fillable = ['loyalty_program_id', 'loyalty_program_name', 'cashback_currency'];

    protected $primaryKey = 'loyalty_program_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class, 'loyalty_program_id', 'loyalty_program_id');
    }
}
