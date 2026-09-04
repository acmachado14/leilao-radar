<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class AuctionDate
{
    public static function parseEnd(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $text = trim($value);
        $tz = 'America/Sao_Paulo';

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1) {
            return Carbon::createFromFormat('Y-m-d H:i:s', $text.' 23:59:59', $tz);
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
                $date->setTime(23, 59, 59);
            }

            return $date;
        }

        try {
            return Carbon::parse($text, $tz);
        } catch (\Throwable) {
            return null;
        }
    }
}
