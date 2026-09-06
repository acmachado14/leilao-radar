<?php

namespace App\Livewire;

use App\Services\Billing\PlanQuota;
use App\Support\SalesWhatsApp;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Catalog extends Component
{
    public function render(PlanQuota $quota)
    {
        $user = Auth::user();

        return view('livewire.catalog', [
            'plans' => $quota->publicPlans(),
            'quota' => $user ? $quota->snapshot($user) : null,
            'checkoutUrl' => SalesWhatsApp::checkoutUrl($user ? $quota->suggestedUpgrade($user->plan) : 'radar', $user),
        ])->layout('layouts.app', [
            'title' => 'VerifyRadar — IA para lance de leilão',
            'fullBleed' => true,
            'metaDescription' => 'IA lê fotos e FIPE e diz até quanto pagar no leilão. Alertas Sodré e Palácio. Teste 3 análises grátis.',
        ]);
    }
}
