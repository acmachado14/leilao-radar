<?php

namespace App\Bff;

use App\Models\Lot;
use App\Support\TextNormalizer;
use Illuminate\Support\Collection;

class CatalogFilter
{
    /**
     * @param  list<string>  $fontes
     * @param  list<string>  $fipe
     * @param  list<string>  $monta
     * @param  list<string>  $marcas
     */
    public function __construct(
        public readonly string $q = '',
        public readonly array $fontes = ['sodre', 'palacio'],
        public readonly array $fipe = ['exact', 'closest', 'failed'],
        public readonly array $monta = ['sem_sinistro', 'pequena', 'media'],
        public readonly bool $excludeGrande = true,
        public readonly float $minDesconto = 0.0,
        public readonly array $marcas = [],
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function from(array $input): self
    {
        $q = trim((string) ($input['q'] ?? $input['search'] ?? ''));

        return new self(
            q: $q,
            fontes: self::values($input, 'fontes', [
                'fonte_sodre' => 'sodre',
                'fonte_palacio' => 'palacio',
            ], ['sodre', 'palacio']),
            fipe: self::values($input, 'fipe', [
                'fipe_exact' => 'exact',
                'fipe_closest' => 'closest',
                'fipe_failed' => 'failed',
            ], ['exact', 'closest', 'failed']),
            monta: self::values($input, 'monta', [
                'monta_sem_sinistro' => 'sem_sinistro',
                'monta_pequena' => 'pequena',
                'monta_media' => 'media',
                'monta_outro' => 'outro',
            ], ['sem_sinistro', 'pequena', 'media']),
            excludeGrande: array_key_exists('exclude_grande', $input)
                ? PreferenceInput::truthy($input['exclude_grande'])
                : true,
            minDesconto: self::minDesconto($input),
            marcas: PreferenceInput::marcas($input, []),
        );
    }

    public static function snapshotCount(): int
    {
        return Lot::query()->count();
    }

    /**
     * Same rules as web/resources/js/catalog.js applyFilters(), then isUpcoming().
     *
     * @return Collection<int, Lot>
     */
    public function matchingUpcoming(): Collection
    {
        return $this->filteredQuery()
            ->get()
            ->filter(fn (Lot $lot) => $lot->isUpcoming())
            ->values();
    }

    /**
     * @return Collection<int, Lot>
     */
    public function lots(int $limit = 100): Collection
    {
        return $this->matchingUpcoming()->take($limit)->values();
    }

    public function filteredCount(): int
    {
        return $this->matchingUpcoming()->count();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Lot>
     */
    private function filteredQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = Lot::query()->orderByDesc('relevance_score');

        if ($this->fontes !== []) {
            $query->whereIn('fonte', $this->fontes);
        }

        if ($this->fipe !== []) {
            $query->whereIn('fipe_match', $this->fipe);
        } else {
            $query->whereRaw('0 = 1');
        }

        if ($this->excludeGrande) {
            $query->where(function ($inner) {
                $inner->whereNull('classificacao_monta')
                    ->orWhere('classificacao_monta', '!=', 'grande');
            });
        }

        if ($this->monta !== []) {
            $query->whereIn('classificacao_monta', $this->monta);
        }

        $query->where(function ($inner) {
            $inner->where('desconto_pct', '<=', -900)
                ->orWhere('desconto_pct', '>=', $this->minDesconto);
        });

        if ($this->marcas !== []) {
            $query->where(function ($inner) {
                foreach ($this->marcas as $marca) {
                    $inner->orWhere('marca', 'like', '%'.$marca.'%');
                }
            });
        }

        $tokens = self::searchTokens($this->q);
        foreach ($tokens as $token) {
            $like = '%'.$token.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('marca', 'like', $like)
                    ->orWhere('modelo', 'like', $like);
            });
        }

        $today = now('America/Sao_Paulo')->toDateString();
        $query->where(function ($inner) use ($today) {
            $inner->whereRaw('COALESCE(NULLIF(leilao_fim, \'\'), leilao_em) >= ?', [$today])
                ->orWhere(function ($empty) {
                    $empty->where(function ($field) {
                        $field->whereNull('leilao_fim')->orWhere('leilao_fim', '');
                    })->where(function ($field) {
                        $field->whereNull('leilao_em')->orWhere('leilao_em', '');
                    });
                });
        });

        return $query;
    }

    public function toggle(string $group, string $value): self
    {
        $current = match ($group) {
            'fontes' => $this->fontes,
            'fipe' => $this->fipe,
            'monta' => $this->monta,
            default => [],
        };

        if (in_array($value, $current, true)) {
            $next = array_values(array_filter($current, fn (string $item) => $item !== $value));
        } else {
            $next = [...$current, $value];
        }

        return match ($group) {
            'fontes' => new self($this->q, $next, $this->fipe, $this->monta, $this->excludeGrande, $this->minDesconto, $this->marcas),
            'fipe' => new self($this->q, $this->fontes, $next, $this->monta, $this->excludeGrande, $this->minDesconto, $this->marcas),
            'monta' => new self($this->q, $this->fontes, $this->fipe, $next, $this->excludeGrande, $this->minDesconto, $this->marcas),
            default => $this,
        };
    }

    public function withMinDescontoPercent(int $percent): self
    {
        return new self(
            $this->q,
            $this->fontes,
            $this->fipe,
            $this->monta,
            $this->excludeGrande,
            $percent / 100,
            $this->marcas,
        );
    }

    public function withExcludeGrande(bool $exclude): self
    {
        return new self($this->q, $this->fontes, $this->fipe, $this->monta, $exclude, $this->minDesconto, $this->marcas);
    }

    /**
     * @return array<string, string>
     */
    public function params(): array
    {
        $params = [
            'fontes' => implode(',', $this->fontes),
            'fipe' => implode(',', $this->fipe),
            'monta' => implode(',', $this->monta),
            'exclude_grande' => $this->excludeGrande ? '1' : '0',
            'min_desconto' => (string) (int) round($this->minDesconto * 100),
        ];
        if ($this->q !== '') {
            $params['q'] = $this->q;
        }
        if ($this->marcas !== []) {
            $params['marca'] = implode(',', $this->marcas);
        }

        return $params;
    }

    public function extraCount(): int
    {
        $count = 0;
        if ($this->fipe != ['exact', 'closest', 'failed']) {
            $count++;
        }
        if ($this->monta != ['sem_sinistro', 'pequena', 'media']) {
            $count++;
        }
        if (! $this->excludeGrande) {
            $count++;
        }
        if ($this->minDesconto > 0) {
            $count++;
        }
        if ($this->marcas !== []) {
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, string>  $boolMap
     * @param  list<string>  $default
     * @return list<string>
     */
    private static function values(array $input, string $csvKey, array $boolMap, array $default): array
    {
        if (array_key_exists($csvKey, $input)) {
            $raw = $input[$csvKey];
            if ($raw === null || $raw === '') {
                return [];
            }
            if (is_array($raw)) {
                return array_values(array_filter(array_map('strval', $raw)));
            }

            return array_values(array_filter(array_map('trim', explode(',', (string) $raw))));
        }

        return PreferenceInput::group($input, $boolMap, $default);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function minDesconto(array $input): float
    {
        if (! array_key_exists('min_desconto', $input) || $input['min_desconto'] === '' || $input['min_desconto'] === null) {
            return 0.0;
        }

        $raw = (float) $input['min_desconto'];

        return abs($raw) > 1 ? $raw / 100 : $raw;
    }

    /**
     * @return list<string>
     */
    private static function searchTokens(string $query): array
    {
        $tokens = preg_split('/\s+/', TextNormalizer::fold($query)) ?: [];

        return array_values(array_filter($tokens));
    }
}
