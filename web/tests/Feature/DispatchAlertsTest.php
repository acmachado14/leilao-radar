<?php

namespace Tests\Feature;

use App\Mail\LotMatchMail;
use App\Models\AlertPreference;
use App\Models\Lot;
use App\Models\User;
use App\Services\Alerts\AlertDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DispatchAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_queues_email_for_matching_active_subscriber(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $user->alertPreference()->create(AlertPreference::scoped());
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

    public function test_does_not_email_users_without_a_configured_recorte(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $user->alertPreference()->create(AlertPreference::defaults());
        Lot::factory()->create(['lote_id' => 'none-1']);

        $result = app(AlertDispatcher::class)->dispatch();

        $this->assertSame(0, $result['emails']);
        Mail::assertNothingQueued();
        $this->assertDatabaseMissing('lot_alert_sends', [
            'user_id' => $user->id,
            'lote_id' => 'none-1',
        ]);
    }

    public function test_does_not_email_users_with_no_recorte_at_all(): void
    {
        Mail::fake();

        User::factory()->create();
        Lot::factory()->create(['lote_id' => 'none-2']);

        $result = app(AlertDispatcher::class)->dispatch();

        $this->assertSame(0, $result['emails']);
        Mail::assertNothingQueued();
    }

    public function test_ignores_catch_all_when_user_also_has_a_real_recorte(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $user->alertPreferences()->create(AlertPreference::defaults());
        $user->alertPreferences()->create(array_merge(AlertPreference::defaults(), [
            'name' => 'Jetta',
            'search' => 'Jetta',
        ]));

        Lot::factory()->create([
            'lote_id' => 'jetta-1',
            'marca' => 'Volkswagen',
            'modelo' => 'Jetta GLI',
            'titulo' => 'Jetta GLI',
        ]);
        Lot::factory()->create([
            'lote_id' => 'corolla-1',
            'marca' => 'Toyota',
            'modelo' => 'Corolla Xei',
            'titulo' => 'Toyota Corolla Xei',
        ]);

        $result = app(AlertDispatcher::class)->dispatch();

        $this->assertSame(1, $result['emails']);
        Mail::assertQueued(LotMatchMail::class, function (LotMatchMail $mail) {
            $ids = $mail->lots->pluck('lote_id')->all();

            return $ids === ['jetta-1'];
        });
    }

    public function test_does_not_notify_paused_or_duplicate_lots(): void
    {
        Mail::fake();

        $paused = User::factory()->paused()->create();
        $paused->alertPreference()->create(AlertPreference::scoped());

        $user = User::factory()->create();
        $user->alertPreference()->create(AlertPreference::scoped());
        Lot::factory()->create(['lote_id' => 'dup-1']);

        app(AlertDispatcher::class)->dispatch();
        app(AlertDispatcher::class)->dispatch();

        Mail::assertQueued(LotMatchMail::class, 1);
    }

    public function test_artisan_command_imports_fixture_and_notifies(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-09-08 09:00:00', 'America/Sao_Paulo'));

        $user = User::factory()->create();
        $user->alertPreference()->create(AlertPreference::scoped());

        $this->artisan('radar:dispatch-alerts', [
            '--source' => base_path('tests/fixtures/lotes.json'),
        ])->assertSuccessful();

        $this->assertGreaterThan(0, Lot::query()->count());

        Mail::assertQueued(LotMatchMail::class, 1);
        Carbon::setTestNow();
    }

    public function test_unions_lots_from_multiple_preferences(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $user->alertPreferences()->create(array_merge(AlertPreference::defaults(), [
            'name' => 'Jetta',
            'search' => 'Jetta',
        ]));
        $user->alertPreferences()->create(array_merge(AlertPreference::defaults(), [
            'name' => 'Amarok',
            'search' => 'Amarok',
        ]));

        Lot::factory()->create([
            'lote_id' => 'jetta-1',
            'marca' => 'Volkswagen',
            'modelo' => 'Jetta GLI',
            'titulo' => 'Jetta GLI',
        ]);
        Lot::factory()->create([
            'lote_id' => 'amarok-1',
            'marca' => 'Volkswagen',
            'modelo' => 'Amarok',
            'titulo' => 'Amarok Highline',
        ]);

        $result = app(AlertDispatcher::class)->dispatch();

        $this->assertSame(1, $result['emails']);
        Mail::assertQueued(LotMatchMail::class, function (LotMatchMail $mail) {
            $ids = $mail->lots->pluck('lote_id')->all();

            return in_array('jetta-1', $ids, true) && in_array('amarok-1', $ids, true);
        });
    }
}
