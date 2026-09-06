<?php

namespace App\Livewire\Admin;

use App\Constants\SubscriptionStatus;
use App\Livewire\Admin\Concerns\ManagesSubscribers;
use App\Models\User;
use App\Services\Billing\PlanQuota;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Subscribers extends Component
{
    use ManagesSubscribers;
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'filtro')]
    public string $filter = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $monthStart = app(PlanQuota::class)->periodStart();
        $users = User::query()
            ->withCount('alertPreferences')
            ->withCount('aiUsageLogs')
            ->withSum('aiUsageLogs', 'estimated_cost_brl')
            ->withCount(['aiUsageLogs as ai_month_count' => function ($query) use ($monthStart) {
                $query->where('billed', true)->where('created_at', '>=', $monthStart);
            }])
            ->withSum(['aiUsageLogs as ai_month_cost' => function ($query) use ($monthStart) {
                $query->where('created_at', '>=', $monthStart);
            }], 'estimated_cost_brl')
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)->orWhere('email', 'like', $term);
                });
            })
            ->when($this->filter === 'pending', fn ($query) => $query->where('subscription_status', SubscriptionStatus::PENDING)->whereNull('approved_at'))
            ->when($this->filter === 'active', function ($query) {
                $query->where('active', true)
                    ->whereNotNull('approved_at')
                    ->whereIn('subscription_status', [SubscriptionStatus::TRIAL, SubscriptionStatus::ACTIVE])
                    ->where(function ($inner) {
                        $inner->whereNull('subscription_until')->orWhere('subscription_until', '>', now());
                    });
            })
            ->when($this->filter === 'paused', fn ($query) => $query->where('subscription_status', SubscriptionStatus::PAUSED))
            ->when($this->filter === 'expired', fn ($query) => $query->where('subscription_status', SubscriptionStatus::EXPIRED))
            ->when($this->filter === 'blocked', fn ($query) => $query->where('active', false))
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('livewire.admin.subscribers', [
            'users' => $users,
        ])->layout('layouts.app', [
            'title' => 'Usuários — VerifyRadar',
        ]);
    }
}
