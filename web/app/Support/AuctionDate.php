<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class AuctionDate
{
    public static function hasClockTime(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return false;
        }

        return preg_match('/\d{1,2}:\d{2}/', trim($value)) === 1;
    }

    public static function parse(?string $value, bool $dateOnlyEndOfDay = false): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $text = trim($value);
        $tz = 'America/Sao_Paulo';

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1) {
            $time = $dateOnlyEndOfDay ? '23:59:59' : '00:00:00';

            return Carbon::createFromFormat('Y-m-d H:i:s', $text.' '.$time, $tz);
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'd/m/Y H:i:s', 'd/m/Y', 'd/m/y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $text, $tz);
            } catch (\Throwable) {
                continue;
            }

            if ($date === false) {
                continue;
            }

            if (in_array($format, ['d/m/Y', 'd/m/y', 'Y-m-d'], true)) {
                $date->setTime($dateOnlyEndOfDay ? 23 : 0, $dateOnlyEndOfDay ? 59 : 0, $dateOnlyEndOfDay ? 59 : 0);
            }

            return $date;
        }

        try {
            return Carbon::parse($text, $tz);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function parseEnd(?string $value): ?Carbon
    {
        return self::parse($value, dateOnlyEndOfDay: true);
    }
}
