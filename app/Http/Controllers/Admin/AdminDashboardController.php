<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class AdminDashboardController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AdminService $admin) {}

    /**
     * GET /api/admin/dashboard
     *
     * Returns platform-wide aggregate stats:
     *   - Users count from Laravel DB
     *   - Active VPS / Domains / Open Tickets / Unpaid Invoices from WHMCS
     *   - Current-month revenue from WHMCS paid invoices
     */
    public function summary(): JsonResponse
    {
        try {
            $dto = $this->admin->dashboard();
            return $this->success($dto->toArray(), 'Dashboard data retrieved');
        } catch (RuntimeException $e) {
            return $this->serverError($e->getMessage());
        }
    }
}
