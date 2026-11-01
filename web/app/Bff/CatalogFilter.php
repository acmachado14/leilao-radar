<?php

namespace App\Bff;

use App\Models\Lot;
use App\Support\PatioRegion;
use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CatalogFilter
{
    /**
     * @param  list<string>  $fontes
     * @param  list<string>  $fipe
     * @param  list<string>  $monta
     * @param  list<string>  $marcas
     * @param  list<string>  $regioes
     */
    public function __construct(
        public readonly string $q = '',
        public readonly array $fontes = ['sodre', 'palacio'],
        public readonly array $fipe = ['exact', 'closest', 'failed'],
        public readonly array $monta = ['sem_sinistro', 'pequena', 'media'],
        public readonly bool $excludeGrande = true,
        public readonly float $minDesconto = 0.0,
        public readonly array $marcas = [],
        public readonly array $regioes = [],
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
            regioes: self::regioes($input),
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
            ->filter(fn (Lot $lot) => $this->matchesRegiao($lot))
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
     * @return Builder<Lot>
     */
    private function filteredQuery(): Builder
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
            'fontes' => $this->copy(fontes: $next),
            'fipe' => $this->copy(fipe: $next),
            'monta' => $this->copy(monta: $next),
            default => $this,
        };
    }

    public function withMinDescontoPercent(int $percent): self
    {
        return $this->copy(minDesconto: $percent / 100);
    }

    public function withExcludeGrande(bool $exclude): self
    {
        return $this->copy(excludeGrande: $exclude);
    }

    /**
     * @param  list<string>  $regioes
     */
    public function withRegioes(array $regioes): self
    {
        return $this->copy(regioes: array_values($regioes));
    }

    public function toggleRegiao(string $key): self
    {
        $key = PatioRegion::key($key);
        if ($key === '') {
            return $this;
        }

        if (in_array($key, $this->regioes, true)) {
            $next = array_values(array_filter($this->regioes, fn (string $item) => $item !== $key));
        } else {
            $next = [...$this->regioes, $key];
        }

        return $this->copy(regioes: $next);
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
        if ($this->regioes !== []) {
            $params['regiao'] = implode(',', $this->regioes);
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
        if ($this->regioes !== []) {
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

    private function matchesRegiao(Lot $lot): bool
    {
        if ($this->regioes === []) {
            return true;
        }

        $region = PatioRegion::parse($lot->patio);
        if ($region === null) {
            return false;
        }

        return in_array($region['key'], $this->regioes, true);
    }

    /**
     * @param  list<string>|null  $fontes
     * @param  list<string>|null  $fipe
     * @param  list<string>|null  $monta
     * @param  list<string>|null  $marcas
     * @param  list<string>|null  $regioes
     */
    private function copy(
        ?string $q = null,
        ?array $fontes = null,
        ?array $fipe = null,
        ?array $monta = null,
        ?bool $excludeGrande = null,
        ?float $minDesconto = null,
        ?array $marcas = null,
        ?array $regioes = null,
    ): self {
        return new self(
            $q ?? $this->q,
            $fontes ?? $this->fontes,
            $fipe ?? $this->fipe,
            $monta ?? $this->monta,
            $excludeGrande ?? $this->excludeGrande,
            $minDesconto ?? $this->minDesconto,
            $marcas ?? $this->marcas,
            $regioes ?? $this->regioes,
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<string>
     */
    private static function regioes(array $input): array
    {
        if (! array_key_exists('regiao', $input) && ! array_key_exists('regioes', $input)) {
            return [];
        }

        $raw = array_key_exists('regiao', $input) ? $input['regiao'] : $input['regioes'];
        if ($raw === null || $raw === '') {
            return [];
        }

        $parts = is_array($raw) ? $raw : explode(',', (string) $raw);
        $keys = [];
        foreach ($parts as $part) {
            $key = PatioRegion::key((string) $part);
            if ($key !== '' && ! in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys;
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
