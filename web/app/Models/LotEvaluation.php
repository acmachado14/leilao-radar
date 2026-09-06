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
    'max_bid_amount',
    'estimated_resale',
    'estimated_costs',
    'target_profit',
    'pricing_rationale',
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
            'max_bid_amount' => 'float',
            'estimated_resale' => 'float',
            'estimated_costs' => 'float',
            'target_profit' => 'float',
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
            'pricing-v1',
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
            'max_bid_amount' => $this->max_bid_amount,
            'estimated_resale' => $this->estimated_resale,
            'estimated_costs' => $this->estimated_costs,
            'target_profit' => $this->target_profit,
            'pricing_rationale' => $this->pricing_rationale,
            'model' => $this->model,
            'evaluated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
