<?php

namespace App\Constants;

class SubscriptionStatus
{
    public const TRIAL = 'trial';

    public const ACTIVE = 'active';

    public const PAUSED = 'paused';

    public const EXPIRED = 'expired';

    public static function all(): array
    {
        return [
            self::TRIAL,
            self::ACTIVE,
            self::PAUSED,
            self::EXPIRED,
        ];
    }
}
