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

    /**
     * Match a folded token against a folded haystack using alphanumeric boundaries.
     * "gol" matches "vw gol 1.0" but not "golf".
     */
    public static function containsToken(string $haystack, string $token): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        $quoted = preg_quote($token, '/');

        return (bool) preg_match('/(?<![a-z0-9])'.$quoted.'(?![a-z0-9])/u', $haystack);
    }
}
