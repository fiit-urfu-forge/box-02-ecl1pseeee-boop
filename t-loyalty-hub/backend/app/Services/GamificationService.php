<?php

namespace App\Services;

use App\Models\UserGamification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class GamificationService
{
    private const CACHE_TTL = 60;

    private const TIERS = [
        80 => 'Мастер выгоды',
        50 => 'Рационалист',
        20 => 'Новичок+',
        0 => 'Новичок',
    ];

    public function calculateHealthScore(float $realCashback, float $shadowCashback): int
    {
        if ($shadowCashback <= 0) {
            return 0;
        }

        return (int) min(100, max(0, round(($realCashback / $shadowCashback) * 100)));
    }

    public function resolveTier(int $healthScore): string
    {
        foreach (self::TIERS as $threshold => $tier) {
            if ($healthScore >= $threshold) {
                return $tier;
            }
        }

        return 'Новичок';
    }

    public function recordVisit(int $userId): array
    {
        $gamification = UserGamification::firstOrCreate(
            ['user_id' => $userId],
            ['streak_days' => 0, 'health_score' => 0, 'loyalty_tier' => 'Новичок']
        );

        $today = Carbon::today();
        $lastVisit = $gamification->last_visit_date ? Carbon::parse($gamification->last_visit_date) : null;

        $gamification->streak_days = match (true) {
            $lastVisit === null => 1,
            $lastVisit->isSameDay($today) => max(1, (int) $gamification->streak_days),
            $lastVisit->copy()->addDay()->isSameDay($today) => (int) $gamification->streak_days + 1,
            default => 1,
        };

        $gamification->last_visit_date = $today;
        $gamification->save();

        Cache::forget("gamification:{$userId}");

        return $this->getForUser($userId);
    }

    public function updateScoreFromShadow(int $userId, float $realCashback, float $shadowCashback): void
    {
        $score = $this->calculateHealthScore($realCashback, $shadowCashback);
        $tier = $this->resolveTier($score);

        UserGamification::updateOrCreate(
            ['user_id' => $userId],
            ['health_score' => $score, 'loyalty_tier' => $tier]
        );

        Cache::forget("gamification:{$userId}");
    }

    public function getForUser(int $userId): array
    {
        return Cache::remember("gamification:{$userId}", self::CACHE_TTL, function () use ($userId) {
            $g = UserGamification::where('user_id', $userId)->first();

            return [
                'health_score' => (int) ($g->health_score ?? 0),
                'loyalty_tier' => $g->loyalty_tier ?? 'Новичок',
                'streak_days' => (int) ($g->streak_days ?? 0),
                'last_visit_date' => $g?->last_visit_date?->toDateString(),
            ];
        });
    }
}
