<?php

namespace App\Support;

use App\Models\Lot;
use Illuminate\Support\Collection;

class PatioRegion
{
    /**
     * City extracted from a yard address. Yards in the same city share one key
     * (Guarulhos I and Guarulhos III, or "Juatuba" and "Juatuba-MG").
     *
     * @return array{key: string, city: string, uf: ?string}|null
     */
    public static function parse(?string $patio): ?array
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', (string) $patio));
        if ($normalized === '') {
            return null;
        }

        if (TextNormalizer::fold($normalized) === 'outros locais') {
            return [
                'key' => 'outros-locais',
                'city' => 'Outros locais',
                'uf' => null,
            ];
        }

        if (preg_match('/^(?<body>.+?)(?:\s+(?<yard>[ivx]{1,4}))?\/(?<uf>[a-z]{2})$/iu', $normalized, $matches) === 1) {
            $city = self::title($matches['body']);

            return [
                'key' => self::key($city),
                'city' => $city,
                'uf' => strtoupper($matches['uf']),
            ];
        }

        $segment = $normalized;
        if (preg_match('/\s-\s/', $segment) === 1) {
            $parts = preg_split('/\s+-\s+/', $segment) ?: [$segment];
            $segment = trim((string) end($parts));
        }

        if (preg_match('/^(?<body>.+)-(?<uf>[A-Za-z]{2})$/u', $segment, $matches) === 1) {
            $city = self::cityFromTail($matches['body']);
            if ($city === '') {
                return null;
            }

            return [
                'key' => self::key($city),
                'city' => $city,
                'uf' => strtoupper($matches['uf']),
            ];
        }

        if (! preg_match('/\d/', $normalized) && mb_strlen($normalized) <= 40) {
            $city = self::title($normalized);

            return [
                'key' => self::key($city),
                'city' => $city,
                'uf' => null,
            ];
        }

        $city = self::cityFromTail($normalized);
        if ($city === '') {
            return null;
        }

        return [
            'key' => self::key($city),
            'city' => $city,
            'uf' => null,
        ];
    }

    public static function key(string $city): string
    {
        $folded = str_replace(['~', '^', '`', "'", '"'], '', TextNormalizer::fold($city));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $folded) ?? $folded;

        return trim((string) $slug, '-');
    }

    public static function label(string $city, ?string $uf): string
    {
        if ($uf === null || $uf === '') {
            return $city;
        }

        return $city.' · '.$uf;
    }

    public static function labelFromKey(string $key): string
    {
        if ($key === 'outros-locais') {
            return 'Outros locais';
        }

        return self::title(str_replace('-', ' ', $key));
    }

    /**
     * Distinct cities present on the given lots, busiest first.
     * "Outros locais" stays at the end.
     *
     * @param  Collection<int, Lot>  $lots
     * @return list<array{key: string, label: string, count: int}>
     */
    public static function summarize(Collection $lots): array
    {
        /** @var array<string, array{key: string, city: string, uf: ?string, count: int}> $buckets */
        $buckets = [];

        foreach ($lots as $lot) {
            $region = self::parse($lot->patio);
            if ($region === null) {
                continue;
            }

            $key = $region['key'];
            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'key' => $key,
                    'city' => $region['city'],
                    'uf' => $region['uf'],
                    'count' => 0,
                ];
            } elseif ($buckets[$key]['uf'] === null && $region['uf'] !== null) {
                $buckets[$key]['uf'] = $region['uf'];
            }

            $buckets[$key]['count']++;
        }

        $rows = array_values($buckets);
        usort($rows, function (array $left, array $right): int {
            $leftOther = $left['key'] === 'outros-locais';
            $rightOther = $right['key'] === 'outros-locais';
            if ($leftOther !== $rightOther) {
                return $leftOther <=> $rightOther;
            }
            if ($left['count'] !== $right['count']) {
                return $right['count'] <=> $left['count'];
            }

            return strcmp($left['city'], $right['city']);
        });

        return array_map(fn (array $row) => [
            'key' => $row['key'],
            'label' => self::label($row['city'], $row['uf']),
            'count' => $row['count'],
        ], $rows);
    }

    private static function cityFromTail(string $body): string
    {
        $words = self::words($body);
        while ($words !== [] && self::isNoise($words[array_key_last($words)])) {
            array_pop($words);
        }
        if ($words === []) {
            return '';
        }

        $last = (string) array_pop($words);
        $previous = $words === [] ? null : (string) $words[array_key_last($words)];
        $takePrevious = $previous !== null && (self::isCityPrefix($previous) || self::isCitySuffix($last));

        return self::title(implode(' ', $takePrevious ? [$previous, $last] : [$last]));
    }

    /**
     * @return list<string>
     */
    private static function words(string $value): array
    {
        $parts = preg_split('/\s+/', trim($value)) ?: [];
        $words = [];
        foreach ($parts as $part) {
            $clean = trim((string) $part, " \t,.;");
            if ($clean !== '') {
                $words[] = $clean;
            }
        }

        return $words;
    }

    private static function token(string $word): string
    {
        return str_replace(['~', '^', '`', "'", '"'], '', TextNormalizer::fold($word));
    }

    private static function isNoise(string $word): bool
    {
        $folded = self::token($word);
        if ($folded === '' || preg_match('/\d/', $folded) === 1) {
            return true;
        }

        return in_array($folded, ['s/n', 'sn', 'km', 'zona', 'rural', 'em', 'no', 'na'], true);
    }

    private static function isCityPrefix(string $word): bool
    {
        return in_array(self::token($word), [
            'nova', 'novo', 'sao', 'santa', 'santo', 'rio', 'monte', 'belo',
            'porto', 'juiz', 'tres', 'cabo', 'barra', 'campo', 'campos', 'feira',
        ], true);
    }

    private static function isCitySuffix(string $word): bool
    {
        return in_array(self::token($word), [
            'filho', 'filha', 'acima', 'abaixo', 'preto', 'preta', 'horizonte',
            'claro', 'grosso', 'vista', 'cruz', 'iguacu',
        ], true);
    }

    private static function title(string $value): string
    {
        $lower = mb_strtolower(trim($value), 'UTF-8');

        return mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');
    }
}
