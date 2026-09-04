<?php

namespace Tests\Feature;

use App\Mail\LotMatchMail;
use App\Models\AlertPreference;
use App\Models\Lot;
use App\Models\User;
use App\Services\Alerts\AlertDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DispatchAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_queues_email_for_matching_active_subscriber(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $user->alertPreference()->create(AlertPreference::defaults());
        Lot::factory()->create(['lote_id' => 'abc-1', 'marca' => 'Toyota']);

        $result = app(AlertDispatcher::class)->dispatch();

        $this->assertSame(1, $result['emails']);
        Mail::assertQueued(LotMatchMail::class, 1);
        $this->assertDatabaseHas('lot_alert_sends', [
            'user_id' => $user->id,
            'lote_id' => 'abc-1',
            'channel' => 'email',
        ]);
    }

    public function test_does_not_notify_paused_or_duplicate_lots(): void
    {
        Mail::fake();

        $paused = User::factory()->paused()->create();
        $paused->alertPreference()->create(AlertPreference::defaults());

        $user = User::factory()->create();
        $user->alertPreference()->create(AlertPreference::defaults());
        Lot::factory()->create(['lote_id' => 'dup-1']);

        app(AlertDispatcher::class)->dispatch();
        app(AlertDispatcher::class)->dispatch();

        Mail::assertQueued(LotMatchMail::class, 1);
    }

    public function test_artisan_command_imports_fixture_and_notifies(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $user->alertPreference()->create(AlertPreference::defaults());

        $this->artisan('radar:dispatch-alerts', [
            '--source' => base_path('tests/fixtures/lotes.json'),
        ])->assertSuccessful();

        $this->assertGreaterThan(0, \App\Models\Lot::query()->count());

        Mail::assertQueued(LotMatchMail::class, 1);
    }
}
