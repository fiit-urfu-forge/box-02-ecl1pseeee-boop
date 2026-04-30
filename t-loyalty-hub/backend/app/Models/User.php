<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Model
{
    use HasFactory;

    protected $fillable = ['id', 'email', 'phone_number', 'full_name', 'financial_segment'];

    public $incrementing = false;

    protected $keyType = 'int';

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function gamification(): HasOne
    {
        return $this->hasOne(UserGamification::class);
    }

    public function isHighSegment(): bool
    {
        return $this->financial_segment === 'HIGH';
    }
}
