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

    public function label(): string
    {
        $name = trim((string) $this->name);
        if ($name !== '') {
            return $name;
        }

        $search = trim((string) $this->search);
        if ($search !== '') {
            return $search;
        }

        return 'Todas as ofertas';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
