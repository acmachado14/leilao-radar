<?php

namespace App\Bff;

use App\Models\AlertPreference;
use App\Support\SearchYearExtractor;

class PreferenceInput
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function hydrate(array $input, ?AlertPreference $existing = null): array
    {
        $base = $existing
            ? [
                'name' => (string) $existing->name,
                'search' => (string) $existing->search,
                'ano_min' => $existing->ano_min,
                'ano_max' => $existing->ano_max,
                'marcas' => $existing->marcas ?? [],
                'fontes' => $existing->fontes ?? ['sodre', 'palacio'],
                'fipe_matches' => $existing->fipe_matches ?? ['exact', 'closest', 'failed'],
                'monta' => $existing->monta ?? ['sem_sinistro', 'pequena', 'media'],
                'min_desconto' => (float) $existing->min_desconto,
                'exclude_grande' => (bool) $existing->exclude_grande,
                'max_days_until' => $existing->max_days_until,
            ]
            : AlertPreference::defaults();

        $searchRaw = array_key_exists('search', $input)
            ? trim((string) $input['search'])
            : (array_key_exists('q', $input) ? trim((string) $input['q']) : (string) $base['search']);

        $extracted = SearchYearExtractor::extract($searchRaw);
        $search = $extracted['search'];

        $anoMin = self::nullableInt($input['ano_min'] ?? null) ?? $extracted['ano_min'] ?? $base['ano_min'];
        $anoMax = self::nullableInt($input['ano_max'] ?? null) ?? $extracted['ano_max'] ?? $base['ano_max'];
        if (is_int($anoMin) && is_int($anoMax) && $anoMin > $anoMax) {
            [$anoMin, $anoMax] = [$anoMax, $anoMin];
        }

        $minDesconto = $base['min_desconto'];
        if (array_key_exists('min_desconto', $input) && $input['min_desconto'] !== '' && $input['min_desconto'] !== null) {
            $raw = (float) $input['min_desconto'];
            $minDesconto = abs($raw) > 1 ? $raw / 100 : $raw;
        }

        return [
            'name' => array_key_exists('name', $input) ? trim((string) $input['name']) : $base['name'],
            'search' => $search,
            'ano_min' => $anoMin,
            'ano_max' => $anoMax,
            'marcas' => self::marcas($input, $base['marcas']),
            'fontes' => self::group($input, [
                'fonte_sodre' => 'sodre',
                'fonte_palacio' => 'palacio',
            ], $base['fontes']),
            'fipe_matches' => self::group($input, [
                'fipe_exact' => 'exact',
                'fipe_closest' => 'closest',
                'fipe_failed' => 'failed',
            ], $base['fipe_matches']),
            'monta' => self::group($input, [
                'monta_sem_sinistro' => 'sem_sinistro',
                'monta_pequena' => 'pequena',
                'monta_media' => 'media',
                'monta_outro' => 'outro',
            ], $base['monta']),
            'min_desconto' => $minDesconto,
            'exclude_grande' => array_key_exists('exclude_grande', $input)
                ? self::truthy($input['exclude_grande'])
                : (bool) $base['exclude_grande'],
            'max_days_until' => array_key_exists('max_days_until', $input)
                ? self::nullableInt($input['max_days_until'])
                : $base['max_days_until'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, string>  $map
     * @param  list<string>  $fallback
     * @return list<string>
     */
    public static function group(array $input, array $map, array $fallback): array
    {
        $hasAny = false;
        foreach (array_keys($map) as $key) {
            if (array_key_exists($key, $input)) {
                $hasAny = true;
                break;
            }
        }

        if (! $hasAny) {
            return array_values($fallback);
        }

        $selected = [];
        foreach ($map as $key => $value) {
            if (array_key_exists($key, $input)) {
                if (self::truthy($input[$key] ?? false)) {
                    $selected[] = $value;
                }

                continue;
            }

            if (in_array($value, $fallback, true)) {
                $selected[] = $value;
            }
        }

        return $selected;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<string>  $fallback
     * @return list<string>
     */
    public static function marcas(array $input, array $fallback): array
    {
        if (! array_key_exists('marcas', $input) && ! array_key_exists('marca', $input)) {
            return array_values($fallback);
        }

        $raw = $input['marcas'] ?? $input['marca'] ?? [];
        if (is_string($raw)) {
            $raw = preg_split('/[,;\n]+/', $raw) ?: [];
        }
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map(fn ($item) => trim((string) $item), $raw)));
    }

    public static function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param  array<string, mixed>  $preference
     * @return array<string, mixed>
     */
    public static function checkboxValues(array $preference): array
    {
        $fontes = $preference['fontes'] ?? [];
        $fipe = $preference['fipe_matches'] ?? [];
        $monta = $preference['monta'] ?? [];

        return [
            'fonte_sodre' => in_array('sodre', $fontes, true),
            'fonte_palacio' => in_array('palacio', $fontes, true),
            'fipe_exact' => in_array('exact', $fipe, true),
            'fipe_closest' => in_array('closest', $fipe, true),
            'fipe_failed' => in_array('failed', $fipe, true),
            'monta_sem_sinistro' => in_array('sem_sinistro', $monta, true),
            'monta_pequena' => in_array('pequena', $monta, true),
            'monta_media' => in_array('media', $monta, true),
            'monta_outro' => in_array('outro', $monta, true),
            'exclude_grande' => (bool) ($preference['exclude_grande'] ?? true),
        ];
    }
}
