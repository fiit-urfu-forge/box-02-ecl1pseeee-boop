<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiStubsTest extends TestCase
{
    use RefreshDatabase;

    public function test_shadow_portfolio_returns_ai_response(): void
    {
        $user = User::factory()->create();

        Http::fake([
            '*ai/shadow-portfolio/*' => Http::response([
                'real_cashback' => 1200.50,
                'shadow_cashback' => 2400.00,
                'gap' => 1199.50,
                'insight' => 'Test insight',
                'health_score' => 50,
                'is_stub' => true,
            ]),
        ]);

        $response = $this->getJson("/api/ai/shadow-portfolio/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.health_score', 50)
            ->assertJsonPath('data.real_cashback', 1200.50)
            ->assertJsonPath('data.is_stub', true);

        $this->assertDatabaseHas('user_gamification', [
            'user_id' => $user->id,
            'health_score' => 50,
            'loyalty_tier' => 'Рационалист',
        ]);
    }

    public function test_shadow_portfolio_falls_back_when_engine_unreachable(): void
    {
        $user = User::factory()->create();

        Http::fake([
            '*ai/shadow-portfolio/*' => Http::response('upstream error', 503),
        ]);

        $this->getJson("/api/ai/shadow-portfolio/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.is_fallback', true)
            ->assertJsonPath('data.real_cashback', 0);
    }

    public function test_nudging_proxies_to_engine(): void
    {
        $user = User::factory()->create();

        Http::fake([
            '*ai/nudging/*' => Http::response([
                'message' => 'Активируй АЗС',
                'category' => 'АЗС',
                'boost_multiplier' => 2.5,
                'trigger_time' => 'вечером сегодня',
                'is_stub' => true,
            ]),
        ]);

        $this->getJson("/api/ai/nudging/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.category', 'АЗС')
            ->assertJsonPath('data.boost_multiplier', 2.5);
    }

    public function test_zero_click_falls_back_to_empty_offer(): void
    {
        $user = User::factory()->create();

        Http::fake([
            '*ai/zero-click/*' => fn () => throw new \Exception('connection refused'),
        ]);

        $this->getJson("/api/ai/zero-click/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.activated_offer', null)
            ->assertJsonPath('data.is_fallback', true);
    }
}
