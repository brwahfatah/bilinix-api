<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AdminService $admin) {}

    /**
     * GET /api/admin/users?per_page=20
     *
     * Paginated list of all registered users (Laravel DB).
     * Never exposes password or remember_token.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 100);
        $users   = $this->admin->users($perPage);

        return $this->success($users->toArray(), 'Users retrieved');
    }

    /**
     * GET /api/admin/users/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = $this->admin->user($id);
            return $this->success($user->toArray(), 'User retrieved');
        } catch (ModelNotFoundException) {
            return $this->notFound('User not found.');
        }
    }
}
