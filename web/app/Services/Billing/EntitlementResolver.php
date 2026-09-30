<?php

namespace App\Services\Billing;

use App\Constants\EntitlementSource;
use App\Constants\Plan;
use App\Constants\SubscriptionStatus;
use App\Models\User;

class EntitlementResolver
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(User $user): array
    {
        $quota = app(PlanQuota::class)->snapshot($user);
        unset($quota['checkout_url']);

        $source = $this->source($user);

        return [
            ...$quota,
            'source' => $source,
            'can_show_paywall' => $this->canShowPaywall($user),
            'has_live_subscription' => $user->hasLiveSubscription(),
            'status_label' => $this->statusLabel($user),
        ];
    }

    public function source(User $user): string
    {
        if (! $user->hasLiveSubscription()) {
            return EntitlementSource::NONE;
        }

        if ($this->hasPaidAccess($user)) {
            if ($user->entitlement_source === EntitlementSource::IAP || filled($user->revenuecat_app_user_id)) {
                return EntitlementSource::IAP;
            }

            return EntitlementSource::WEB;
        }

        if ($user->subscription_status === SubscriptionStatus::TRIAL || $user->plan === Plan::TRIAL) {
            return EntitlementSource::TRIAL;
        }

        if ($user->entitlement_source === EntitlementSource::IAP || filled($user->revenuecat_app_user_id)) {
            return EntitlementSource::IAP;
        }

        return EntitlementSource::WEB;
    }

    public function canShowPaywall(User $user): bool
    {
        if ($user->isAdmin()) {
            return false;
        }

        return ! $this->hasPaidAccess($user);
    }

    public function hasPaidAccess(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->hasLiveSubscription()) {
            return false;
        }

        return in_array($user->plan, Plan::paid(), true);
    }

    public function statusLabel(User $user): string
    {
        if ($this->hasPaidAccess($user)) {
            return $user->subscription_status === SubscriptionStatus::TRIAL
                ? 'Ativa'
                : $user->subscriptionLabel();
        }

        return 'Conta grátis';
    }
}
