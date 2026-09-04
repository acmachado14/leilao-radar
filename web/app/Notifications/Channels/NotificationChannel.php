<?php

namespace App\Notifications\Channels;

use App\Models\AlertPreference;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Support\Collection;

interface NotificationChannel
{
    public function name(): string;

    public function enabledFor(User $user, AlertPreference $preference): bool;

    /**
     * @param  Collection<int, Lot>  $lots
     */
    public function send(User $user, Collection $lots): void;
}
