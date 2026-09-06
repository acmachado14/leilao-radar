<?php

namespace Tests\Feature;

use App\Constants\Plan;
use App\Models\Lot;
use App\Models\User;
use App\Services\Billing\PlanQuota;
use App\Support\SalesWhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeAndAccountMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_is_the_product_landing(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('A IA diz até quanto pagar')
            ->assertSee('Radar Pro')
            ->assertSee('wa.me/5531986268630', false)
            ->assertDontSee('Meus lotes');
    }

    public function test_catalog_lives_at_ofertas(): void
    {
        $this->get('/ofertas')->assertOk()->assertSee('IA calcula o teto de lance');
        $this->assertSame(url('/ofertas'), route('catalog'));
    }

    public function test_approved_user_sees_account_menu_and_plan_page(): void
    {
        $user = User::factory()->create(['plan' => Plan::TRIAL]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Abrir menu')
            ->assertSee('Meus lotes')
            ->assertSee('Meu plano')
            ->assertSee('Minha conta')
            ->assertSee('Meus alertas')
            ->assertSee('Sair');

        $this->actingAs($user)
            ->get(route('meus-lotes'))
            ->assertOk()
            ->assertSee('Carros que você está acompanhando');

        $this->actingAs($user)
            ->get(route('plano'))
            ->assertOk()
            ->assertSee('Radar Pro é o plano')
            ->assertSee('wa.me/5531986268630', false)
            ->assertSee('Quero o Pro');
    }

    public function test_guest_cannot_open_account_area(): void
    {
        $this->get(route('meus-lotes'))->assertRedirect(route('login'));
        $this->get(route('plano'))->assertRedirect(route('login'));
        $this->get(route('conta'))->assertRedirect(route('login'));
    }

    public function test_lot_share_url_points_to_catalog(): void
    {
        $lot = Lot::factory()->create(['lote_id' => 'share-1']);

        $this->assertStringContainsString('/ofertas#lote=share-1', $lot->shareUrl());
    }

    public function test_dashboard_redirects_to_my_lots(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('meus-lotes'));
    }

    public function test_upgrade_always_points_to_radar_pro(): void
    {
        $quota = app(PlanQuota::class);
        $user = User::factory()->create(['plan' => Plan::TRIAL]);

        $this->assertSame(Plan::RADAR_PRO, $quota->suggestedUpgrade(Plan::TRIAL));
        $this->assertSame(Plan::RADAR_PRO, $quota->suggestedUpgrade(Plan::RADAR));
        $this->assertStringContainsString('Radar Pro', urldecode(SalesWhatsApp::checkoutUrl($quota->suggestedUpgrade($user->plan), $user)));
    }
}
