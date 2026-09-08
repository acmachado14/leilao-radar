<?php

namespace App\Services\Alerts;

use App\Models\AlertPreference;
use App\Models\Lot;
use App\Support\TextNormalizer;
use Illuminate\Support\Carbon;

class LotMatcher
{
    public function matches(Lot $lot, AlertPreference $preference, ?Carbon $now = null): bool
    {
        if (! $preference->isConfigured()) {
            return false;
        }

        if (! $lot->isUpcoming($now)) {
            return false;
        }

        $days = $lot->daysUntilAuction($now);
        if ($preference->max_days_until !== null && $days !== null && $days > $preference->max_days_until) {
            return false;
        }

        $fontes = $preference->fontes ?? [];
        $fonte = $lot->fonte ?: 'sodre';
        if ($fontes !== [] && ! in_array($fonte, $fontes, true)) {
            return false;
        }

        $matches = $preference->fipe_matches ?? [];
        if ($matches !== [] && ! in_array((string) $lot->fipe_match, $matches, true)) {
            return false;
        }

        if (! $this->passesMinDesconto($lot, (float) $preference->min_desconto)) {
            return false;
        }

        if ($preference->exclude_grande && $lot->classificacao_monta === 'grande') {
            return false;
        }

        $monta = $preference->monta ?? [];
        if ($monta !== [] && ! in_array((string) $lot->classificacao_monta, $monta, true)) {
            return false;
        }

        $marcas = array_filter($preference->marcas ?? []);
        if ($marcas !== []) {
            $keys = array_map(fn ($marca) => TextNormalizer::fold((string) $marca), $marcas);
            if (! in_array(TextNormalizer::fold((string) $lot->marca), $keys, true)) {
                return false;
            }
        }

        $search = trim((string) $preference->search);
        if ($search !== '' && ! $this->matchesSearch($lot, $search)) {
            return false;
        }

        if (! $this->matchesYear($lot, $preference->ano_min, $preference->ano_max)) {
            return false;
        }

        return true;
    }

    private function matchesYear(Lot $lot, mixed $anoMin, mixed $anoMax): bool
    {
        $min = is_numeric($anoMin) ? (int) $anoMin : null;
        $max = is_numeric($anoMax) ? (int) $anoMax : null;
        if ($min === null && $max === null) {
            return true;
        }

        $year = $lot->ano_mod;
        if ($year === null) {
            return false;
        }

        if ($min !== null && $year < $min) {
            return false;
        }

        if ($max !== null && $year > $max) {
            return false;
        }

        return true;
    }

    private function passesMinDesconto(Lot $lot, float $minDesconto): bool
    {
        $desconto = $lot->desconto_pct;
        if ($desconto !== null && $desconto <= -900) {
            return true;
        }

        return ($desconto ?? -999) >= $minDesconto;
    }

    private function matchesSearch(Lot $lot, string $query): bool
    {
        $tokens = preg_split('/\s+/', TextNormalizer::fold($query)) ?: [];
        $tokens = array_values(array_filter($tokens));
        if ($tokens === []) {
            return false;
        }

        $haystack = TextNormalizer::fold(trim($lot->marca.' '.$lot->modelo.' '.$lot->titulo));

        foreach ($tokens as $token) {
            if (! TextNormalizer::containsToken($haystack, $token)) {
                return false;
            }
        }

        return true;
    }
}
