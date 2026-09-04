<?php

namespace App\Notifications\Channels;

use App\Constants\NotificationChannelName;
use App\Mail\LotMatchMail;
use App\Models\AlertPreference;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class EmailChannel implements NotificationChannel
{
    public function name(): string
    {
        return NotificationChannelName::EMAIL;
    }

    public function enabledFor(User $user, AlertPreference $preference): bool
    {
        return $preference->notify_email && filled($user->email);
    }

    public function send(User $user, Collection $lots): void
    {
        Mail::to($user->email)->queue(new LotMatchMail($user, $lots));
    }
}
