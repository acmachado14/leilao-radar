<?php

namespace App\Livewire;

use App\Constants\SubscriptionStatus;
use App\Models\AlertPreference;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $terms_accepted = false;

    public bool $notify_whatsapp = false;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|min:8|confirmed',
            'terms_accepted' => 'accepted',
            'notify_whatsapp' => 'boolean',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'terms_accepted' => 'termos de uso',
        ];
    }

    public function register(): void
    {
        $this->validate();

        if ($this->notify_whatsapp && trim($this->phone) === '') {
            $this->addError('phone', 'Informe o WhatsApp para receber alertas nesse canal.');

            return;
        }

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone !== '' ? $this->phone : null,
            'password' => Hash::make($this->password),
            'active' => true,
            'subscription_status' => SubscriptionStatus::TRIAL,
            'subscription_until' => now()->addDays((int) config('radar.trial_days', 7)),
            'last_login_at' => now(),
        ]);

        $defaults = AlertPreference::defaults();
        $defaults['notify_whatsapp'] = $this->notify_whatsapp;
        $user->alertPreference()->create($defaults);

        Auth::login($user);

        $this->redirectRoute('alertas', navigate: true);
    }

    public function render()
    {
        return view('livewire.register')->layout('layouts.app', [
            'title' => 'Criar conta — Leilão Radar',
        ]);
    }
}
