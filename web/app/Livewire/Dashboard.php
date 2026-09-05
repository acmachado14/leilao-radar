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
        $matches = $dispatcher->previewForUser($user, 8);

        return view('livewire.dashboard', [
            'user' => $user,
            'matches' => $matches,
            'lotCount' => Lot::query()->count(),
            'whatsappReady' => (bool) config('radar.whatsapp.enabled'),
        ])->layout('layouts.app', [
            'title' => 'Painel — VerifyRadar',
        ]);
    }
}
