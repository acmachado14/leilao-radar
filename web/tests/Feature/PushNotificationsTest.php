<?php

namespace Tests\Feature;

use App\Constants\AlertSendKind;
use App\Constants\NotificationChannelName;
use App\Models\AlertPreference;
use App\Models\DevicePushToken;
use App\Models\Lot;
use App\Models\User;
use App\Services\Alerts\AlertDispatcher;
use App\Services\Alerts\AuctionReminderDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PushNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_alerts_sends_expo_push_for_ios_token(): void
    {
        Mail::fake();
        Http::fake([
            'exp.host/*' => Http::response(['data' => [['status' => 'ok']]], 200),
        ]);

        $user = User::factory()->create();
        $user->alertPreference()->create(AlertPreference::scoped());
        DevicePushToken::query()->create([
            'user_id' => $user->id,
            'token' => 'ExponentPushToken[test-ios]',
            'platform' => DevicePushToken::PLATFORM_IOS,
        ]);
        Lot::factory()->create(['lote_id' => 'push-1', 'marca' => 'Toyota', 'modelo' => 'Corolla']);

        app(AlertDispatcher::class)->dispatch();

        Http::assertSent(function ($request) {
            $body = $request->data();
            $first = is_array($body) ? ($body[0] ?? $body) : [];

            return str_contains((string) ($first['to'] ?? ''), 'ExponentPushToken[test-ios]')
                && str_contains((string) ($first['title'] ?? ''), 'recorte');
        });

        $this->assertDatabaseHas('lot_alert_sends', [
            'user_id' => $user->id,
            'lote_id' => 'push-1',
            'channel' => NotificationChannelName::PUSH,
            'kind' => AlertSendKind::MATCH,
        ]);
    }

    public function test_android_push_registration_is_ignored(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('ios')->plainTextToken;

        $this->withToken($token)
            ->postJson('/bff/v1/actions/register_push', [
                'expo_push_token' => 'ExponentPushToken[android]',
                'platform' => 'android',
            ])
            ->assertOk();

        $this->assertDatabaseMissing('device_push_tokens', [
            'user_id' => $user->id,
        ]);
    }

    public function test_ios_push_registration_stores_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('ios')->plainTextToken;

        $this->withToken($token)
            ->postJson('/bff/v1/actions/register_push', [
                'expo_push_token' => 'ExponentPushToken[iphone]',
                'platform' => 'ios',
            ])
            ->assertOk();

        $this->assertDatabaseHas('device_push_tokens', [
            'user_id' => $user->id,
            'token' => 'ExponentPushToken[iphone]',
            'platform' => DevicePushToken::PLATFORM_IOS,
        ]);
    }

    public function test_auction_reminder_sends_push_independently_of_email_dedupe(): void
    {
        Mail::fake();
        Http::fake([
            'exp.host/*' => Http::response(['data' => [['status' => 'ok']]], 200),
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-05 08:35:00', 'America/Sao_Paulo'));

        $user = User::factory()->create();
        $user->alertPreference()->create(AlertPreference::scoped());
        DevicePushToken::query()->create([
            'user_id' => $user->id,
            'token' => 'ExponentPushToken[reminder]',
            'platform' => DevicePushToken::PLATFORM_IOS,
        ]);

        Lot::factory()->create([
            'lote_id' => 'rem-1',
            'titulo' => 'Honda Civic',
            'leilao_em' => '2026-09-05 09:30:00',
            'leilao_fim' => '2026-09-05 09:45:00',
        ]);
        $user->lotInterests()->create(['lote_id' => 'rem-1']);

        $result = app(AuctionReminderDispatcher::class)->dispatch();

        $this->assertSame(1, $result['emails']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'exp.host'));

        $this->assertDatabaseHas('lot_alert_sends', [
            'user_id' => $user->id,
            'lote_id' => 'rem-1',
            'channel' => NotificationChannelName::PUSH,
            'kind' => AlertSendKind::AUCTION_REMINDER,
        ]);

        Carbon::setTestNow();
    }
}
