<?php

namespace App\Livewire;

use App\Constants\Plan;
use App\Services\Billing\PlanQuota;
use App\Support\SalesWhatsApp;
use Livewire\Component;

class Home extends Component
{
    public function render(PlanQuota $quota)
    {
        return view('livewire.home', [
            'plans' => $quota->publicPlans(),
            'checkoutUrl' => SalesWhatsApp::checkoutUrl(Plan::RADAR_PRO),
        ])->layout('layouts.app', [
            'title' => 'VerifyRadar — IA para lance de leilão',
            'fullBleed' => true,
            'metaDescription' => 'IA lê fotos e FIPE e diz até quanto pagar no leilão. Alertas Sodré e Palácio. Teste 3 análises grátis.',
        ]);
    }
}
