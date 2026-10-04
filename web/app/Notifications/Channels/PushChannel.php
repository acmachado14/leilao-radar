<?php

namespace App\Notifications\Channels;

use App\Constants\AlertSendKind;
use App\Constants\NotificationChannelName;
use App\Models\AlertPreference;
use App\Models\DevicePushToken;
use App\Models\User;
use App\Services\Push\ExpoPushClient;
use App\Services\Push\PushMessageFactory;
use Illuminate\Support\Collection;

class PushChannel implements NotificationChannel
{
    public function __construct(
        private ExpoPushClient $expo,
        private PushMessageFactory $messages,
    ) {}

    public function name(): string
    {
        return NotificationChannelName::PUSH;
    }

    public function enabledFor(User $user, AlertPreference $preference): bool
    {
        if (! $preference->isConfigured()) {
            return false;
        }

        return $user->devicePushTokens()
            ->where('platform', DevicePushToken::PLATFORM_IOS)
            ->exists();
    }

    public function send(User $user, Collection $lots): void
    {
        $tokens = $user->devicePushTokens()
            ->where('platform', DevicePushToken::PLATFORM_IOS)
            ->pluck('token');

        if ($tokens->isEmpty()) {
            return;
        }

        $payload = $this->messages->forLots($lots, AlertSendKind::MATCH);
        $this->expo->sendToTokens($tokens, $payload['title'], $payload['body'], $payload['data']);
    }
}
