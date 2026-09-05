<?php

namespace Tests\Feature;

use App\Models\Lot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LotInterestTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_mark_interest(): void
    {
        Lot::factory()->create(['lote_id' => 'guest-1']);

        $this->postJson(route('interesses.store', ['lote' => 'guest-1']))
            ->assertUnauthorized();
    }

    public function test_user_can_toggle_interest_on_a_lot(): void
    {
        $user = User::factory()->create();
        Lot::factory()->create(['lote_id' => 'want-1']);

        $this->actingAs($user)
            ->postJson(route('interesses.store', ['lote' => 'want-1']))
            ->assertOk()
            ->assertJson(['interested' => true, 'lote_id' => 'want-1']);

        $this->assertDatabaseHas('lot_interests', [
            'user_id' => $user->id,
            'lote_id' => 'want-1',
        ]);

        $this->actingAs($user)
            ->getJson(route('interesses.index'))
            ->assertOk()
            ->assertJson(['lote_ids' => ['want-1']]);

        $this->actingAs($user)
            ->deleteJson(route('interesses.destroy', ['lote' => 'want-1']))
            ->assertOk()
            ->assertJson(['interested' => false]);

        $this->assertDatabaseMissing('lot_interests', [
            'user_id' => $user->id,
            'lote_id' => 'want-1',
        ]);
    }

    public function test_unknown_lot_returns_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('interesses.store', ['lote' => 'missing']))
            ->assertNotFound();
    }
}
