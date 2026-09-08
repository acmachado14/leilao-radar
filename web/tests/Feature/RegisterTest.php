<?php

namespace Tests\Feature;

use App\Constants\SubscriptionStatus;
use App\Livewire\Register;
use App\Models\AdminActivityLog;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_register_and_login_pages(): void
    {
        $this->get(route('register'))->assertOk();
        $this->get(route('login'))->assertOk();
        $this->get(route('catalog'))->assertOk();
    }

    public function test_register_starts_trial_without_waiting_for_approval(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'Ana Radar')
            ->set('email', 'ana@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('terms_accepted', true)
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('catalog'));

        $user = User::query()->where('email', 'ana@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame(SubscriptionStatus::TRIAL, $user->subscription_status);
        $this->assertSame('trial', $user->plan);
        $this->assertNotNull($user->approved_at);
        $this->assertFalse($user->isPending());
        $this->assertTrue($user->canReceiveAlerts());
        $this->assertTrue($user->subscription_until->greaterThan(now()->addDays(6)));
        $this->assertNull($user->alertPreference);
        $this->assertSame(0, $user->alertPreferences()->count());
        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'registered',
            'subject_user_id' => $user->id,
        ]);
        $this->assertSame(1, AdminActivityLog::query()->count());

        Queue::fake();
        Lot::factory()->create(['lote_id' => 'trial-eval-1']);
        $this->actingAs($user)
            ->postJson(route('avaliacoes.store', ['lote' => 'trial-eval-1']))
            ->assertOk();
    }

    public function test_trial_user_can_login_again_after_logout(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'Ana Radar')
            ->set('email', 'ana@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('terms_accepted', true)
            ->call('register')
            ->assertHasNoErrors();

        $user = User::query()->where('email', 'ana@example.com')->first();
        $this->assertTrue(Hash::check('password123', $user->password));

        $this->get(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post(route('login.store'), [
            'email' => 'Ana@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_register_whatsapp_opt_in_does_not_create_a_matching_recorte(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'Ana Radar')
            ->set('email', 'ana@example.com')
            ->set('phone', '11999999999')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('terms_accepted', true)
            ->set('notify_whatsapp', true)
            ->call('register')
            ->assertHasNoErrors();

        $user = User::query()->where('email', 'ana@example.com')->first();
        $this->assertSame(1, $user->alertPreferences()->count());
        $this->assertFalse($user->alertPreference->isConfigured());
        $this->assertTrue($user->alertPreference->notify_whatsapp);
    }
}
