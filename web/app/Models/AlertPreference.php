<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'name',
    'search',
    'ano_min',
    'ano_max',
    'marcas',
    'fontes',
    'fipe_matches',
    'monta',
    'min_desconto',
    'exclude_grande',
    'max_days_until',
    'notify_email',
    'notify_whatsapp',
])]
class AlertPreference extends Model
{
    use HasUuid;

    protected function casts(): array
    {
        return [
            'marcas' => 'array',
            'fontes' => 'array',
            'fipe_matches' => 'array',
            'monta' => 'array',
            'ano_min' => 'integer',
            'ano_max' => 'integer',
            'min_desconto' => 'float',
            'exclude_grande' => 'boolean',
            'max_days_until' => 'integer',
            'notify_email' => 'boolean',
            'notify_whatsapp' => 'boolean',
        ];
    }

    public static function defaults(): array
    {
        return [
            'name' => '',
            'search' => '',
            'ano_min' => null,
            'ano_max' => null,
            'marcas' => [],
            'fontes' => ['sodre', 'palacio'],
            'fipe_matches' => ['exact', 'closest', 'failed'],
            'monta' => ['sem_sinistro', 'pequena', 'media'],
            'min_desconto' => 0.0,
            'exclude_grande' => true,
            'max_days_until' => 14,
            'notify_email' => true,
            'notify_whatsapp' => false,
        ];
    }

    /**
     * Preference payload that actually selects lots (empty search is not a recorte).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function scoped(array $overrides = []): array
    {
        return array_merge(self::defaults(), ['search' => 'Corolla'], $overrides);
    }

    public function isConfigured(): bool
    {
        if (trim((string) $this->search) !== '') {
            return true;
        }

        return array_values(array_filter($this->marcas ?? [])) !== [];
    }

    public function label(): string
    {
        $name = trim((string) $this->name);
        if ($name !== '') {
            return $name;
        }

        $search = trim((string) $this->search);
        if ($search !== '') {
            $label = $search;
        } elseif (array_values(array_filter($this->marcas ?? [])) !== []) {
            $label = implode(', ', array_values(array_filter($this->marcas ?? [])));
        } else {
            $label = 'Incompleto — defina um modelo';
        }

        $years = $this->yearLabel();
        if ($years !== null) {
            return str_starts_with($label, 'Incompleto') ? $years : $label.' · '.$years;
        }

        return $label;
    }

    public function yearLabel(): ?string
    {
        $min = $this->ano_min;
        $max = $this->ano_max;
        if ($min === null && $max === null) {
            return null;
        }

        if ($min !== null && $max !== null && $min !== $max) {
            return $min.'–'.$max;
        }

        return (string) ($min ?? $max);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
