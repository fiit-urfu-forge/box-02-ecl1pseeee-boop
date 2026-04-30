<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_index_returns_all_demo_users(): void
    {
        User::factory()->segment('LOW')->create();
        User::factory()->segment('MEDIUM')->create();
        User::factory()->segment('HIGH')->create();

        $response = $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $segments = collect($response->json('data'))->pluck('financial_segment')->sort()->values()->all();
        $this->assertEquals(['HIGH', 'LOW', 'MEDIUM'], $segments);
    }

    public function test_user_show_returns_specific_user(): void
    {
        $user = User::factory()->segment('HIGH')->create(['full_name' => 'Иванов И.И.']);

        $this->getJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.id', (int) $user->id)
            ->assertJsonPath('data.full_name', 'Иванов И.И.')
            ->assertJsonPath('data.financial_segment', 'HIGH');
    }

    public function test_user_show_returns_404_for_unknown(): void
    {
        $this->getJson('/api/users/99999')->assertNotFound();
    }
}
