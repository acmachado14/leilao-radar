<?php

namespace Tests\Unit;

use App\Models\AlertPreference;
use App\Models\Lot;
use App\Services\Alerts\LotMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LotMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_matches_default_preferences(): void
    {
        $lot = Lot::factory()->create();
        $preference = new AlertPreference(AlertPreference::defaults());

        $this->assertTrue((new LotMatcher)->matches($lot, $preference));
    }

    public function test_filters_by_search_and_fonte(): void
    {
        $lot = Lot::factory()->create([
            'marca' => 'Volkswagen',
            'modelo' => 'Jetta GLI',
            'titulo' => 'Vw Jetta GLI',
            'fonte' => 'sodre',
        ]);
        $preference = new AlertPreference(array_merge(AlertPreference::defaults(), [
            'search' => 'jetta gli',
            'fontes' => ['palacio'],
        ]));

        $this->assertFalse((new LotMatcher)->matches($lot, $preference));

        $preference->fontes = ['sodre'];
        $this->assertTrue((new LotMatcher)->matches($lot, $preference));
    }

    public function test_skips_ended_auctions(): void
    {
        $lot = Lot::factory()->create([
            'leilao_fim' => now('America/Sao_Paulo')->subDay()->format('Y-m-d H:i:s'),
        ]);
        $preference = new AlertPreference(AlertPreference::defaults());

        $this->assertFalse((new LotMatcher)->matches($lot, $preference));
    }

    public function test_date_only_stays_open_until_end_of_day(): void
    {
        $lot = Lot::factory()->create([
            'leilao_fim' => now('America/Sao_Paulo')->toDateString(),
        ]);
        $preference = new AlertPreference(AlertPreference::defaults());

        $this->assertTrue((new LotMatcher)->matches($lot, $preference));
    }
}
