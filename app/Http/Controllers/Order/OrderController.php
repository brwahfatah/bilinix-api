<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Services\OrderService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly OrderService $orders,
        private readonly CartService  $cart,
    ) {}

    /**
     * POST /api/orders/checkout
     */
    public function checkout(Request $request): JsonResponse
    {
        try {
            $cart  = $this->cart->resolve($request);
            $order = $this->orders->checkout(
                $request->user(),
                $cart,
                $request->input('payment_method', 'banktransfer'),
            );

            return $this->created($order->toArray(), 'Order placed successfully');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        }
    }

    /**
     * GET /api/orders
     */
    public function index(Request $request): JsonResponse
    {
        $list = $this->orders->list($request->user());

        return $this->success(
            array_map(fn($dto) => $dto->toArray(), $list),
            'Orders retrieved'
        );
    }

    /**
     * GET /api/orders/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $order = $this->orders->get($request->user(), $id);
            return $this->success($order->toArray(), 'Order retrieved');
        } catch (RuntimeException $e) {
            return $this->notFound($e->getMessage());
        }
    }
}
