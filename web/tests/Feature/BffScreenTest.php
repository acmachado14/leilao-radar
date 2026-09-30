<?php

namespace Tests\Feature;

use App\Constants\EntitlementSource;
use App\Constants\Plan;
use App\Constants\SubscriptionStatus;
use App\Models\Lot;
use App\Models\LotEvaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BffScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_is_a_component_tree(): void
    {
        $response = $this->getJson('/bff/v1/screens/login');

        $response->assertOk()
            ->assertJsonPath('screen', 'login')
            ->assertJsonPath('schema_version', 1);

        $types = collect($response->json('components'))->pluck('type');
        $this->assertTrue($types->contains('TextField'));
        $this->assertTrue($types->contains('Button'));
        $email = collect($response->json('components'))->first(
            fn ($node) => ($node['props']['name'] ?? '') === 'email'
        );
        $password = collect($response->json('components'))->first(
            fn ($node) => ($node['props']['name'] ?? '') === 'password'
        );
        $this->assertSame('username', $email['props']['autoComplete'] ?? null);
        $this->assertSame('password', $password['props']['autoComplete'] ?? null);
        $entrar = collect($response->json('components'))->first(
            fn ($node) => ($node['props']['label'] ?? '') === 'Entrar'
        );
        $this->assertSame('login', $entrar['onPress']['action'] ?? null);
        $this->assertArrayNotHasKey('then', $entrar['onPress'] ?? []);
        $this->assertStringNotContainsString('wa.me', $response->getContent());
        $this->assertStringNotContainsString('checkout_url', $response->getContent());
    }

    public function test_register_screen_creates_a_free_account_without_picking_a_plan(): void
    {
        $response = $this->getJson('/bff/v1/screens/register');

        $response->assertOk()->assertJsonPath('screen', 'register');
        $types = collect($response->json('components'))->pluck('type');
        $this->assertFalse($types->contains('Paywall'));
        $this->assertTrue($types->contains('Checkbox'));
        $terms = collect($response->json('components'))->first(
            fn ($node) => ($node['props']['name'] ?? '') === 'terms_accepted'
        );
        $this->assertNotNull($terms);
        $this->assertJsonTextContains($response, 'Criar conta grátis');
        $this->assertStringNotContainsString('wa.me', $response->getContent());
        $this->assertStringNotContainsString('radar_monthly', $response->getContent());
    }

    public function test_catalog_screen_renders_lot_cards_from_the_bff(): void
    {
        Lot::factory()->create([
            'lote_id' => 'ios-1',
            'titulo' => 'Fiat Toro',
            'foto_capa' => 'https://example.test/toro.jpg',
        ]);

        $response = $this->getJson('/bff/v1/screens/catalog');

        $response->assertOk()->assertJsonPath('screen', 'catalog');
        $list = collect($response->json('components'))->firstWhere('type', 'List');
        $this->assertNotNull($list);
        $card = collect($list['children'] ?? [])->firstWhere('type', 'LotCard');
        $this->assertSame('LotCard', $card['type'] ?? null);
        $this->assertSame('https://example.test/toro.jpg', $card['props']['photo'] ?? null);
        $this->assertNotEmpty($card['props']['lance'] ?? null);
        $this->assertStringContainsString('Fiat Toro', $response->getContent());
    }

    public function test_web_subscriber_gets_account_without_paywall_cta(): void
    {
        $user = User::factory()->create([
            'plan' => Plan::RADAR,
            'subscription_status' => SubscriptionStatus::ACTIVE,
            'entitlement_source' => EntitlementSource::WEB,
            'subscription_until' => now()->addMonth(),
        ]);
        $token = $user->createToken('ios')->plainTextToken;

        $response = $this->withToken($token)->getJson('/bff/v1/screens/account');

        $response->assertOk()
            ->assertJsonPath('meta.entitlement.source', EntitlementSource::WEB)
            ->assertJsonPath('meta.entitlement.can_show_paywall', false);
        $this->assertStringNotContainsString('wa.me', $response->getContent());
        $this->assertStringNotContainsString('Assinar no iPhone', $response->getContent());
        $this->assertStringContainsString('Plano ativo pela web', $response->getContent());
        $this->assertStringContainsString('Ver planos', $response->getContent());
        $this->assertStringContainsString('Restaurar compras', $response->getContent());
        $this->assertStringContainsString('Gerenciar assinatura Apple', $response->getContent());
        $this->assertStringContainsString('Apagar conta', $response->getContent());
        $this->assertStringNotContainsString('"style":"danger"', $response->getContent());
    }

    public function test_catalog_filters_match_the_website(): void
    {
        Lot::factory()->create([
            'lote_id' => 'sodre-1',
            'titulo' => 'Fiat Toro Sodre',
            'fonte' => 'sodre',
            'fipe_match' => 'exact',
            'classificacao_monta' => 'pequena',
            'relevance_score' => 0.9,
        ]);
        Lot::factory()->create([
            'lote_id' => 'palacio-1',
            'titulo' => 'Honda Civic Palacio',
            'marca' => 'Honda',
            'modelo' => 'Civic',
            'fonte' => 'palacio',
            'fipe_match' => 'exact',
            'classificacao_monta' => 'pequena',
            'relevance_score' => 0.8,
        ]);
        Lot::factory()->create([
            'lote_id' => 'outro-1',
            'titulo' => 'Outro monta',
            'fonte' => 'sodre',
            'fipe_match' => 'exact',
            'classificacao_monta' => 'outro',
            'relevance_score' => 1,
        ]);
        Lot::factory()->create([
            'lote_id' => 'expired-1',
            'titulo' => 'Lote vencido',
            'fonte' => 'sodre',
            'fipe_match' => 'exact',
            'classificacao_monta' => 'pequena',
            'leilao_fim' => now('America/Sao_Paulo')->subDay()->format('Y-m-d H:i:s'),
            'leilao_em' => now('America/Sao_Paulo')->subDay()->format('Y-m-d H:i:s'),
            'relevance_score' => 2,
        ]);
        Lot::factory()->create([
            'lote_id' => 'titulo-only',
            'titulo' => 'Civic especial',
            'marca' => 'Ford',
            'modelo' => 'Ka',
            'fonte' => 'sodre',
            'fipe_match' => 'exact',
            'classificacao_monta' => 'pequena',
            'relevance_score' => 0.7,
        ]);

        $catalog = $this->getJson('/bff/v1/screens/catalog');
        $catalog->assertOk();
        $list = collect($catalog->json('components'))->firstWhere('type', 'List');
        $this->assertNotNull($list);
        $childTypes = collect($list['children'] ?? [])->pluck('type');
        $this->assertTrue($childTypes->contains('ChipRow'));
        $this->assertTrue($childTypes->contains('SearchBar'));
        $this->assertFalse(collect($catalog->json('components'))->pluck('type')->contains('Checkbox'));
        $this->assertStringContainsString('exibindo', $catalog->getContent());
        $this->assertStringContainsString('no snapshot', $catalog->getContent());
        $this->assertStringNotContainsString('Aplicar filtros', $catalog->getContent());
        $this->assertStringContainsString('Fiat Toro Sodre', $catalog->getContent());
        $this->assertStringContainsString('Honda Civic Palacio', $catalog->getContent());
        $this->assertStringNotContainsString('Outro monta', $catalog->getContent());
        $this->assertStringNotContainsString('Lote vencido', $catalog->getContent());

        $filtered = $this->getJson('/bff/v1/screens/catalog?fontes=palacio');
        $filtered->assertOk();
        $this->assertStringContainsString('Honda Civic Palacio', $filtered->getContent());
        $this->assertStringNotContainsString('Fiat Toro Sodre', $filtered->getContent());

        $search = $this->getJson('/bff/v1/screens/catalog?q=Civic');
        $search->assertOk();
        $this->assertStringContainsString('Honda Civic Palacio', $search->getContent());
        $this->assertStringNotContainsString('titulo-only', $search->getContent());
        $this->assertStringNotContainsString('Civic especial', $search->getContent());
    }

    public function test_lot_screen_opens_the_official_auction_url(): void
    {
        $lot = Lot::factory()->create([
            'lote_id' => 'link-1',
            'fonte' => 'palacio',
            'url' => 'https://www.leilaopalacio.com.br/lote/link-1',
            'foto_capa' => 'https://cdn.example.com/a.jpg',
            'fotos' => ['https://cdn.example.com/a.jpg', 'https://cdn.example.com/b.jpg'],
            'lance_atual' => 42000,
            'fipe_preco' => 61000,
            'patio' => 'Guarulhos',
        ]);

        $response = $this->getJson('/bff/v1/screens/lot?id='.$lot->lote_id);

        $response->assertOk();
        $gallery = collect($response->json('components'))->firstWhere('type', 'Gallery');
        $this->assertNotNull($gallery);
        $this->assertSame(
            ['https://cdn.example.com/a.jpg', 'https://cdn.example.com/b.jpg'],
            $gallery['props']['photos'] ?? null,
        );
        $this->assertTrue(collect($response->json('components'))->contains(fn ($node) => ($node['type'] ?? '') === 'Group'));
        $this->assertStringContainsString('Lance atual', $response->getContent());
        $this->assertStringContainsString('Tabela FIPE', $response->getContent());
        $this->assertStringContainsString('Guarulhos', $response->getContent());
        $openUrl = collect($response->json('components'))->first(
            fn ($node) => ($node['onPress']['type'] ?? null) === 'open_url'
        );
        $this->assertNotNull($openUrl);
        $this->assertSame('https://www.leilaopalacio.com.br/lote/link-1', $openUrl['onPress']['url'] ?? null);
    }

    public function test_paid_plan_is_not_labelled_trial(): void
    {
        $user = User::factory()->create([
            'plan' => Plan::RADAR_PRO,
            'subscription_status' => SubscriptionStatus::TRIAL,
            'entitlement_source' => EntitlementSource::WEB,
            'subscription_until' => now()->addMonth(),
        ]);
        $token = $user->createToken('ios')->plainTextToken;

        $response = $this->withToken($token)->getJson('/bff/v1/screens/account');

        $response->assertOk()
            ->assertJsonPath('meta.entitlement.plan', Plan::RADAR_PRO)
            ->assertJsonPath('meta.entitlement.plan_name', 'Radar Pro')
            ->assertJsonPath('meta.entitlement.status_label', 'Ativa')
            ->assertJsonPath('meta.entitlement.can_show_paywall', false);
        $this->assertStringContainsString('Radar Pro', $response->getContent());
        $this->assertStringContainsString('Ver planos', $response->getContent());
        $accountTypes = collect($response->json('components'))->flatMap(
            fn ($node) => collect([$node])->merge($node['children'] ?? [])
        )->pluck('type');
        $this->assertTrue($accountTypes->contains('Group'));
        $this->assertTrue($accountTypes->contains('Row'));
    }

    public function test_native_legal_screens_are_plain_text(): void
    {
        $terms = $this->getJson('/bff/v1/screens/terms');
        $privacy = $this->getJson('/bff/v1/screens/privacy');

        $terms->assertOk()->assertJsonPath('screen', 'terms');
        $privacy->assertOk()->assertJsonPath('screen', 'privacy');
        $this->assertStringContainsString('Termos de uso', $terms->getContent());
        $this->assertStringContainsString('renovam automaticamente', $terms->getContent());
        $this->assertStringContainsString('alucina', $terms->getContent());
        $this->assertStringContainsString('Tratamos nome, e-mail', $privacy->getContent());
        $this->assertStringNotContainsString('<article', $privacy->getContent());
        $this->assertStringNotContainsString('prose-invert', $privacy->getContent());
    }

    public function test_paywall_is_always_listed_in_tabs(): void
    {
        $user = User::factory()->create([
            'plan' => Plan::TRIAL,
            'subscription_status' => SubscriptionStatus::TRIAL,
            'subscription_until' => now()->addDays(5),
        ]);
        $token = $user->createToken('ios')->plainTextToken;

        $response = $this->withToken($token)->getJson('/bff/v1/screens/paywall');

        $response->assertOk()->assertJsonPath('screen', 'paywall');
        $this->assertTrue(collect($response->json('tabs'))->contains(fn ($tab) => ($tab['id'] ?? '') === 'paywall'));
        $this->assertTrue(collect($response->json('components'))->contains(fn ($node) => ($node['type'] ?? '') === 'Paywall'));
        $this->assertStringContainsString('34,90', $response->getContent());
        $this->assertStringContainsString('49,90', $response->getContent());
        $this->assertJsonTextContains($response, '7 dias grátis');
        $this->assertJsonTextContains($response, 'renova automaticamente');
        $this->assertJsonTextContains($response, 'sheet da Apple');
        $this->assertStringNotContainsString('Restaurar compras', $response->getContent());
        $this->assertJsonTextContains($response, 'Continuar grátis');
        $this->assertStringContainsString('stdeula', $response->getContent());
        $this->assertStringNotContainsString('wa.me', $response->getContent());
    }

    public function test_alerts_list_supports_creating_a_new_recorte(): void
    {
        $user = User::factory()->create();
        $user->alertPreferences()->create([
            'name' => 'Civic',
            'search' => 'Civic',
            ...\App\Models\AlertPreference::defaults(),
        ]);
        $token = $user->createToken('ios')->plainTextToken;

        $list = $this->withToken($token)->getJson('/bff/v1/screens/alerts');
        $list->assertOk();
        $this->assertStringContainsString('Novo recorte', $list->getContent());
        $this->assertTrue(collect($list->json('components'))->contains(fn ($node) => ($node['type'] ?? '') === 'Group'));

        $edit = $this->withToken($token)->getJson('/bff/v1/screens/alerts_edit');
        $edit->assertOk()->assertJsonPath('screen', 'alerts_edit');
        $this->assertStringContainsString('Adicionar recorte', $edit->getContent());
    }

    public function test_pending_evaluation_screen_has_ai_loading_and_polls(): void
    {
        $user = User::factory()->create();
        $lot = Lot::factory()->create();
        LotEvaluation::query()->create([
            'lote_id' => $lot->lote_id,
            'status' => 'pending',
            'source_hash' => 'pending-hash',
        ]);
        $token = $user->createToken('ios')->plainTextToken;

        $response = $this->withToken($token)->getJson('/bff/v1/screens/evaluation?id='.$lot->lote_id);

        $response->assertOk()
            ->assertJsonPath('screen', 'evaluation')
            ->assertJsonPath('meta.status', 'pending')
            ->assertJsonPath('meta.poll.screen', 'evaluation')
            ->assertJsonPath('meta.poll.params.id', $lot->lote_id);
        $this->assertTrue(collect($response->json('components'))->contains(
            fn ($node) => ($node['type'] ?? '') === 'AiLoading'
        ));
        $this->assertStringContainsString('Voltar ao lote', $response->getContent());
    }
}
