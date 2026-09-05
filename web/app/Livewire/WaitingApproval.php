<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class WaitingApproval extends Component
{
    public function mount(): void
    {
        $user = Auth::user();
        if ($user && ! $user->isPending()) {
            $this->redirectRoute($user->homeRoute(), navigate: true);
        }
    }

    public function render()
    {
        return view('livewire.waiting-approval', [
            'user' => Auth::user(),
        ])->layout('layouts.app', [
            'title' => 'Aguardando aprovação — VerifyRadar',
        ]);
    }
}
