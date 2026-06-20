<?php

namespace App\Http\Controllers\Auth;

use App\DTO\UserDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuthService $auth) {}

    /**
     * POST /api/auth/register
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->auth->register(
            name:     $request->name,
            email:    $request->email,
            password: $request->password,
        );

        return $this->created($result, 'Account created successfully');
    }

    /**
     * POST /api/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->auth->login($request->email, $request->password);
            return $this->success($result, 'Login successful');
        } catch (AuthenticationException $e) {
            return $this->unauthorized($e->getMessage());
        }
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());
        return $this->noContent('Logged out successfully');
    }

    /**
     * POST /api/auth/logout-all
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $this->auth->logoutAll($request->user());
        return $this->noContent('Logged out from all devices');
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success(UserDTO::from($request->user())->toArray());
    }

    /**
     * POST /api/auth/forgot-password
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->auth->forgotPassword($request->email);
        // Always return success to prevent email enumeration attacks
        return $this->success(null, 'If an account exists with that email, a reset link has been sent.');
    }

    /**
     * PATCH /api/auth/profile
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $dto = $this->auth->updateProfile($request->user(), $request->name);
        return $this->success($dto->toArray(), 'Profile updated successfully');
    }

    /**
     * POST /api/auth/change-password
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            $this->auth->changePassword(
                user:            $request->user(),
                currentPassword: $request->current_password,
                newPassword:     $request->new_password,
            );
            return $this->success(null, 'Password changed successfully');
        } catch (AuthenticationException $e) {
            return $this->error($e->getMessage(), null, 422);
        }
    }
}
