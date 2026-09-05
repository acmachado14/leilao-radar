<?php

namespace App\Models;

use App\Constants\SubscriptionStatus;
use App\Constants\UserType;
use App\Mail\ResetPasswordMail;
use App\Models\Concerns\HasUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;

#[Fillable([
    'name',
    'email',
    'phone',
    'password',
    'type',
    'active',
    'subscription_status',
    'subscription_until',
    'approved_at',
    'rejected_at',
    'last_login_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuid, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'subscription_until' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        Mail::to($this->email)->queue(new ResetPasswordMail($this, $token));
    }

    public function isAdmin(): bool
    {
        if ($this->active === false) {
            return false;
        }

        if ($this->type === UserType::ADMIN) {
            return true;
        }

        $emails = config('app.admin_emails', []);

        if (! is_array($emails) || $emails === []) {
            return false;
        }

        return in_array(
            strtolower((string) $this->email),
            array_map('strtolower', $emails),
            true,
        );
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function isPending(): bool
    {
        if ($this->isAdmin()) {
            return false;
        }

        return $this->subscription_status === SubscriptionStatus::PENDING || $this->approved_at === null;
    }

    public function homeRoute(): string
    {
        if ($this->isAdmin()) {
            return 'admin.dashboard';
        }

        if ($this->isPending()) {
            return 'aguardando';
        }

        return 'dashboard';
    }

    public function canReceiveAlerts(): bool
    {
        if ($this->active === false || ! $this->isApproved()) {
            return false;
        }

        if (! in_array($this->subscription_status, [SubscriptionStatus::TRIAL, SubscriptionStatus::ACTIVE], true)) {
            return false;
        }

        if ($this->subscription_until === null) {
            return true;
        }

        return $this->subscription_until->isFuture();
    }

    public function subscriptionLabel(): string
    {
        return match ($this->subscription_status) {
            SubscriptionStatus::PENDING => 'Pendente',
            SubscriptionStatus::TRIAL => 'Trial',
            SubscriptionStatus::ACTIVE => 'Ativa',
            SubscriptionStatus::PAUSED => 'Pausada',
            SubscriptionStatus::EXPIRED => 'Expirada',
            SubscriptionStatus::REJECTED => 'Recusada',
            default => $this->subscription_status,
        };
    }

    public function alertPreferences(): HasMany
    {
        return $this->hasMany(AlertPreference::class)->orderBy('created_at');
    }

    public function alertPreference(): HasOne
    {
        return $this->hasOne(AlertPreference::class)->oldestOfMany();
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(AdminActivityLog::class, 'subject_user_id');
    }

    public function lotAlertSends(): HasMany
    {
        return $this->hasMany(LotAlertSend::class);
    }
}
