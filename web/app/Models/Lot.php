<?php

namespace App\Models;

use App\Support\AuctionDate;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

#[Fillable([
    'lote_id',
    'titulo',
    'marca',
    'modelo',
    'ano_mod',
    'lance_atual',
    'fipe_preco',
    'desconto_pct',
    'desconto_label',
    'relevance_score',
    'leilao_fim',
    'leilao_em',
    'fipe_match',
    'classificacao_monta',
    'sinistro',
    'sinistro_label',
    'patio',
    'fonte',
    'url',
    'foto_capa',
    'fotos',
    'custo_estimado_5pct',
])]
class Lot extends Model
{
    use HasFactory;

    protected $primaryKey = 'lote_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'fotos' => 'array',
            'lance_atual' => 'float',
            'fipe_preco' => 'float',
            'desconto_pct' => 'float',
            'relevance_score' => 'float',
            'custo_estimado_5pct' => 'float',
            'ano_mod' => 'integer',
        ];
    }

    public function auctionEndsAt(): ?Carbon
    {
        return AuctionDate::parseEnd($this->leilao_fim ?: $this->leilao_em);
    }

    public function daysUntilAuction(?Carbon $now = null): ?float
    {
        $end = $this->auctionEndsAt();
        if ($end === null) {
            return null;
        }

        $reference = $now?->copy() ?? now('America/Sao_Paulo');

        return ($end->getTimestamp() - $reference->getTimestamp()) / 86400;
    }

    public function isUpcoming(?Carbon $now = null): bool
    {
        $days = $this->daysUntilAuction($now);

        return $days === null || $days >= 0;
    }

    public function shareUrl(): string
    {
        return url('/').'#lote='.rawurlencode((string) $this->lote_id);
    }
}
