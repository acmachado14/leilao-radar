<?php

namespace App\Livewire\Admin\Concerns;

use App\Constants\SubscriptionStatus;
use App\Models\User;
use App\Services\Admin\AdminAuditor;

trait ManagesSubscribers
{
    public function approve(string $userId, int $days = 30): void
    {
        $user = $this->findManagedUser($userId);

        $user->update([
            'subscription_status' => SubscriptionStatus::ACTIVE,
            'subscription_until' => now()->addDays($days),
            'approved_at' => now(),
            'rejected_at' => null,
            'active' => true,
        ]);

        $this->auditor()->record('approved', "Aprovou {$user->name} por {$days} dias", $user, ['days' => $days]);
        session()->flash('success', "{$user->name} aprovado por {$days} dias.");
    }

    public function approveTrial(string $userId): void
    {
        $days = (int) config('radar.trial_days', 7);
        $user = $this->findManagedUser($userId);

        $user->update([
            'subscription_status' => SubscriptionStatus::TRIAL,
            'subscription_until' => now()->addDays($days),
            'approved_at' => now(),
            'rejected_at' => null,
            'active' => true,
        ]);

        $this->auditor()->record('approved_trial', "Liberou trial de {$user->name} ({$days} dias)", $user, ['days' => $days]);
        session()->flash('success', "Trial de {$user->name} liberado por {$days} dias.");
    }

    public function reject(string $userId): void
    {
        $user = $this->findManagedUser($userId);
        if ($this->isSelfAdmin($user)) {
            session()->flash('error', 'Você não pode recusar a própria conta admin.');

            return;
        }

        $user->update([
            'subscription_status' => SubscriptionStatus::REJECTED,
            'approved_at' => null,
            'rejected_at' => now(),
            'active' => false,
        ]);

        $this->auditor()->record('rejected', "Recusou {$user->name}", $user);
        session()->flash('success', "Cadastro de {$user->name} recusado.");
    }

    public function activate(string $userId, int $days = 30): void
    {
        $this->approve($userId, $days);
    }

    public function pause(string $userId): void
    {
        $user = $this->findManagedUser($userId);
        $user->update(['subscription_status' => SubscriptionStatus::PAUSED]);
        $this->auditor()->record('paused', "Pausou {$user->name}", $user);
        session()->flash('success', "Assinatura de {$user->name} pausada.");
    }

    public function expire(string $userId): void
    {
        $user = $this->findManagedUser($userId);
        $user->update([
            'subscription_status' => SubscriptionStatus::EXPIRED,
            'subscription_until' => now(),
        ]);
        $this->auditor()->record('expired', "Expirou {$user->name}", $user);
        session()->flash('success', "Assinatura de {$user->name} expirada.");
    }

    public function toggleActive(string $userId): void
    {
        $user = $this->findManagedUser($userId);
        if ($this->isSelfAdmin($user)) {
            session()->flash('error', 'Você não pode bloquear a própria conta admin.');

            return;
        }

        $user->update(['active' => ! $user->active]);
        $this->auditor()->record(
            $user->active ? 'unblocked' : 'blocked',
            ($user->active ? 'Liberou' : 'Bloqueou')." {$user->name}",
            $user,
        );
        session()->flash('success', $user->active ? 'Conta reativada.' : 'Conta bloqueada.');
    }

    private function findManagedUser(string $userId): User
    {
        return User::query()->findOrFail($userId);
    }

    private function isSelfAdmin(User $user): bool
    {
        return $user->isAdmin() && $user->id === auth()->id();
    }

    private function auditor(): AdminAuditor
    {
        return app(AdminAuditor::class);
    }
}
