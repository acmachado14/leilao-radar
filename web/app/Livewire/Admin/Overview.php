<?php

namespace App\Livewire\Admin;

use App\Constants\SubscriptionStatus;
use App\Livewire\Admin\Concerns\ManagesSubscribers;
use App\Models\AdminActivityLog;
use App\Models\Lot;
use App\Models\User;
use Livewire\Component;

class Overview extends Component
{
    use ManagesSubscribers;

    public function render()
    {
        $pending = User::query()->where('subscription_status', SubscriptionStatus::PENDING)->whereNull('approved_at');

        return view('livewire.admin.overview', [
            'stats' => [
                'pending' => (clone $pending)->count(),
                'active' => User::query()
                    ->where('active', true)
                    ->whereNotNull('approved_at')
                    ->whereIn('subscription_status', [SubscriptionStatus::TRIAL, SubscriptionStatus::ACTIVE])
                    ->where(function ($query) {
                        $query->whereNull('subscription_until')->orWhere('subscription_until', '>', now());
                    })
                    ->count(),
                'paused' => User::query()->where('subscription_status', SubscriptionStatus::PAUSED)->count(),
                'expired' => User::query()->where('subscription_status', SubscriptionStatus::EXPIRED)->count(),
                'blocked' => User::query()->where('active', false)->count(),
                'lots' => Lot::query()->count(),
            ],
            'pendingUsers' => (clone $pending)->orderByDesc('created_at')->limit(8)->get(),
            'recentLogs' => AdminActivityLog::query()
                ->with(['actor', 'subject'])
                ->orderByDesc('created_at')
                ->limit(12)
                ->get(),
        ])->layout('layouts.app', [
            'title' => 'Admin — VerifyRadar',
        ]);
    }
}
