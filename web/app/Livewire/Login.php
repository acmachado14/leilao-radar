<?php

namespace App\Livewire;

use Livewire\Component;

class Login extends Component
{
    public string $redirectTo = '';

    public function mount(): void
    {
        $this->redirectTo = (string) request()->query('redirect', old('redirect', ''));
    }

    public function render()
    {
        return view('livewire.login')->layout('layouts.app', [
            'title' => 'Entrar — VerifyRadar',
        ]);
    }
}
