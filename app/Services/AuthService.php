<?php

namespace App\Services;

use App\DTO\UserDTO;
use App\Integrations\WhmcsService;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AuthService
{
    public function __construct(private readonly WhmcsService $whmcs) {}

    /**
     * Register a new client account and issue a token.
     *
     * In real WHMCS mode the operation is atomic: the local user row and the
     * WHMCS client record are both created inside a single DB transaction.
     * If WHMCS rejects the request or is unreachable the transaction is rolled
     * back and no local user is persisted.
     *
     * In fake mode FakeWhmcsService::createClient() returns clientid = 1
     * immediately, so behaviour is unchanged from the previous implementation.
     *
     * @return array{ user: array, token: string }
     * @throws RuntimeException  when WHMCS does not return a valid client ID
     */
    public function register(string $name, string $email, string $password): array
    {
        return DB::transaction(function () use ($name, $email, $password) {
            $user = User::create([
                'name'            => $name,
                'email'           => $email,
                'password'        => Hash::make($password),
                'role'            => 'client',
                'status'          => 'active',
                'whmcs_client_id' => null,
            ]);

            $result   = $this->whmcs->createClient(['email' => $email, 'firstname' => $name]);
            $clientId = (int) ($result['clientid'] ?? 0);

            if ($clientId <= 0) {
                throw new RuntimeException('WHMCS did not return a valid client ID.');
            }

            $user->update(['whmcs_client_id' => $clientId]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return [
                'user'  => UserDTO::from($user->fresh())->toArray(),
                'token' => $token,
            ];
        });
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
     * Trigger a WHMCS password-reset email for the given address.
     *
     * All exceptions from WHMCS are swallowed deliberately: the controller
     * always returns a generic success message to prevent email-enumeration.
     */
    public function forgotPassword(string $email): void
    {
        try {
            $this->whmcs->resetPassword($email);
        } catch (\Throwable) {
            // Intentional no-op — never reveal whether the address exists.
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
