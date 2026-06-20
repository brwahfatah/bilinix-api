<?php

namespace Tests\Feature\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CartCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function userWithCart(array $items = []): array
    {
        $user  = User::factory()->create(['whmcs_client_id' => 1]);
        $token = $user->createToken('test')->plainTextToken;

        $cart = Cart::create([
            'user_id'       => $user->id,
            'session_token' => 'test-token-' . $user->id,
        ]);

        foreach ($items as $item) {
            CartItem::create(array_merge([
                'cart_id'       => $cart->id,
                'product_id'    => '3',
                'name'          => 'VPS 2GB',
                'type'          => 'server',
                'billing_cycle' => 'monthly',
                'quantity'      => 1,
                'unit_price'    => 9.99,
            ], $item));
        }

        return [
            'user'    => $user,
            'cart'    => $cart,
            'headers' => [
                'Authorization'  => "Bearer {$token}",
                'X-Cart-Token'   => $cart->session_token,
            ],
        ];
    }

    // ── M3: Zero-total checkout ───────────────────────────────────────────────

    public function test_checkout_rejects_zero_price_items(): void
    {
        ['headers' => $headers] = $this->userWithCart([
            ['unit_price' => 0.00],
        ]);

        $this->withHeaders($headers)
             ->postJson('/api/orders/checkout')
             ->assertStatus(422)
             ->assertJsonPath('message', 'Order total must be greater than zero.');
    }

    public function test_checkout_rejects_empty_cart(): void
    {
        ['headers' => $headers] = $this->userWithCart([]);

        $this->withHeaders($headers)
             ->postJson('/api/orders/checkout')
             ->assertStatus(422);
    }

    // ── M2: Quantity expansion ────────────────────────────────────────────────

    public function test_checkout_succeeds_with_positive_price(): void
    {
        ['headers' => $headers] = $this->userWithCart([
            ['unit_price' => 9.99, 'quantity' => 1],
        ]);

        $this->withHeaders($headers)
             ->postJson('/api/orders/checkout')
             ->assertStatus(201)
             ->assertJsonStructure(['data' => ['id', 'status']]);
    }

    public function test_checkout_succeeds_with_quantity_greater_than_one(): void
    {
        ['headers' => $headers] = $this->userWithCart([
            ['unit_price' => 9.99, 'quantity' => 3],
        ]);

        // 3 × $9.99 = $29.97 — should pass the zero-total check
        $this->withHeaders($headers)
             ->postJson('/api/orders/checkout')
             ->assertStatus(201);
    }

    // ── M6: Cart expiry ───────────────────────────────────────────────────────

    public function test_expired_guest_cart_is_ignored_on_resolve(): void
    {
        // Create an expired guest cart
        $cart = Cart::create([
            'user_id'       => null,
            'session_token' => 'expired-guest-token',
            'expires_at'    => now()->subHour(),
        ]);
        CartItem::create([
            'cart_id'       => $cart->id,
            'product_id'    => '3',
            'name'          => 'VPS 2GB',
            'type'          => 'server',
            'billing_cycle' => 'monthly',
            'quantity'      => 1,
            'unit_price'    => 9.99,
        ]);

        // GET /api/cart with the expired token should return a NEW empty cart,
        // not the expired one with items.
        $response = $this->withHeaders(['X-Cart-Token' => 'expired-guest-token'])
                         ->getJson('/api/cart');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.items'));
        $this->assertNotEquals('expired-guest-token', $response->json('data.token'));
    }

    public function test_active_guest_cart_is_returned(): void
    {
        $cart = Cart::create([
            'user_id'       => null,
            'session_token' => 'active-guest-token',
            'expires_at'    => now()->addDay(),
        ]);
        CartItem::create([
            'cart_id'       => $cart->id,
            'product_id'    => '3',
            'name'          => 'VPS 2GB',
            'type'          => 'server',
            'billing_cycle' => 'monthly',
            'quantity'      => 1,
            'unit_price'    => 9.99,
        ]);

        $response = $this->withHeaders(['X-Cart-Token' => 'active-guest-token'])
                         ->getJson('/api/cart');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.items'));
    }

    // ── M8: Checkout idempotency ──────────────────────────────────────────────

    public function test_duplicate_checkout_returns_cached_order(): void
    {
        ['headers' => $headers, 'cart' => $cart] = $this->userWithCart([
            ['unit_price' => 9.99, 'quantity' => 1],
        ]);

        // First checkout
        $first = $this->withHeaders($headers)
                      ->postJson('/api/orders/checkout');
        $first->assertStatus(201);

        // The cart items were cleared, but the idempotency cache is set.
        // A second POST with the same cart token must return the same order.
        $second = $this->withHeaders($headers)
                       ->postJson('/api/orders/checkout');
        $second->assertStatus(201);

        $this->assertEquals(
            $first->json('data.id'),
            $second->json('data.id'),
            'Duplicate checkout must return the same order ID'
        );
    }
}
