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

    public function test_blank_recorte_does_not_match_any_lot(): void
    {
        $lot = Lot::factory()->create();
        $preference = new AlertPreference(AlertPreference::defaults());

        $this->assertFalse($preference->isConfigured());
        $this->assertFalse((new LotMatcher)->matches($lot, $preference));
    }

    public function test_matches_scoped_search(): void
    {
        $lot = Lot::factory()->create();
        $preference = new AlertPreference(AlertPreference::scoped());

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

    public function test_does_not_treat_gol_as_golf(): void
    {
        $golf = Lot::factory()->create([
            'marca' => 'Volkswagen',
            'modelo' => 'Golf GTI',
            'titulo' => 'Vw Golf GTI',
        ]);
        $gol = Lot::factory()->create([
            'marca' => 'Volkswagen',
            'modelo' => 'Gol 1.0',
            'titulo' => 'Vw Gol 1.0',
        ]);
        $preference = new AlertPreference(array_merge(AlertPreference::defaults(), [
            'search' => 'Gol',
        ]));

        $matcher = new LotMatcher;
        $this->assertFalse($matcher->matches($golf, $preference));
        $this->assertTrue($matcher->matches($gol, $preference));
    }

    public function test_marca_only_recorte_still_matches(): void
    {
        $lot = Lot::factory()->create(['marca' => 'Toyota']);
        $other = Lot::factory()->create([
            'marca' => 'Volkswagen',
            'modelo' => 'Gol',
            'titulo' => 'Vw Gol',
        ]);
        $preference = new AlertPreference(array_merge(AlertPreference::defaults(), [
            'search' => '',
            'marcas' => ['Toyota'],
        ]));

        $matcher = new LotMatcher;
        $this->assertTrue($preference->isConfigured());
        $this->assertTrue($matcher->matches($lot, $preference));
        $this->assertFalse($matcher->matches($other, $preference));
    }

    public function test_filters_by_vehicle_year(): void
    {
        $lot = Lot::factory()->create(['ano_mod' => 2018]);
        $preference = new AlertPreference(array_merge(AlertPreference::scoped(), [
            'ano_min' => 2020,
            'ano_max' => 2022,
        ]));

        $this->assertFalse((new LotMatcher)->matches($lot, $preference));

        $preference->ano_min = 2016;
        $preference->ano_max = 2018;
        $this->assertTrue((new LotMatcher)->matches($lot, $preference));
    }

    public function test_skips_ended_auctions(): void
    {
        $lot = Lot::factory()->create([
            'leilao_fim' => now('America/Sao_Paulo')->subDay()->format('Y-m-d H:i:s'),
        ]);
        $preference = new AlertPreference(AlertPreference::scoped());

        $this->assertFalse((new LotMatcher)->matches($lot, $preference));
    }

    public function test_date_only_stays_open_until_end_of_day(): void
    {
        $lot = Lot::factory()->create([
            'leilao_fim' => now('America/Sao_Paulo')->toDateString(),
        ]);
        $preference = new AlertPreference(AlertPreference::scoped());

        $this->assertTrue((new LotMatcher)->matches($lot, $preference));
    }
}
