<?php

namespace App\Livewire;

use App\Constants\Plan;
use App\Services\Billing\PlanQuota;
use App\Support\SalesWhatsApp;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PlanPage extends Component
{
    public function render(PlanQuota $quota)
    {
        $user = Auth::user();
        $plans = $quota->publicPlans();
        $pro = collect($plans)->firstWhere('key', Plan::RADAR_PRO) ?? $quota->definition(Plan::RADAR_PRO);

        return view('livewire.plan', [
            'user' => $user,
            'quota' => $quota->snapshot($user),
            'plans' => $plans,
            'pro' => $pro,
            'checkoutUrl' => SalesWhatsApp::checkoutUrl(Plan::RADAR_PRO, $user),
        ])->layout('layouts.app', [
            'title' => 'Meu plano — VerifyRadar',
        ]);
    }
}
