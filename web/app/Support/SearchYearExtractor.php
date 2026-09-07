<?php

namespace App\Support;

class SearchYearExtractor
{
    public const MIN_YEAR = 1980;

    public const MAX_YEAR = 2032;

    /**
     * Pull 4-digit vehicle years out of a free-text search and return a cleaned query.
     *
     * @return array{search: string, ano_min: ?int, ano_max: ?int}
     */
    public static function extract(string $search): array
    {
        $original = trim($search);
        if ($original === '') {
            return ['search' => '', 'ano_min' => null, 'ano_max' => null];
        }

        $years = [];
        $pattern = '/\b(19[89]\d|20[0-3]\d)\b/';
        $cleaned = preg_replace_callback($pattern, function (array $match) use (&$years): string {
            $year = (int) $match[1];
            if (! self::isVehicleYear($year)) {
                return $match[0];
            }

            $years[] = $year;

            return ' ';
        }, $original) ?? $original;

        $cleaned = trim((string) preg_replace('/[\s\/\-]+/', ' ', $cleaned));

        if ($years === []) {
            return ['search' => $original, 'ano_min' => null, 'ano_max' => null];
        }

        return [
            'search' => $cleaned,
            'ano_min' => min($years),
            'ano_max' => max($years),
        ];
    }

    public static function isVehicleYear(int $year): bool
    {
        return $year >= self::MIN_YEAR && $year <= self::MAX_YEAR;
    }
}
