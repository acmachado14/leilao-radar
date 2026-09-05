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
        $interestedIds = $user->lotInterests()->pluck('lote_id');
        $interested = Lot::query()
            ->whereIn('lote_id', $interestedIds)
            ->get()
            ->filter(fn (Lot $lot) => $lot->isUpcoming())
            ->sortBy(fn (Lot $lot) => $lot->daysUntilAuction() ?? 999)
            ->values();

        return view('livewire.dashboard', [
            'user' => $user,
            'matches' => $matches,
            'interested' => $interested,
            'lotCount' => Lot::query()->count(),
            'whatsappReady' => (bool) config('radar.whatsapp.enabled'),
        ])->layout('layouts.app', [
            'title' => 'Painel — VerifyRadar',
        ]);
    }
}
