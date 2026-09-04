<?php

namespace App\Livewire;

use App\Models\Lot;
use App\Services\Alerts\AlertDispatcher;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(AlertDispatcher $dispatcher)
    {
        $user = Auth::user();
        $preference = $user->alertPreference;
        $matches = $preference
            ? $dispatcher->preview($user, $preference, 8)
            : collect();

        return view('livewire.dashboard', [
            'user' => $user,
            'preference' => $preference,
            'matches' => $matches,
            'lotCount' => Lot::query()->count(),
            'whatsappReady' => (bool) config('radar.whatsapp.enabled'),
        ])->layout('layouts.app', [
            'title' => 'Painel — Leilão Radar',
        ]);
    }
}
