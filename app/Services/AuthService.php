<?php

namespace App\Services;

use App\DTO\UserDTO;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    /**
     * Register a new client account and issue a token.
     *
     * @return array{ user: array, token: string }
     */
    public function register(string $name, string $email, string $password): array
    {
        $user = User::create([
            'name'           => $name,
            'email'          => $email,
            'password'       => Hash::make($password),
            'role'           => 'client',
            'status'         => 'active',
            // In dev-mock mode all fake WHMCS records belong to client ID 1;
            // assign it automatically so VPS/billing/domain/ticket services work.
            'whmcs_client_id' => env('ENABLE_DEV_MOCKS', false) ? 1 : null,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user'  => UserDTO::from($user)->toArray(),
            'token' => $token,
        ];
    }

    /**
     * Validate credentials and issue a new token.
     *
     * @return array{ user: array, token: string }
     * @throws AuthenticationException
     */
    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new AuthenticationException('Invalid email or password.');
        }

        // Revoke previous tokens issued for this device name to avoid accumulation
        $user->tokens()->where('name', 'auth_token')->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user'  => UserDTO::from($user)->toArray(),
            'token' => $token,
        ];
    }

    /**
     * Revoke the current access token (single-device logout).
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    /**
     * Revoke all tokens (all-device logout).
     */
    public function logoutAll(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * Trigger WHMCS password reset email.
     * Actual WHMCS call will be injected via WhmcsService when wired up.
     */
    public function forgotPassword(string $email): void
    {
        // Placeholder — will delegate to WhmcsService::resetPassword($email)
        // in the next integration step.
        $user = User::where('email', $email)->first();
        if ($user) {
            // TODO: dispatch PasswordResetEmail notification
        }
    }

    /**
     * Update display name for authenticated user.
     */
    public function updateProfile(User $user, string $name): UserDTO
    {
        $user->update(['name' => $name]);
        return UserDTO::from($user->fresh());
    }

    /**
     * Change password after verifying the current one.
     *
     * @throws AuthenticationException
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw new AuthenticationException('Current password is incorrect.');
        }

        $user->update(['password' => Hash::make($newPassword)]);
        // Revoke all other tokens so other sessions must re-authenticate
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();
    }
}
