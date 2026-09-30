<?php

namespace Tests\Feature;

use App\Constants\EntitlementSource;
use App\Constants\Plan;
use App\Constants\SubscriptionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueCatWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_purchase_activates_iap_plan(): void
    {
        $user = User::factory()->create([
            'plan' => Plan::TRIAL,
            'subscription_status' => SubscriptionStatus::TRIAL,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer rc-test-secret')
            ->postJson('/api/webhooks/revenuecat', [
                'event' => [
                    'type' => 'INITIAL_PURCHASE',
                    'app_user_id' => $user->id,
                    'product_id' => 'radar_pro_monthly',
                    'expiration_at_ms' => now()->addMonth()->getTimestampMs(),
                ],
            ]);

        $response->assertOk();
        $user->refresh();
        $this->assertSame(Plan::RADAR_PRO, $user->plan);
        $this->assertSame(SubscriptionStatus::ACTIVE, $user->subscription_status);
        $this->assertSame(EntitlementSource::IAP, $user->entitlement_source);
    }

    public function test_expiration_returns_the_user_to_the_free_tier(): void
    {
        $user = User::factory()->create([
            'plan' => Plan::RADAR_PRO,
            'subscription_status' => SubscriptionStatus::ACTIVE,
            'entitlement_source' => EntitlementSource::IAP,
            'subscription_until' => now()->addDay(),
        ]);

        $this->withHeader('Authorization', 'Bearer rc-test-secret')
            ->postJson('/api/webhooks/revenuecat', [
                'event' => [
                    'type' => 'EXPIRATION',
                    'app_user_id' => $user->id,
                    'product_id' => 'radar_pro_monthly',
                ],
            ])
            ->assertOk();

        $user->refresh();
        $this->assertSame(Plan::TRIAL, $user->plan);
        $this->assertSame(SubscriptionStatus::TRIAL, $user->subscription_status);
        $this->assertNull($user->subscription_until);
    }

    public function test_rejects_invalid_webhook_auth(): void
    {
        $this->postJson('/api/webhooks/revenuecat', ['event' => []])->assertUnauthorized();
    }
}
