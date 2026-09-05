<?php

namespace App\Constants;

class SubscriptionStatus
{
    public const PENDING = 'pending';

    public const TRIAL = 'trial';

    public const ACTIVE = 'active';

    public const PAUSED = 'paused';

    public const EXPIRED = 'expired';

    public const REJECTED = 'rejected';

    public static function all(): array
    {
        return [
            self::PENDING,
            self::TRIAL,
            self::ACTIVE,
            self::PAUSED,
            self::EXPIRED,
            self::REJECTED,
        ];
    }
}
