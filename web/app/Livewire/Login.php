<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    protected function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required',
        ];
    }

    public function login(): void
    {
        $this->validate();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            $this->addError('email', 'E-mail ou senha incorretos.');

            return;
        }

        if (Auth::user()?->active === false) {
            Auth::logout();
            $this->addError('email', 'Esta conta está bloqueada. Entre em contato com o suporte.');

            return;
        }

        session()->regenerate();

        User::query()
            ->whereKey(Auth::id())
            ->update(['last_login_at' => now()]);

        $this->redirectRoute(Auth::user()->homeRoute(), navigate: true);
    }

    public function logout()
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('login');
    }

    public function render()
    {
        return view('livewire.login')->layout('layouts.app', [
            'title' => 'Entrar — VerifyRadar',
        ]);
    }
}
