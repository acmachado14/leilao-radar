<?php

use App\Constants\Plan;
use App\Constants\SubscriptionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $days = (int) config('radar.trial_days', 7);

        DB::table('users')
            ->whereNull('approved_at')
            ->where('subscription_status', SubscriptionStatus::PENDING)
            ->where('active', true)
            ->update([
                'subscription_status' => SubscriptionStatus::TRIAL,
                'plan' => Plan::TRIAL,
                'subscription_until' => now()->addDays($days),
                'approved_at' => now(),
                'rejected_at' => null,
            ]);
    }

    public function down(): void
    {
        // Trial access granted to waiting signups cannot be safely reversed.
    }
};
