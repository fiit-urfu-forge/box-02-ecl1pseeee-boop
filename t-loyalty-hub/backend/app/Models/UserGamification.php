<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGamification extends Model
{
    use HasFactory;

    protected $table = 'user_gamification';

    protected $fillable = [
        'user_id',
        'health_score',
        'loyalty_tier',
        'streak_days',
        'last_visit_date',
    ];

    protected $casts = [
        'last_visit_date' => 'date',
        'health_score' => 'integer',
        'streak_days' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
