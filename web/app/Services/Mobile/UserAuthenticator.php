<?php

namespace App\Services\Mobile;

use App\Constants\EntitlementSource;
use App\Constants\Plan;
use App\Constants\SubscriptionStatus;
use App\Models\AlertPreference;
use App\Models\User;
use App\Services\Admin\AdminAuditor;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class UserAuthenticator
{
    public function __construct(private AdminAuditor $auditor) {}

    /**
     * @return array{user: User, token: string}
     */
    public function register(array $payload): array
    {
        $user = User::query()->create([
            'name' => $payload['name'],
            'email' => strtolower(trim((string) $payload['email'])),
            'phone' => $payload['phone'] ?? null,
            'password' => $payload['password'],
            'active' => true,
            'subscription_status' => SubscriptionStatus::TRIAL,
            'plan' => Plan::TRIAL,
            'entitlement_source' => EntitlementSource::TRIAL,
            'subscription_until' => null,
            'approved_at' => now(),
            'last_login_at' => now(),
        ]);

        $defaults = AlertPreference::defaults();
        $user->alertPreference()->create($defaults);

        $this->auditor->record('registered', "Novo cadastro iOS (conta grátis): {$user->name} ({$user->email})", $user);

        return $this->issue($user);
    }

    /**
     * @return array{user: User, token: string}
     */
    public function login(string $email, string $password): array
    {
        $user = User::query()->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])->first();

        if ($user === null || ! Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'E-mail ou senha inválidos.',
            ]);
        }

        if ($user->active === false) {
            throw ValidationException::withMessages([
                'email' => 'Esta conta está desativada.',
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return $this->issue($user);
    }

    public function sendResetLink(string $email): void
    {
        Password::sendResetLink(['email' => strtolower(trim($email))]);
    }

    /**
     * @return array{user: User, token: string}
     */
    public function issue(User $user): array
    {
        $user->tokens()->where('name', 'ios')->delete();

        return [
            'user' => $user,
            'token' => $user->createToken('ios')->plainTextToken,
        ];
    }
}
