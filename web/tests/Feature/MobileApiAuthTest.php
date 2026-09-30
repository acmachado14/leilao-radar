<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_issues_sanctum_token(): void
    {
        $user = User::factory()->create(['email' => 'ios@example.com']);

        $this->postJson('/api/v1/login', [
            'email' => 'ios@example.com',
            'password' => 'password',
        ])->assertOk()->assertJsonStructure(['token', 'user' => ['entitlement']]);

        $this->assertArrayNotHasKey(
            'checkout_url',
            $this->postJson('/api/v1/login', [
                'email' => 'ios@example.com',
                'password' => 'password',
            ])->json('user.entitlement'),
        );
    }

    public function test_account_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('ios')->plainTextToken;

        $this->withToken($token)->deleteJson('/api/v1/account')->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
