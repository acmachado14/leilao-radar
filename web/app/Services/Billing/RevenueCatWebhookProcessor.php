<?php

namespace App\Services\Billing;

use App\Constants\EntitlementSource;
use App\Constants\Plan;
use App\Constants\SubscriptionStatus;
use App\Models\User;
use Illuminate\Support\Carbon;

class RevenueCatWebhookProcessor
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $event = is_array($payload['event'] ?? null) ? $payload['event'] : $payload;
        $type = strtoupper((string) ($event['type'] ?? ''));
        $appUserId = (string) ($event['app_user_id'] ?? '');
        if ($appUserId === '') {
            return;
        }

        $user = User::query()
            ->where('id', $appUserId)
            ->orWhere('revenuecat_app_user_id', $appUserId)
            ->first();

        if ($user === null) {
            return;
        }

        $productId = (string) ($event['product_id'] ?? $event['new_product_id'] ?? '');
        $plan = $this->planForProduct($productId) ?? Plan::RADAR;
        $expiration = $this->expiration($event);

        if (in_array($type, ['INITIAL_PURCHASE', 'RENEWAL', 'PRODUCT_CHANGE', 'UNCANCELLATION', 'NON_RENEWING_PURCHASE'], true)) {
            $user->forceFill([
                'plan' => $plan,
                'subscription_status' => SubscriptionStatus::ACTIVE,
                'entitlement_source' => EntitlementSource::IAP,
                'revenuecat_app_user_id' => $appUserId,
                'subscription_until' => $expiration,
                'approved_at' => $user->approved_at ?? now(),
                'active' => true,
            ])->save();

            return;
        }

        if (in_array($type, ['EXPIRATION', 'REFUND'], true)) {
            $user->forceFill([
                'plan' => Plan::TRIAL,
                'subscription_status' => SubscriptionStatus::TRIAL,
                'entitlement_source' => EntitlementSource::TRIAL,
                'subscription_until' => null,
            ])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function expiration(array $event): ?Carbon
    {
        $ms = $event['expiration_at_ms'] ?? null;
        if (! is_numeric($ms)) {
            return now()->addMonth();
        }

        return Carbon::createFromTimestampMs((int) $ms);
    }

    private function planForProduct(string $productId): ?string
    {
        $map = config('radar.revenuecat.products', []);

        return is_string($map[$productId] ?? null) ? $map[$productId] : null;
    }
}
