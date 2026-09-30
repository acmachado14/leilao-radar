<?php

namespace App\Livewire;

use App\Constants\Plan;
use App\Models\Lot;
use App\Services\Billing\PlanQuota;
use App\Support\EmailBranding;
use App\Support\SalesWhatsApp;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Catalog extends Component
{
    public function render(PlanQuota $quota)
    {
        $user = Auth::user();

        return view('livewire.catalog', [
            'quota' => $user ? $quota->snapshot($user) : null,
            'checkoutUrl' => SalesWhatsApp::checkoutUrl(Plan::RADAR_PRO, $user),
        ])->layout('layouts.app', $this->layoutData());
    }

    /**
     * @return array<string, mixed>
     */
    private function layoutData(): array
    {
        $defaults = [
            'title' => 'Ofertas — VerifyRadar',
            'fullBleed' => true,
            'metaDescription' => 'Ofertas de leilão Sodré e Palácio vs tabela FIPE. Peça a IA para ver até quanto pagar.',
        ];

        $lotId = request()->query('lote');
        if (! is_string($lotId) || $lotId === '') {
            return $defaults;
        }

        $lot = Lot::query()->find($lotId);
        if ($lot === null) {
            return $defaults;
        }

        $description = $lot->shareDescription();

        return [
            ...$defaults,
            'title' => $lot->titulo.' — VerifyRadar',
            'metaDescription' => $description,
            'ogTitle' => $lot->titulo,
            'ogDescription' => $description,
            'ogImage' => $lot->coverPhotoUrl() ?? EmailBranding::logoUrl(),
            'ogUrl' => $lot->shareUrl(),
        ];
    }
}
