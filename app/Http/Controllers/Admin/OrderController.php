<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AdminService $admin) {}

    /**
     * GET /api/admin/orders
     *
     * Returns all WHMCS orders across all clients (no ownership filter).
     */
    public function index(): JsonResponse
    {
        try {
            $orders = $this->admin->orders();
            return $this->success(
                array_map(fn($dto) => $dto->toArray(), $orders),
                'Orders retrieved'
            );
        } catch (RuntimeException $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * GET /api/admin/orders/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $order = $this->admin->order($id);
            return $this->success($order->toArray(), 'Order retrieved');
        } catch (RuntimeException $e) {
            return $this->notFound($e->getMessage());
        }
    }
}
