<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\ApiTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_returns_token_and_user_payload(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Paul Turpin',
            'email' => 'paul@example.com',
            'password' => 'password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'message',
                'token',
                'expiresAt',
                'user' => ['id', 'name', 'email'],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'paul@example.com',
        ]);
        $this->assertDatabaseCount('api_tokens', 1);
    }

    public function test_login_with_valid_credentials_returns_token(): void
    {
        $user = User::factory()->create([
            'email' => 'paul@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'paul@example.com',
            'password' => 'password123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['token', 'expiresAt']);

        $this->assertDatabaseCount('api_tokens', 1);
    }

    public function test_login_with_invalid_credentials_returns_unauthorized(): void
    {
        User::factory()->create([
            'email' => 'paul@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'paul@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized();
    }

    public function test_profile_requires_valid_api_token(): void
    {
        $response = $this->getJson('/api/auth/profile');

        $response->assertUnauthorized();
    }

    public function test_profile_returns_authenticated_user_with_valid_token(): void
    {
        $user = User::factory()->create();
        $token = app(ApiTokenService::class)->issueToken($user)['token'];

        $response = $this->withToken($token)->getJson('/api/auth/profile');

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = app(ApiTokenService::class)->issueToken($user)['token'];

        $logoutResponse = $this->withToken($token)->postJson('/api/auth/logout');
        $logoutResponse->assertOk();

        $profileResponse = $this->withToken($token)->getJson('/api/auth/profile');
        $profileResponse->assertUnauthorized();

        $this->assertDatabaseCount('api_tokens', 0);
    }

    public function test_register_is_rate_limited_after_five_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/register', ['email' => 'invalid']);
        }

        $response = $this->postJson('/api/auth/register', ['email' => 'invalid']);

        $response->assertStatus(429);
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        User::factory()->create(['email' => 'paul@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'paul@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->postJson('/api/auth/login', [
            'email' => 'paul@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
    }

    public function test_refresh_returns_new_token_and_revokes_old_one(): void
    {
        $user = User::factory()->create();
        $oldToken = app(ApiTokenService::class)->issueToken($user)['token'];

        $response = $this->withToken($oldToken)->postJson('/api/auth/refresh');

        $response
            ->assertOk()
            ->assertJsonStructure(['message', 'token', 'expiresAt']);

        $newToken = $response->json('token');

        $this->withToken($oldToken)->getJson('/api/auth/profile')->assertUnauthorized();
        $this->withToken($newToken)->getJson('/api/auth/profile')->assertOk();
        $this->assertDatabaseCount('api_tokens', 1);
    }

    public function test_refresh_requires_valid_token(): void
    {
        $response = $this->postJson('/api/auth/refresh');

        $response->assertUnauthorized();
    }
}
