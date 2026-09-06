<?php

namespace App\Support;

use App\Models\User;

class SalesWhatsApp
{
    public static function checkoutUrl(?string $plan = null, ?User $user = null): string
    {
        $phone = preg_replace('/\D+/', '', (string) config('radar.sales.whatsapp', '5531986268630')) ?: '5531986268630';
        $plans = config('radar.plans', []);
        $planName = is_string($plan) ? (string) data_get($plans, "{$plan}.name", $plan) : null;

        $lines = ['Olá! Quero falar com um atendente do VerifyRadar.'];
        if ($planName) {
            $lines = ["Olá! Quero o plano {$planName} do VerifyRadar."];
        }
        if ($user !== null) {
            $lines[] = "Meu nome: {$user->name}";
            $lines[] = "E-mail: {$user->email}";
        }

        return 'https://wa.me/'.$phone.'?text='.rawurlencode(implode("\n", $lines));
    }
}
