<?php

namespace Tests\Feature;

use App\Constants\SubscriptionStatus;
use App\Livewire\Admin\Logs as AdminLogs;
use App\Livewire\Admin\Overview;
use App\Livewire\Admin\Subscribers;
use App\Models\AlertPreference;
use App\Models\Lot;
use App\Models\User;
use App\Services\Alerts\AlertDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class AdminApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_user_cannot_open_dashboard_or_alerts(): void
    {
        $user = User::factory()->pending()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('aguardando'));

        $this->actingAs($user)
            ->get(route('alertas'))
            ->assertRedirect(route('aguardando'));

        $this->actingAs($user)
            ->get(route('aguardando'))
            ->assertOk();
    }

    public function test_guest_cannot_open_admin_pages(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.assinantes'))->assertRedirect(route('login'));
        $this->get(route('admin.logs'))->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_open_admin_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.logs'))->assertForbidden();
    }

    public function test_admin_can_open_dashboard_users_and_logs(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.assinantes'))->assertOk();
        $this->actingAs($admin)->get(route('admin.logs'))->assertOk();
    }

    public function test_admin_can_approve_pending_user(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = User::factory()->pending()->create(['name' => 'Ana Radar']);

        Livewire::actingAs($admin)
            ->test(Subscribers::class)
            ->call('approve', $pending->id)
            ->assertHasNoErrors();

        $pending->refresh();
        $this->assertSame(SubscriptionStatus::ACTIVE, $pending->subscription_status);
        $this->assertSame('radar', $pending->plan);
        $this->assertNotNull($pending->approved_at);
        $this->assertTrue($pending->canReceiveAlerts());
        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'approved',
            'actor_id' => $admin->id,
            'subject_user_id' => $pending->id,
        ]);

        $this->actingAs($pending)->get(route('meus-lotes'))->assertOk();
    }

    public function test_admin_can_approve_trial_from_overview(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = User::factory()->pending()->create();

        Livewire::actingAs($admin)
            ->test(Overview::class)
            ->call('approveTrial', $pending->id);

        $pending->refresh();
        $this->assertSame(SubscriptionStatus::TRIAL, $pending->subscription_status);
        $this->assertSame('trial', $pending->plan);
        $this->assertTrue($pending->canReceiveAlerts());
    }

    public function test_admin_can_reject_pending_user(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = User::factory()->pending()->create();

        Livewire::actingAs($admin)
            ->test(Subscribers::class)
            ->call('reject', $pending->id);

        $pending->refresh();
        $this->assertSame(SubscriptionStatus::REJECTED, $pending->subscription_status);
        $this->assertFalse($pending->active);
        $this->assertNull($pending->approved_at);
        $this->assertFalse($pending->canReceiveAlerts());
    }

    public function test_pending_user_does_not_receive_alert_emails(): void
    {
        Mail::fake();

        $pending = User::factory()->pending()->create();
        $pending->alertPreference()->create(AlertPreference::defaults());
        Lot::factory()->create(['lote_id' => 'pending-skip-1', 'marca' => 'Toyota']);

        $result = app(AlertDispatcher::class)->dispatch();

        $this->assertSame(0, $result['emails']);
        Mail::assertNothingQueued();
    }

    public function test_admin_logs_page_renders_activity(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = User::factory()->pending()->create();

        Livewire::actingAs($admin)
            ->test(Subscribers::class)
            ->call('approve', $pending->id);

        Livewire::actingAs($admin)
            ->test(AdminLogs::class)
            ->assertSee('Aprovou');
    }
}
