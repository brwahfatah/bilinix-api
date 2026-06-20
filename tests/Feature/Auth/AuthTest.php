<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Register ──────────────────────────────────────────────────────────────

    public function test_register_creates_user_and_returns_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Jane Doe',
            'email'                 => 'jane@example.com',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'data' => ['user', 'token'],
                     'message',
                     'errors',
                 ])
                 ->assertJsonPath('data.user.email', 'jane@example.com')
                 ->assertJsonPath('errors', null);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dupe@example.com']);

        $this->postJson('/api/auth/register', [
            'name'                  => 'Dup',
            'email'                 => 'dupe@example.com',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ])->assertStatus(422);
    }

    // ── Login ─────────────────────────────────────────────────────────────────

    public function test_login_returns_token_for_valid_credentials(): void
    {
        User::factory()->create([
            'email'  => 'test@example.com',
            'password' => bcrypt('Password1!'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'test@example.com',
            'password' => 'Password1!',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => ['token', 'user']])
                 ->assertJsonPath('errors', null);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'test2@example.com', 'password' => bcrypt('right')]);

        $this->postJson('/api/auth/login', [
            'email'    => 'test2@example.com',
            'password' => 'wrong',
        ])->assertStatus(401);
    }

    // ── Me ────────────────────────────────────────────────────────────────────

    public function test_me_returns_authenticated_user(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
             ->getJson('/api/auth/me')
             ->assertStatus(200)
             ->assertJsonPath('data.email', $user->email);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function test_logout_revokes_current_token(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
             ->postJson('/api/auth/logout')
             ->assertStatus(200);

        // Verify the token was physically deleted from the DB
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id'   => $user->id,
            'tokenable_type' => \App\Models\User::class,
            'name'           => 'test',
        ]);
    }
}
