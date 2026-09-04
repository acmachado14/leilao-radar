<?php

namespace Database\Factories;

use App\Constants\SubscriptionStatus;
use App\Constants\UserType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'type' => UserType::USER,
            'active' => true,
            'subscription_status' => SubscriptionStatus::TRIAL,
            'subscription_until' => now()->addDays(7),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserType::ADMIN,
            'active' => true,
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_status' => SubscriptionStatus::PAUSED,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
