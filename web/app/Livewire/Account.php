<?php

namespace App\Livewire;

use App\Services\Billing\PlanQuota;
use App\Support\SalesWhatsApp;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class Account extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) $user->phone;
    }

    public function updateProfile(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        Auth::user()->update([
            'name' => $this->name,
            'phone' => $this->phone !== '' ? $this->phone : null,
        ]);

        session()->flash('success', 'Dados atualizados.');
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (! Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'Senha atual incorreta.');

            return;
        }

        $user->update([
            'password' => Hash::make($this->password),
        ]);

        $this->reset(['current_password', 'password', 'password_confirmation']);
        session()->flash('success', 'Senha alterada.');
    }

    public function render(PlanQuota $quota)
    {
        $user = Auth::user();

        return view('livewire.account', [
            'user' => $user,
            'quota' => $quota->snapshot($user),
            'checkoutUrl' => SalesWhatsApp::checkoutUrl($quota->suggestedUpgrade($user->plan), $user),
            'plans' => $quota->publicPlans(),
        ])->layout('layouts.app', [
            'title' => 'Minha conta — VerifyRadar',
        ]);
    }
}
