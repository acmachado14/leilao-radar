<?php

namespace App\Services\Push;

use App\Constants\AlertSendKind;
use App\Models\Lot;
use Illuminate\Support\Collection;

class PushMessageFactory
{
    /**
     * @param  Collection<int, Lot>  $lots
     * @return array{title: string, body: string, data: array<string, string>}
     */
    public function forLots(Collection $lots, string $kind = AlertSendKind::MATCH): array
    {
        $count = $lots->count();
        $first = $lots->first();

        if ($kind === AlertSendKind::AUCTION_REMINDER && $first instanceof Lot) {
            return [
                'title' => 'Leilão em breve',
                'body' => 'Leilão em ~1h: '.$first->titulo,
                'data' => $this->navigationData($lots),
            ];
        }

        if ($count === 1 && $first instanceof Lot) {
            return [
                'title' => 'Nova oferta no seu recorte',
                'body' => $first->titulo,
                'data' => $this->navigationData($lots),
            ];
        }

        $body = $first instanceof Lot
            ? $first->titulo.' e mais '.($count - 1)
            : (string) $count.' ofertas';

        return [
            'title' => $count.' oferta'.($count === 1 ? '' : 's').' no seu recorte',
            'body' => $body,
            'data' => $this->navigationData($lots),
        ];
    }

    /**
     * @param  Collection<int, Lot>  $lots
     * @return array<string, string>
     */
    private function navigationData(Collection $lots): array
    {
        if ($lots->count() === 1) {
            $lot = $lots->first();
            if ($lot instanceof Lot) {
                return [
                    'screen' => 'lot',
                    'id' => (string) $lot->lote_id,
                ];
            }
        }

        return [
            'screen' => 'alerts',
        ];
    }
}
