<?php

namespace App\Services\Push;

use App\Constants\AlertSendKind;
use App\Models\DevicePushToken;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Support\Collection;

class UserPushNotifier
{
    public function __construct(
        private ExpoPushClient $expo,
        private PushMessageFactory $messages,
    ) {}

    /**
     * @param  Collection<int, Lot>  $lots
     */
    public function send(User $user, Collection $lots, string $kind = AlertSendKind::MATCH): void
    {
        $tokens = $user->devicePushTokens()
            ->where('platform', DevicePushToken::PLATFORM_IOS)
            ->pluck('token');

        if ($tokens->isEmpty()) {
            return;
        }

        $payload = $this->messages->forLots($lots, $kind);
        $this->expo->sendToTokens($tokens, $payload['title'], $payload['body'], $payload['data']);
    }
}
