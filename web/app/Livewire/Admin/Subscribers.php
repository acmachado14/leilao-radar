<?php

namespace App\Livewire\Admin;

use App\Constants\SubscriptionStatus;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class Subscribers extends Component
{
    use WithPagination;

    public string $search = '';

    public function activate(string $userId, int $days = 30): void
    {
        $user = User::query()->findOrFail($userId);
        $user->update([
            'subscription_status' => SubscriptionStatus::ACTIVE,
            'subscription_until' => now()->addDays($days),
            'active' => true,
        ]);
        session()->flash('success', "Assinatura de {$user->name} ativada por {$days} dias.");
    }

    public function pause(string $userId): void
    {
        $user = User::query()->findOrFail($userId);
        $user->update(['subscription_status' => SubscriptionStatus::PAUSED]);
        session()->flash('success', "Assinatura de {$user->name} pausada.");
    }

    public function expire(string $userId): void
    {
        $user = User::query()->findOrFail($userId);
        $user->update([
            'subscription_status' => SubscriptionStatus::EXPIRED,
            'subscription_until' => now(),
        ]);
        session()->flash('success', "Assinatura de {$user->name} expirada.");
    }

    public function toggleActive(string $userId): void
    {
        $user = User::query()->findOrFail($userId);
        if ($user->isAdmin() && $user->id === auth()->id()) {
            session()->flash('error', 'Você não pode bloquear a própria conta admin.');

            return;
        }

        $user->update(['active' => ! $user->active]);
        session()->flash('success', $user->active ? 'Conta reativada.' : 'Conta bloqueada.');
    }

    public function render()
    {
        $users = User::query()
            ->with('alertPreferences')
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)->orWhere('email', 'like', $term);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('livewire.admin.subscribers', [
            'users' => $users,
        ])->layout('layouts.app', [
            'title' => 'Assinantes — Leilão Radar',
        ]);
    }
}
