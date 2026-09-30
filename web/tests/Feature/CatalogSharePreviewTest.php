<?php

namespace Tests\Feature;

use App\Models\Lot;
use App\Support\EmailBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSharePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_lot_share_url_uses_catalog_query(): void
    {
        $lot = Lot::factory()->create(['lote_id' => 'share-1']);

        $this->assertSame(route('catalog', ['lote' => 'share-1']), $lot->shareUrl());
        $this->assertStringContainsString('/ofertas?lote=share-1', $lot->shareUrl());
        $this->assertStringNotContainsString('#lote=', $lot->shareUrl());
    }

    public function test_catalog_share_link_exposes_lot_open_graph_image(): void
    {
        $lot = Lot::factory()->create([
            'lote_id' => 'og-1',
            'titulo' => 'Honda Civic EX',
            'marca' => 'Honda',
            'modelo' => 'Civic EX',
            'ano_mod' => 2019,
            'desconto_label' => '32.0%',
            'foto_capa' => 'https://cdn.example.com/civic-cover.jpg',
        ]);

        $response = $this->get(route('catalog', ['lote' => $lot->lote_id]));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('Honda Civic EX — VerifyRadar', $html);
        $this->assertStringContainsString('property="og:title"', $html);
        $this->assertStringContainsString('Honda Civic EX', $html);
        $this->assertStringContainsString('property="og:image"', $html);
        $this->assertStringContainsString('https://cdn.example.com/civic-cover.jpg', $html);
        $this->assertStringContainsString($lot->shareUrl(), $html);
        $this->assertStringContainsString('name="twitter:card"', $html);
        $this->assertStringContainsString('summary_large_image', $html);
        $this->assertStringContainsString('Honda Civic EX 2019', $html);
        $this->assertStringContainsString('Desconto 32.0%', $html);
    }

    public function test_catalog_without_lote_does_not_use_another_lot_photo(): void
    {
        Lot::factory()->create([
            'lote_id' => 'other-1',
            'titulo' => 'Toyota Corolla Xei',
            'foto_capa' => 'https://cdn.example.com/other-cover.jpg',
        ]);

        $response = $this->get(route('catalog'));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('Ofertas — VerifyRadar', $html);
        $this->assertStringNotContainsString('https://cdn.example.com/other-cover.jpg', $html);
        $this->assertStringNotContainsString('Toyota Corolla Xei — VerifyRadar', $html);
        $this->assertStringContainsString(EmailBranding::logoUrl(), $html);
    }

    public function test_unknown_lote_keeps_generic_catalog_preview(): void
    {
        $response = $this->get(route('catalog', ['lote' => 'missing-lote']));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('Ofertas — VerifyRadar', $html);
        $this->assertStringContainsString(EmailBranding::logoUrl(), $html);
    }

    public function test_lot_without_photo_falls_back_to_logo_in_open_graph(): void
    {
        $lot = Lot::factory()->create([
            'lote_id' => 'no-photo',
            'titulo' => 'Fiat Argo',
            'foto_capa' => null,
            'fotos' => null,
        ]);

        $response = $this->get(route('catalog', ['lote' => $lot->lote_id]));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('Fiat Argo — VerifyRadar', $html);
        $this->assertStringContainsString(EmailBranding::logoUrl(), $html);
    }
}
