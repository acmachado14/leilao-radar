<?php

namespace App\Support;

class TextNormalizer
{
    public static function fold(?string $value): string
    {
        $text = trim((string) $value);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($ascii === false) {
            $ascii = $text;
        }

        return mb_strtolower($ascii);
    }
}
