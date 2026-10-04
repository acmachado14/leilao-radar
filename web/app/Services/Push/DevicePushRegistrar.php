<?php

namespace App\Services\Push;

use App\Models\DevicePushToken;
use App\Models\User;

class DevicePushRegistrar
{
    public function register(User $user, string $token, string $platform): bool
    {
        $token = trim($token);
        if ($token === '' || $platform !== DevicePushToken::PLATFORM_IOS) {
            return false;
        }

        DevicePushToken::query()->updateOrCreate(
            ['token' => $token],
            [
                'user_id' => $user->id,
                'platform' => DevicePushToken::PLATFORM_IOS,
            ],
        );

        return true;
    }

    public function unregister(User $user, string $token): void
    {
        $token = trim($token);
        if ($token === '') {
            return;
        }

        DevicePushToken::query()
            ->where('user_id', $user->id)
            ->where('token', $token)
            ->delete();
    }
}
