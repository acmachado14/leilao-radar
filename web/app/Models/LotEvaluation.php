<?php

namespace App\Models;

use App\Constants\LotEvaluationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lote_id',
    'status',
    'source_hash',
    'risk_score',
    'summary',
    'flags',
    'patio_checks',
    'model',
    'error_message',
])]
class LotEvaluation extends Model
{
    protected $primaryKey = 'lote_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'flags' => 'array',
            'patio_checks' => 'array',
            'risk_score' => 'integer',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class, 'lote_id', 'lote_id');
    }

    public static function sourceHashFor(Lot $lot): string
    {
        $photos = is_array($lot->fotos) ? $lot->fotos : [];
        $payload = implode('|', [
            (string) $lot->lote_id,
            (string) $lot->lance_atual,
            (string) $lot->fipe_preco,
            (string) $lot->fipe_match,
            (string) $lot->classificacao_monta,
            (string) $lot->sinistro,
            (string) $lot->foto_capa,
            implode(',', array_slice($photos, 0, 6)),
        ]);

        return hash('sha256', $payload);
    }

    public function isReady(): bool
    {
        return $this->status === LotEvaluationStatus::READY;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'lote_id' => $this->lote_id,
            'status' => $this->status,
            'risk_score' => $this->risk_score,
            'summary' => $this->summary,
            'flags' => $this->flags ?? [],
            'patio_checks' => $this->patio_checks ?? [],
            'model' => $this->model,
            'evaluated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
