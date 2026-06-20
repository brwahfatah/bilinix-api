<?php

namespace App\Http\Controllers\Cart;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddToCartRequest;
use App\Services\CartService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CartService $cart) {}

    /**
     * GET /api/cart
     * Returns current cart (auth user or guest). New guests receive a token to store.
     */
    public function show(Request $request): JsonResponse
    {
        $cart = $this->cart->resolve($request);
        return $this->success($this->cart->get($cart)->toArray(), 'Cart retrieved');
    }

    /**
     * POST /api/cart/items
     */
    public function addItem(AddToCartRequest $request): JsonResponse
    {
        $cart = $this->cart->resolve($request);
        $dto  = $this->cart->add($cart, $request->validated());

        return $this->success($dto->toArray(), 'Item added to cart');
    }

    /**
     * PATCH /api/cart/items/{id}
     */
    public function updateItem(Request $request, int $id): JsonResponse
    {
        $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:100']]);

        try {
            $cart = $this->cart->resolve($request);
            $dto  = $this->cart->update($cart, $id, (int) $request->quantity);
            return $this->success($dto->toArray(), 'Cart updated');
        } catch (ModelNotFoundException) {
            return $this->notFound('Cart item not found.');
        }
    }

    /**
     * DELETE /api/cart/items/{id}
     */
    public function removeItem(Request $request, int $id): JsonResponse
    {
        $cart = $this->cart->resolve($request);
        $dto  = $this->cart->remove($cart, $id);

        return $this->success($dto->toArray(), 'Item removed from cart');
    }

    /**
     * DELETE /api/cart
     */
    public function clear(Request $request): JsonResponse
    {
        $cart = $this->cart->resolve($request);
        $this->cart->clear($cart);

        return $this->noContent('Cart cleared');
    }
}
