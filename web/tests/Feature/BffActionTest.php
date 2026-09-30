<?php

namespace Tests\Feature;

use App\Models\Lot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BffActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_action_returns_paywall_and_token(): void
    {
        $response = $this->postJson('/bff/v1/actions/register', [
            'name' => 'Ana Radar',
            'email' => 'ana@example.com',
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'terms_accepted' => '1',
        ]);

        $response->assertOk()
            ->assertJsonPath('screen', 'paywall')
            ->assertJsonStructure(['token']);
        $this->assertTrue(collect($response->json('components'))->contains(fn ($node) => ($node['type'] ?? '') === 'Paywall'));
        $this->assertJsonTextContains($response, 'Continuar grátis');
        $this->assertStringNotContainsString('wa.me', $response->getContent());
        $this->assertDatabaseHas('users', [
            'email' => 'ana@example.com',
            'plan' => 'trial',
            'subscription_until' => null,
        ]);
    }

    public function test_invalid_login_stays_on_login_without_token(): void
    {
        $response = $this->postJson('/bff/v1/actions/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('screen', 'login')
            ->assertJsonMissingPath('token');
        $this->assertJsonTextContains($response, 'E-mail ou senha inválidos.');
        $entrar = collect($response->json('components'))->first(
            fn ($node) => ($node['props']['label'] ?? '') === 'Entrar'
        );
        $this->assertSame('submit', $entrar['onPress']['type'] ?? null);
        $this->assertArrayNotHasKey('then', $entrar['onPress'] ?? []);
    }

    public function test_valid_login_returns_catalog_and_token(): void
    {
        User::factory()->create([
            'email' => 'ana@example.com',
            'password' => 'password12',
        ]);

        $response = $this->postJson('/bff/v1/actions/login', [
            'email' => 'ana@example.com',
            'password' => 'password12',
        ]);

        $response->assertOk()
            ->assertJsonPath('screen', 'catalog')
            ->assertJsonStructure(['token']);
    }

    public function test_register_validation_errors_are_in_portuguese(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $taken = $this->postJson('/bff/v1/actions/register', [
            'name' => 'Ana Radar',
            'email' => 'ana@example.com',
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'terms_accepted' => '1',
        ]);
        $taken->assertOk()->assertJsonPath('screen', 'register');
        $this->assertJsonTextContains($taken, 'Não foi possível criar a conta');
        $this->assertStringNotContainsString('Já existe uma conta', $taken->getContent());
        $this->assertStringNotContainsString('has already been taken', $taken->getContent());

        $short = $this->postJson('/bff/v1/actions/register', [
            'name' => 'Ana Radar',
            'email' => 'nova@example.com',
            'password' => 'curta',
            'password_confirmation' => 'curta',
        ]);
        $short->assertOk()->assertJsonPath('screen', 'register');
        $this->assertJsonTextContains($short, 'pelo menos 8 caracteres');

        $mismatch = $this->postJson('/bff/v1/actions/register', [
            'name' => 'Ana Radar',
            'email' => 'nova@example.com',
            'password' => 'password12',
            'password_confirmation' => 'outra-senha',
        ]);
        $mismatch->assertOk()->assertJsonPath('screen', 'register');
        $this->assertJsonTextContains($mismatch, 'confirmação da senha não confere');

        $noTerms = $this->postJson('/bff/v1/actions/register', [
            'name' => 'Ana Radar',
            'email' => 'sem-termos@example.com',
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'terms_accepted' => '0',
        ]);
        $noTerms->assertOk()->assertJsonPath('screen', 'register');
        $this->assertJsonTextContains($noTerms, 'Aceite os termos de uso');
    }

    public function test_evaluate_without_quota_returns_paywall_navigation(): void
    {
        $user = User::factory()->create([
            'subscription_until' => now()->subDay(),
            'subscription_status' => 'expired',
        ]);
        $lot = Lot::factory()->create();
        $token = $user->createToken('ios')->plainTextToken;

        $response = $this->withToken($token)->postJson('/bff/v1/actions/evaluate', [
            'id' => $lot->lote_id,
        ]);

        $response->assertOk()->assertJsonPath('screen', 'evaluation');
        $this->assertStringContainsString('Assine na App Store', $response->getContent());
        $this->assertStringNotContainsString('atendente', $response->getContent());
    }

    public function test_alerts_crud_creates_updates_and_deletes(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('ios')->plainTextToken;

        $created = $this->withToken($token)->postJson('/bff/v1/actions/save_alerts', [
            'name' => 'Jetta GLI',
            'search' => 'Jetta GLI',
            'fonte_sodre' => true,
            'fonte_palacio' => false,
            'fipe_exact' => true,
            'fipe_closest' => false,
            'fipe_failed' => false,
            'monta_pequena' => true,
            'exclude_grande' => true,
            'min_desconto' => 10,
            'ano_min' => 2018,
            'ano_max' => 2022,
        ]);
        $created->assertOk()->assertJsonPath('screen', 'alerts');
        $this->assertDatabaseHas('alert_preferences', [
            'user_id' => $user->id,
            'name' => 'Jetta GLI',
            'search' => 'Jetta GLI',
        ]);

        $preference = $user->alertPreferences()->first();
        $this->assertNotNull($preference);
        $this->assertSame(['sodre'], $preference->fontes);
        $this->assertEqualsWithDelta(0.10, (float) $preference->min_desconto, 0.0001);

        $updated = $this->withToken($token)->postJson('/bff/v1/actions/save_alerts', [
            'id' => $preference->id,
            'name' => 'Jetta GLI 2020',
            'search' => 'Jetta GLI',
            'fonte_sodre' => true,
            'fonte_palacio' => true,
        ]);
        $updated->assertOk();
        $this->assertSame('Jetta GLI 2020', $preference->fresh()->name);
        $this->assertSame(['sodre', 'palacio'], $preference->fresh()->fontes);

        $deleted = $this->withToken($token)->postJson('/bff/v1/actions/delete_alerts', [
            'id' => $preference->id,
        ]);
        $deleted->assertOk()->assertJsonPath('screen', 'alerts');
        $this->assertDatabaseMissing('alert_preferences', ['id' => $preference->id]);
    }
}
