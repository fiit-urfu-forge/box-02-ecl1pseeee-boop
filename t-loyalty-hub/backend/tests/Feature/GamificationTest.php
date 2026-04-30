<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGamification;
use App\Services\GamificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_visit_starts_streak_at_one(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson("/api/gamification/{$user->id}/visit")->assertOk();

        $this->assertEquals(1, $response->json('data.streak_days'));
        $this->assertDatabaseHas('user_gamification', ['user_id' => $user->id, 'streak_days' => 1]);
    }

    public function test_record_visit_increments_streak_when_consecutive(): void
    {
        $user = User::factory()->create();
        UserGamification::create([
            'user_id' => $user->id,
            'streak_days' => 4,
            'health_score' => 60,
            'loyalty_tier' => 'Рационалист',
            'last_visit_date' => Carbon::yesterday(),
        ]);

        $response = $this->postJson("/api/gamification/{$user->id}/visit")->assertOk();
        $this->assertEquals(5, $response->json('data.streak_days'));
    }

    public function test_record_visit_resets_streak_after_gap(): void
    {
        $user = User::factory()->create();
        UserGamification::create([
            'user_id' => $user->id,
            'streak_days' => 9,
            'health_score' => 60,
            'loyalty_tier' => 'Рационалист',
            'last_visit_date' => Carbon::today()->subDays(3),
        ]);

        $response = $this->postJson("/api/gamification/{$user->id}/visit")->assertOk();
        $this->assertEquals(1, $response->json('data.streak_days'));
    }

    public function test_health_score_calculation_and_tier_resolution(): void
    {
        $service = app(GamificationService::class);

        $this->assertEquals(0, $service->calculateHealthScore(100, 0));
        $this->assertEquals(50, $service->calculateHealthScore(50, 100));
        $this->assertEquals(100, $service->calculateHealthScore(120, 100));

        $this->assertEquals('Новичок', $service->resolveTier(5));
        $this->assertEquals('Новичок+', $service->resolveTier(25));
        $this->assertEquals('Рационалист', $service->resolveTier(60));
        $this->assertEquals('Мастер выгоды', $service->resolveTier(85));
    }
}
