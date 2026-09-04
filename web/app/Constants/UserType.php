<?php

namespace App\Constants;

class UserType
{
    public const USER = 'user';

    public const ADMIN = 'admin';

    public static function all(): array
    {
        return [
            self::USER,
            self::ADMIN,
        ];
    }
}
