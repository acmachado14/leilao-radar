<?php

namespace App\Constants;

class Plan
{
    public const TRIAL = 'trial';

    public const RADAR = 'radar';

    public const RADAR_PRO = 'radar_pro';

    public static function all(): array
    {
        return [
            self::TRIAL,
            self::RADAR,
            self::RADAR_PRO,
        ];
    }

    public static function paid(): array
    {
        return [
            self::RADAR,
            self::RADAR_PRO,
        ];
    }
}
