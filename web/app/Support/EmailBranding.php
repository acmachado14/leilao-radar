<?php

namespace App\Support;

class EmailBranding
{
    public static function logoUrl(): string
    {
        return url('/images/logo.png');
    }

    public static function searchIconUrl(): string
    {
        return url('/images/brand/search.png');
    }
}
