<?php

namespace Tests\Feature\Auth;

use App\Integrations\WhmcsService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Register — base behaviour ─────────────────────────────────────────────

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

    // ── Register — fake mode (ENABLE_DEV_MOCKS=true in phpunit.xml) ───────────

    public function test_register_fake_mode_assigns_whmcs_client_id_1(): void
    {
        // phpunit.xml sets ENABLE_DEV_MOCKS=true → FakeWhmcsService is bound.
        // FakeWhmcsService::createClient() returns ['clientid' => 1].
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Fake User',
            'email'                 => 'fakemode@example.com',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email'           => 'fakemode@example.com',
            'whmcs_client_id' => 1,
        ]);
    }

    // ── Register — real mode: WHMCS returns a valid client ID ─────────────────

    public function test_register_real_mode_stores_whmcs_client_id(): void
    {
        $mock = $this->mock(WhmcsService::class);
        $mock->shouldReceive('createClient')
             ->once()
             ->andReturn(['result' => 'success', 'clientid' => 42]);

        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Real User',
            'email'                 => 'realmode@example.com',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.user.email', 'realmode@example.com');

        $this->assertDatabaseHas('users', [
            'email'           => 'realmode@example.com',
            'whmcs_client_id' => 42,
        ]);
    }

    // ── Register — real mode: WHMCS fails → transaction rolled back ───────────

    public function test_register_whmcs_failure_rolls_back_user_creation(): void
    {
        $mock = $this->mock(WhmcsService::class);
        $mock->shouldReceive('createClient')
             ->once()
             ->andThrow(new RuntimeException('WHMCS connection refused'));

        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Rollback User',
            'email'                 => 'rollback@example.com',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertStatus(503);

        // The DB transaction must have been rolled back — no user row persisted.
        $this->assertDatabaseMissing('users', ['email' => 'rollback@example.com']);
    }

    // ── Login ─────────────────────────────────────────────────────────────────

    public function test_login_returns_token_for_valid_credentials(): void
    {
        User::factory()->create([
            'email'    => 'test@example.com',
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

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id'   => $user->id,
            'tokenable_type' => \App\Models\User::class,
            'name'           => 'test',
        ]);
    }

    // ── Forgot password — WHMCS reset email dispatched ────────────────────────

    public function test_forgot_password_returns_success_for_any_email(): void
    {
        // FakeWhmcsService::resetPassword() is a no-op — endpoint returns 200.
        $this->postJson('/api/auth/forgot-password', ['email' => 'anyone@example.com'])
             ->assertStatus(200)
             ->assertJsonPath('message', 'If an account exists with that email, a reset link has been sent.');
    }

    public function test_forgot_password_calls_whmcs_reset_password(): void
    {
        $mock = $this->mock(WhmcsService::class);
        $mock->shouldReceive('resetPassword')
             ->once()
             ->with('wired@example.com');

        $this->postJson('/api/auth/forgot-password', ['email' => 'wired@example.com'])
             ->assertStatus(200);
    }

    // ── Forgot password — WHMCS failure still returns 200 ────────────────────

    public function test_forgot_password_whmcs_failure_still_returns_200(): void
    {
        // Even if WHMCS is unreachable the endpoint must not expose this detail.
        $mock = $this->mock(WhmcsService::class);
        $mock->shouldReceive('resetPassword')
             ->once()
             ->andThrow(new RuntimeException('WHMCS API error'));

        $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])
             ->assertStatus(200)
             ->assertJsonPath('message', 'If an account exists with that email, a reset link has been sent.');
    }
}
