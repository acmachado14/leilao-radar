<?php

namespace Tests\Feature;

use App\Constants\AlertSendKind;
use App\Mail\AuctionReminderMail;
use App\Mail\LotMatchMail;
use App\Models\AlertPreference;
use App\Models\Lot;
use App\Models\User;
use App\Services\Alerts\AlertDispatcher;
use App\Services\Alerts\AuctionReminderDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DispatchAuctionRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-05 08:35:00', 'America/Sao_Paulo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_emails_one_hour_before_auction_start(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $user->alertPreference()->create(AlertPreference::defaults());
        Lot::factory()->create([
            'lote_id' => 'soon-1',
            'titulo' => 'Toyota Corolla Xei',
            'leilao_em' => '2026-09-05 09:30:00',
            'leilao_fim' => '2026-09-05 09:45:00',
        ]);
        $user->lotInterests()->create(['lote_id' => 'soon-1']);

        $result = app(AuctionReminderDispatcher::class)->dispatch();

        $this->assertSame(1, $result['emails']);
        Mail::assertQueued(AuctionReminderMail::class, function (AuctionReminderMail $mail) {
            return $mail->lots->pluck('lote_id')->all() === ['soon-1']
                && str_contains($mail->envelope()->subject, 'Leilão em 1 hora');
        });
        $this->assertDatabaseHas('lot_alert_sends', [
            'user_id' => $user->id,
            'lote_id' => 'soon-1',
            'channel' => 'email',
            'kind' => AlertSendKind::AUCTION_REMINDER,
        ]);
    }

    public function test_does_not_email_two_hours_before_or_after_start(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $user->alertPreference()->create(AlertPreference::defaults());
        Lot::factory()->create([
            'lote_id' => 'later-1',
            'leilao_em' => '2026-09-05 09:30:00',
            'leilao_fim' => '2026-09-05 09:45:00',
        ]);
        $user->lotInterests()->create(['lote_id' => 'later-1']);

        Carbon::setTestNow(Carbon::parse('2026-09-05 07:20:00', 'America/Sao_Paulo'));
        $this->assertSame(0, app(AuctionReminderDispatcher::class)->dispatch()['emails']);

        Carbon::setTestNow(Carbon::parse('2026-09-05 09:31:00', 'America/Sao_Paulo'));
        $this->assertSame(0, app(AuctionReminderDispatcher::class)->dispatch()['emails']);
        Mail::assertNothingQueued();
    }

    public function test_date_only_lots_notify_on_auction_day(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $user->alertPreference()->create(AlertPreference::defaults());
        Lot::factory()->create([
            'lote_id' => 'day-1',
            'titulo' => 'Honda Cg 160',
            'leilao_em' => '2026-09-05',
            'leilao_fim' => '2026-09-05',
        ]);
        $user->lotInterests()->create(['lote_id' => 'day-1']);

        Carbon::setTestNow(Carbon::parse('2026-09-04 11:10:00', 'America/Sao_Paulo'));
        $this->assertSame(0, app(AuctionReminderDispatcher::class)->dispatch()['emails']);

        Carbon::setTestNow(Carbon::parse('2026-09-05 11:10:00', 'America/Sao_Paulo'));
        $this->assertSame(1, app(AuctionReminderDispatcher::class)->dispatch()['emails']);
        Mail::assertQueued(AuctionReminderMail::class, function (AuctionReminderMail $mail) {
            return str_contains($mail->envelope()->subject, 'Leilão hoje');
        });
    }

    public function test_match_digest_does_not_block_reminder(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $user->alertPreference()->create(AlertPreference::defaults());
        Lot::factory()->create([
            'lote_id' => 'both-1',
            'leilao_em' => '2026-09-05 09:30:00',
            'leilao_fim' => '2026-09-05 09:45:00',
        ]);
        $user->lotInterests()->create(['lote_id' => 'both-1']);

        app(AlertDispatcher::class)->dispatch();
        app(AuctionReminderDispatcher::class)->dispatch();

        Mail::assertQueued(LotMatchMail::class, 1);
        Mail::assertQueued(AuctionReminderMail::class, 1);
    }

    public function test_does_not_resend_the_same_reminder(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $user->alertPreference()->create(AlertPreference::defaults());
        Lot::factory()->create([
            'lote_id' => 'once-1',
            'leilao_em' => '2026-09-05 09:30:00',
            'leilao_fim' => '2026-09-05 09:45:00',
        ]);
        $user->lotInterests()->create(['lote_id' => 'once-1']);

        app(AuctionReminderDispatcher::class)->dispatch();
        app(AuctionReminderDispatcher::class)->dispatch();

        Mail::assertQueued(AuctionReminderMail::class, 1);
    }

    public function test_does_not_remind_faixa_matches_without_interest(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $user->alertPreference()->create(AlertPreference::defaults());
        Lot::factory()->create([
            'lote_id' => 'faixa-1',
            'leilao_em' => '2026-09-05 09:30:00',
            'leilao_fim' => '2026-09-05 09:45:00',
        ]);

        $this->assertSame(0, app(AuctionReminderDispatcher::class)->dispatch()['emails']);
        Mail::assertNothingQueued();
    }

    public function test_artisan_command_runs(): void
    {
        Mail::fake();

        $this->artisan('radar:dispatch-auction-reminders')->assertSuccessful();
    }
}
