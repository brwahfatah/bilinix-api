<?php

namespace App\Services;

use App\DTO\CartDTO;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CartService
{
    /**
     * Resolve the correct cart for the current request.
     *
     * Priority:
     *  1. Authenticated user → their user cart (or adopt their guest cart if X-Cart-Token matches)
     *  2. Guest with X-Cart-Token header → their existing guest cart
     *  3. New guest → fresh cart with a generated token
     */
    public function resolve(Request $request): Cart
    {
        // ── Authenticated user ───────────────────────────────────────────────
        $user = $request->user('sanctum');

        if ($user) {
            // Check for existing user cart
            $userCart = Cart::where('user_id', $user->id)->with('items')->first();
            if ($userCart) {
                return $userCart;
            }

            // Adopt guest cart on first authenticated request (merge on login).
            // Only active (non-expired) guest carts are eligible for adoption.
            $token     = $request->header('X-Cart-Token');
            $guestCart = $token
                ? Cart::active()->whereNull('user_id')->where('session_token', $token)->with('items')->first()
                : null;

            if ($guestCart) {
                $guestCart->update(['user_id' => $user->id]);
                return $guestCart->fresh(['items']);
            }

            // Create fresh cart for this user
            return Cart::create([
                'user_id'       => $user->id,
                'session_token' => Str::uuid()->toString(),
            ]);
        }

        // ── Guest ────────────────────────────────────────────────────────────
        $token = $request->header('X-Cart-Token');

        if ($token) {
            $cart = Cart::active()
                        ->whereNull('user_id')
                        ->where('session_token', $token)
                        ->with('items')
                        ->first();
            if ($cart) {
                return $cart;
            }
        }

        // New guest: create a cart and surface the token in the CartDTO response
        return Cart::create([
            'user_id'       => null,
            'session_token' => Str::uuid()->toString(),
            'expires_at'    => now()->addDays(7),
        ]);
    }

    public function get(Cart $cart): CartDTO
    {
        $cart->loadMissing('items');
        return CartDTO::fromCart($cart);
    }

    /**
     * Add a product to the cart.
     * If an identical product+cycle already exists, increments quantity.
     */
    public function add(Cart $cart, array $data): CartDTO
    {
        $existing = $cart->items()
            ->where('product_id', $data['product_id'])
            ->where('billing_cycle', $data['billing_cycle'])
            ->first();

        if ($existing) {
            $existing->increment('quantity', $data['quantity'] ?? 1);
        } else {
            $cart->items()->create([
                'product_id'    => $data['product_id'],
                'name'          => $data['name'],
                'type'          => $data['type'] ?? 'other',
                'billing_cycle' => $data['billing_cycle'],
                'quantity'      => $data['quantity'] ?? 1,
                'unit_price'    => $data['unit_price'],
            ]);
        }

        return CartDTO::fromCart($cart->fresh(['items']));
    }

    /**
     * Update the quantity of a specific cart item.
     * Verifies the item belongs to this cart via the relationship query.
     */
    public function update(Cart $cart, int $itemId, int $quantity): CartDTO
    {
        $cart->items()->findOrFail($itemId)->update(['quantity' => $quantity]);

        return CartDTO::fromCart($cart->fresh(['items']));
    }

    /**
     * Remove a specific item from the cart.
     */
    public function remove(Cart $cart, int $itemId): CartDTO
    {
        $cart->items()->where('id', $itemId)->delete();

        return CartDTO::fromCart($cart->fresh(['items']));
    }

    /**
     * Remove all items from the cart (preserves the cart record itself).
     */
    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
    }
}
