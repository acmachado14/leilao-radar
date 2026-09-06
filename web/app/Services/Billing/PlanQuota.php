<?php

namespace App\Services\Billing;

use App\Constants\Plan;
use App\Models\AiUsageLog;
use App\Models\User;
use App\Support\SalesWhatsApp;
use Illuminate\Support\Carbon;

class PlanQuota
{
    /**
     * @return array<string, mixed>
     */
    public function definition(?string $plan): array
    {
        $key = $this->normalizePlan($plan);
        $plans = config('radar.plans', []);

        return is_array($plans[$key] ?? null) ? $plans[$key] : $plans[Plan::TRIAL];
    }

    public function normalizePlan(?string $plan): string
    {
        $key = $plan ?: Plan::TRIAL;

        return in_array($key, Plan::all(), true) ? $key : Plan::TRIAL;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function publicPlans(): array
    {
        $plans = [];
        foreach (Plan::all() as $key) {
            $definition = $this->definition($key);
            $plans[] = [
                'key' => $key,
                ...$definition,
                'checkout_url' => SalesWhatsApp::checkoutUrl($key),
            ];
        }

        return $plans;
    }

    public function analysesLimit(User $user): ?int
    {
        if ($user->isAdmin()) {
            return null;
        }

        $limit = data_get($this->definition($user->plan), 'analyses_per_month');

        return $limit === null ? null : (int) $limit;
    }

    public function alertsLimit(User $user): int
    {
        if ($user->isAdmin()) {
            return (int) config('radar.max_preferences', 12);
        }

        return (int) data_get($this->definition($user->plan), 'alerts', config('radar.max_preferences', 12));
    }

    public function periodStart(?Carbon $now = null): Carbon
    {
        $reference = $now?->copy()->timezone('America/Sao_Paulo') ?? now('America/Sao_Paulo');

        return $reference->copy()->startOfMonth()->timezone(config('app.timezone', 'UTC'));
    }

    public function billedCount(User $user, ?Carbon $now = null): int
    {
        return $user->aiUsageLogs()
            ->where('billed', true)
            ->where('created_at', '>=', $this->periodStart($now))
            ->count();
    }

    public function spentBrl(User $user): float
    {
        return round((float) $user->aiUsageLogs()->sum('estimated_cost_brl'), 4);
    }

    public function spentBrlThisMonth(User $user, ?Carbon $now = null): float
    {
        return round((float) $user->aiUsageLogs()
            ->where('created_at', '>=', $this->periodStart($now))
            ->sum('estimated_cost_brl'), 4);
    }

    public function alreadyBilledThisPeriod(User $user, string $loteId, ?Carbon $now = null): bool
    {
        return $user->aiUsageLogs()
            ->where('billed', true)
            ->where('lote_id', $loteId)
            ->where('created_at', '>=', $this->periodStart($now))
            ->exists();
    }

    public function canConsult(User $user, string $loteId, ?Carbon $now = null): bool
    {
        if (! $user->isAdmin() && ! $user->hasLiveSubscription()) {
            return false;
        }

        if ($this->alreadyBilledThisPeriod($user, $loteId, $now)) {
            return true;
        }

        $limit = $this->analysesLimit($user);
        if ($limit === null) {
            return true;
        }

        return $this->billedCount($user, $now) < $limit;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(User $user, ?Carbon $now = null): array
    {
        $limit = $this->analysesLimit($user);
        $used = $this->billedCount($user, $now);
        $remaining = $limit === null ? null : max(0, $limit - $used);
        $plan = $this->normalizePlan($user->plan);
        $definition = $this->definition($plan);

        return [
            'plan' => $plan,
            'plan_name' => $definition['name'] ?? $plan,
            'used' => $used,
            'limit' => $limit,
            'remaining' => $remaining,
            'unlimited' => $limit === null,
            'spent_brl' => $this->spentBrl($user),
            'spent_brl_month' => $this->spentBrlThisMonth($user, $now),
            'alerts_used' => $user->alertPreferences()->count(),
            'alerts_limit' => $this->alertsLimit($user),
            'checkout_url' => SalesWhatsApp::checkoutUrl($this->suggestedUpgrade($plan), $user),
            'upgrade_plan' => $this->suggestedUpgrade($plan),
        ];
    }

    public function record(User $user, string $loteId, string $source, bool $apiCall): AiUsageLog
    {
        $alreadyBilled = $this->alreadyBilledThisPeriod($user, $loteId);

        return AiUsageLog::query()->create([
            'user_id' => $user->id,
            'lote_id' => $loteId,
            'source' => $apiCall ? 'api' : $source,
            'billed' => ! $alreadyBilled,
            'estimated_cost_brl' => 0,
            'image_count' => null,
        ]);
    }

    public function markApiCost(AiUsageLog $log, int $imageCount): void
    {
        $log->update([
            'source' => 'api',
            'estimated_cost_brl' => (float) config('radar.gemini.estimated_cost_brl', 0.18),
            'image_count' => $imageCount,
        ]);
    }

    public function suggestedUpgrade(string $plan): string
    {
        return Plan::RADAR_PRO;
    }
}
