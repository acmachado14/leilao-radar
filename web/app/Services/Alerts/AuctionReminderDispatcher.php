<?php

namespace App\Services\Alerts;

use App\Constants\AlertSendKind;
use App\Constants\NotificationChannelName;
use App\Mail\AuctionReminderMail;
use App\Models\Lot;
use App\Models\LotAlertSend;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class AuctionReminderDispatcher
{
    public function __construct(
        private AlertDispatcher $alerts,
    ) {}

    /**
     * @return array{users: int, emails: int, skipped: int}
     */
    public function dispatch(?\Illuminate\Support\Carbon $now = null): array
    {
        $lots = Lot::query()
            ->get()
            ->filter(fn (Lot $lot) => $lot->isAuctionReminderDue($now))
            ->values();

        $emails = 0;
        $users = 0;
        $skipped = 0;

        if ($lots->isEmpty()) {
            return compact('users', 'emails', 'skipped');
        }

        User::query()
            ->with('alertPreferences')
            ->where('active', true)
            ->each(function (User $user) use ($lots, $now, &$emails, &$users, &$skipped): void {
                if (! $user->canReceiveAlerts()) {
                    $skipped++;

                    return;
                }

                $preferences = $user->alertPreferences;
                if ($preferences->isEmpty() || ! $preferences->contains(fn ($preference) => $preference->notify_email)) {
                    $skipped++;

                    return;
                }

                if (! filled($user->email)) {
                    $skipped++;

                    return;
                }

                $matched = $this->alerts->matchLots($lots, $preferences)
                    ->filter(fn (Lot $lot) => $lot->isAuctionReminderDue($now))
                    ->values();

                if ($matched->isEmpty()) {
                    $skipped++;

                    return;
                }

                $fresh = $this->unsents($user, $matched);
                if ($fresh->isEmpty()) {
                    $skipped++;

                    return;
                }

                $digest = $fresh->take((int) config('radar.digest_limit', 8));
                Mail::to($user->email)->queue(new AuctionReminderMail($user, $digest));
                $this->markSent($user, $digest);
                $emails++;
                $users++;
            });

        return compact('users', 'emails', 'skipped');
    }

    /**
     * @param  Collection<int, Lot>  $lots
     * @return Collection<int, Lot>
     */
    private function unsents(User $user, Collection $lots): Collection
    {
        $already = LotAlertSend::query()
            ->where('user_id', $user->id)
            ->where('channel', NotificationChannelName::EMAIL)
            ->where('kind', AlertSendKind::AUCTION_REMINDER)
            ->whereIn('lote_id', $lots->pluck('lote_id'))
            ->pluck('lote_id')
            ->all();

        return $lots->reject(fn (Lot $lot) => in_array($lot->lote_id, $already, true))->values();
    }

    /**
     * @param  Collection<int, Lot>  $lots
     */
    private function markSent(User $user, Collection $lots): void
    {
        foreach ($lots as $lot) {
            LotAlertSend::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'lote_id' => $lot->lote_id,
                    'channel' => NotificationChannelName::EMAIL,
                    'kind' => AlertSendKind::AUCTION_REMINDER,
                ],
                ['sent_at' => now()],
            );
        }
    }
}
