<?php

namespace App\Services\Alerts;

use App\Constants\AlertSendKind;
use App\Models\AlertPreference;
use App\Models\Lot;
use App\Models\LotAlertSend;
use App\Models\User;
use App\Notifications\Channels\NotificationChannel;
use Illuminate\Support\Collection;

class AlertDispatcher
{
    /**
     * @param  iterable<NotificationChannel>  $channels
     */
    public function __construct(
        private LotMatcher $matcher,
        private iterable $channels,
    ) {}

    /**
     * @return array{users: int, emails: int, skipped: int}
     */
    public function dispatch(): array
    {
        $lots = Lot::query()->get()->filter(fn (Lot $lot) => $lot->isUpcoming())->values();
        $emails = 0;
        $users = 0;
        $skipped = 0;

        User::query()
            ->with('alertPreferences')
            ->where('active', true)
            ->each(function (User $user) use ($lots, &$emails, &$users, &$skipped): void {
                if (! $user->canReceiveAlerts()) {
                    $skipped++;

                    return;
                }

                $preferences = $user->alertPreferences;
                if ($preferences->isEmpty()) {
                    $skipped++;

                    return;
                }

                $matched = $this->matchLots($lots, $preferences);

                if ($matched->isEmpty()) {
                    $skipped++;

                    return;
                }

                $sentAny = false;
                foreach ($this->channels as $channel) {
                    $preference = $this->channelPreference($user, $preferences, $channel);
                    if ($preference === null) {
                        continue;
                    }

                    $fresh = $this->unsents($user, $matched, $channel->name(), AlertSendKind::MATCH);
                    if ($fresh->isEmpty()) {
                        continue;
                    }

                    $digest = $fresh->take((int) config('radar.digest_limit', 8));
                    $channel->send($user, $digest);
                    $this->markSent($user, $digest, $channel->name(), AlertSendKind::MATCH);
                    $sentAny = true;
                    if ($channel->name() === 'email') {
                        $emails++;
                    }
                }

                if ($sentAny) {
                    $users++;
                } else {
                    $skipped++;
                }
            });

        return compact('users', 'emails', 'skipped');
    }

    /**
     * @param  Collection<int, Lot>  $lots
     * @return Collection<int, Lot>
     */
    public function preview(User $user, AlertPreference $preference, int $limit = 24): Collection
    {
        return Lot::query()
            ->get()
            ->filter(fn (Lot $lot) => $this->matcher->matches($lot, $preference))
            ->sortByDesc(fn (Lot $lot) => $lot->relevance_score ?? 0)
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, Lot>
     */
    public function previewForUser(User $user, int $limit = 24): Collection
    {
        $preferences = $user->alertPreferences;
        if ($preferences->isEmpty()) {
            return collect();
        }

        return $this->matchLots(
            Lot::query()->get()->filter(fn (Lot $lot) => $lot->isUpcoming())->values(),
            $preferences,
        )->take($limit)->values();
    }

    /**
     * @param  Collection<int, Lot>  $lots
     * @param  Collection<int, AlertPreference>  $preferences
     * @return Collection<int, Lot>
     */
    public function matchLots(Collection $lots, Collection $preferences): Collection
    {
        return $lots
            ->filter(function (Lot $lot) use ($preferences): bool {
                foreach ($preferences as $preference) {
                    if ($this->matcher->matches($lot, $preference)) {
                        return true;
                    }
                }

                return false;
            })
            ->unique('lote_id')
            ->sortByDesc(fn (Lot $lot) => $lot->relevance_score ?? 0)
            ->values();
    }

    /**
     * @param  Collection<int, AlertPreference>  $preferences
     */
    private function channelPreference(User $user, Collection $preferences, NotificationChannel $channel): ?AlertPreference
    {
        foreach ($preferences as $preference) {
            if ($channel->enabledFor($user, $preference)) {
                return $preference;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, Lot>  $lots
     * @return Collection<int, Lot>
     */
    private function unsents(User $user, Collection $lots, string $channel, string $kind = AlertSendKind::MATCH): Collection
    {
        $already = LotAlertSend::query()
            ->where('user_id', $user->id)
            ->where('channel', $channel)
            ->where('kind', $kind)
            ->whereIn('lote_id', $lots->pluck('lote_id'))
            ->pluck('lote_id')
            ->all();

        return $lots->reject(fn (Lot $lot) => in_array($lot->lote_id, $already, true))->values();
    }

    /**
     * @param  Collection<int, Lot>  $lots
     */
    private function markSent(User $user, Collection $lots, string $channel, string $kind = AlertSendKind::MATCH): void
    {
        foreach ($lots as $lot) {
            LotAlertSend::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'lote_id' => $lot->lote_id,
                    'channel' => $channel,
                    'kind' => $kind,
                ],
                ['sent_at' => now()],
            );
        }
    }
}
