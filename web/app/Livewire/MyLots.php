<?php

namespace App\Livewire;

use App\Models\Lot;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MyLots extends Component
{
    public function render()
    {
        $user = Auth::user();
        $interestedIds = $user->lotInterests()->pluck('lote_id');
        $evaluatedIds = $user->lotEvaluationRequests()->pluck('lote_id');

        $interested = Lot::query()
            ->whereIn('lote_id', $interestedIds)
            ->get()
            ->filter(fn (Lot $lot) => $lot->isUpcoming())
            ->sortBy(fn (Lot $lot) => $lot->daysUntilAuction() ?? 999)
            ->values();

        $evaluated = Lot::query()
            ->whereIn('lote_id', $evaluatedIds)
            ->get()
            ->sortByDesc(fn (Lot $lot) => $lot->updated_at?->timestamp ?? 0)
            ->values();

        return view('livewire.my-lots', [
            'interested' => $interested,
            'evaluated' => $evaluated,
        ])->layout('layouts.app', [
            'title' => 'Meus lotes — VerifyRadar',
        ]);
    }
}
