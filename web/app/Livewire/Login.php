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

    public string $redirectTo = '';

    public function mount(): void
    {
        $this->redirectTo = (string) request()->query('redirect', '');
    }

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

        if ($this->isSafeRedirect($this->redirectTo)) {
            $this->redirect($this->redirectTo, navigate: false);

            return;
        }

        $this->redirectRoute(Auth::user()->homeRoute(), navigate: true);
    }

    private function isSafeRedirect(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) && is_string($appHost) && strcasecmp($host, $appHost) === 0;
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
