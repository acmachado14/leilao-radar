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

    public function auctionStartsAt(): ?Carbon
    {
        $value = $this->scheduledStartValue();
        if ($value === null) {
            return null;
        }

        return AuctionDate::parse($value);
    }

    public function hasScheduledClockTime(): bool
    {
        return AuctionDate::hasClockTime($this->scheduledStartValue());
    }

    public function isAuctionReminderDue(?Carbon $now = null): bool
    {
        $reference = $now?->copy()->timezone('America/Sao_Paulo') ?? now('America/Sao_Paulo');
        if (! $this->isUpcoming($reference)) {
            return false;
        }

        $startValue = $this->scheduledStartValue();
        $start = AuctionDate::parse($startValue);
        if ($start === null) {
            return false;
        }

        if (AuctionDate::hasClockTime($startValue)) {
            $leadMinutes = max(1, (int) config('radar.reminder_lead_minutes', 60));
            $windowStart = $start->copy()->subMinutes($leadMinutes);

            return $reference->gte($windowStart) && $reference->lt($start);
        }

        return $reference->isSameDay($start);
    }

    public function auctionWhenLabel(): string
    {
        $startValue = $this->scheduledStartValue();
        $start = AuctionDate::parse($startValue);
        if ($start === null) {
            return 'data indefinida';
        }

        $start = $start->timezone('America/Sao_Paulo');
        if (AuctionDate::hasClockTime($startValue)) {
            return $start->format('d/m/Y H:i');
        }

        return $start->format('d/m/Y').' (horário não informado)';
    }

    private function scheduledStartValue(): ?string
    {
        foreach ([$this->leilao_em, $this->leilao_fim] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
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
        return route('catalog').'#lote='.rawurlencode((string) $this->lote_id);
    }
}
