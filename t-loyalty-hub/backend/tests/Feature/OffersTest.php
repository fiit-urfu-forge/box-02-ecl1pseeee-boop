<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OffersTest extends TestCase
{
    use RefreshDatabase;

    public function test_offers_filtered_by_segment_and_all(): void
    {
        $user = User::factory()->segment('HIGH')->create();
        $highOffer = Offer::factory()->create(['financial_segment' => 'HIGH', 'cashback_percent' => 5]);
        $allOffer = Offer::factory()->create(['financial_segment' => 'ALL', 'cashback_percent' => 12]);
        $lowOffer = Offer::factory()->create(['financial_segment' => 'LOW', 'cashback_percent' => 8]);

        $response = $this->getJson("/api/offers?user_id={$user->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($highOffer->id, $ids);
        $this->assertContains($allOffer->id, $ids);
        $this->assertNotContains($lowOffer->id, $ids);

        $this->assertEquals($allOffer->id, $response->json('data.0.id'));
    }

    public function test_offers_validates_user_exists(): void
    {
        $this->getJson('/api/offers?user_id=99999')->assertStatus(422);
    }
}
