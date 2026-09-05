<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Password;
use Livewire\Component;

class ForgotPassword extends Component
{
    public string $email = '';

    public bool $sent = false;

    protected function rules(): array
    {
        return [
            'email' => 'required|email',
        ];
    }

    public function sendResetLink(): void
    {
        $this->validate();
        Password::sendResetLink(['email' => $this->email]);
        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.forgot-password')->layout('layouts.app', [
            'title' => 'Esqueci a senha — VerifyRadar',
        ]);
    }
}
