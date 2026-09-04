<?php

namespace App\Services\Alerts;

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
            ->with('alertPreference')
            ->where('active', true)
            ->each(function (User $user) use ($lots, &$emails, &$users, &$skipped): void {
                if (! $user->canReceiveAlerts()) {
                    $skipped++;

                    return;
                }

                $preference = $user->alertPreference;
                if ($preference === null) {
                    $skipped++;

                    return;
                }

                $matched = $lots
                    ->filter(fn (Lot $lot) => $this->matcher->matches($lot, $preference))
                    ->sortByDesc(fn (Lot $lot) => $lot->relevance_score ?? 0)
                    ->values();

                if ($matched->isEmpty()) {
                    $skipped++;

                    return;
                }

                $sentAny = false;
                foreach ($this->channels as $channel) {
                    if (! $channel->enabledFor($user, $preference)) {
                        continue;
                    }

                    $fresh = $this->unsents($user, $matched, $channel->name());
                    if ($fresh->isEmpty()) {
                        continue;
                    }

                    $digest = $fresh->take((int) config('radar.digest_limit', 8));
                    $channel->send($user, $digest);
                    $this->markSent($user, $digest, $channel->name());
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
     * @param  Collection<int, Lot>  $lots
     * @return Collection<int, Lot>
     */
    private function unsents(User $user, Collection $lots, string $channel): Collection
    {
        $already = LotAlertSend::query()
            ->where('user_id', $user->id)
            ->where('channel', $channel)
            ->whereIn('lote_id', $lots->pluck('lote_id'))
            ->pluck('lote_id')
            ->all();

        return $lots->reject(fn (Lot $lot) => in_array($lot->lote_id, $already, true))->values();
    }

    /**
     * @param  Collection<int, Lot>  $lots
     */
    private function markSent(User $user, Collection $lots, string $channel): void
    {
        foreach ($lots as $lot) {
            LotAlertSend::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'lote_id' => $lot->lote_id,
                    'channel' => $channel,
                ],
                ['sent_at' => now()],
            );
        }
    }
}
