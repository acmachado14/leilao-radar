<?php

namespace Tests\Feature;

use App\Constants\SubscriptionStatus;
use App\Livewire\Register;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_register_creates_trial_user_and_preferences(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'Ana Radar')
            ->set('email', 'ana@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('terms_accepted', true)
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('alertas'));

        $user = User::query()->where('email', 'ana@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame(SubscriptionStatus::TRIAL, $user->subscription_status);
        $this->assertTrue($user->canReceiveAlerts());
        $this->assertNotNull($user->alertPreference);
        $this->assertTrue($user->alertPreference->notify_email);
        $this->assertFalse($user->alertPreference->notify_whatsapp);
    }
}
