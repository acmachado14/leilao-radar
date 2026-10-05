<?php

namespace Tests\Unit;

use App\Models\Lot;
use App\Services\Lots\LotImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LotImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_skips_expired_lots_and_purges_stale_rows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-08 12:00:00', 'America/Sao_Paulo'));

        Lot::factory()->create(['lote_id' => 'stale-1', 'marca' => 'Stale']);

        $fixture = base_path('tests/fixtures/lotes.json');
        $importer = app(LotImporter::class);
        $result = $importer->import($fixture);

        $this->assertSame(2, $result['count']);
        $this->assertSame(2, Lot::query()->count());
        $this->assertNull(Lot::query()->find('stale-1'));

        Carbon::setTestNow();
    }

    public function test_import_with_all_expired_clears_catalog(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-01-01 12:00:00', 'America/Sao_Paulo'));

        Lot::factory()->create(['lote_id' => 'old-1']);

        $importer = app(LotImporter::class);
        $result = $importer->import(base_path('tests/fixtures/lotes.json'));

        $this->assertSame(0, $result['count']);
        $this->assertSame(0, Lot::query()->count());

        Carbon::setTestNow();
    }
}
