<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'remember' => 'sometimes|boolean',
            'redirect' => 'nullable|string',
        ]);

        $email = strtolower(trim($credentials['email']));
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'E-mail ou senha incorretos.',
            ]);
        }

        if ($user->active === false) {
            throw ValidationException::withMessages([
                'email' => 'Esta conta está bloqueada. Entre em contato com o suporte.',
            ]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        $redirect = (string) ($credentials['redirect'] ?? '');
        if ($this->isSafeRedirect($redirect, $request)) {
            return redirect()->to($redirect);
        }

        return redirect()->route($user->homeRoute());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function isSafeRedirect(string $url, Request $request): bool
    {
        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: $request->getHost();

        return is_string($host) && is_string($appHost) && strcasecmp($host, $appHost) === 0;
    }
}
