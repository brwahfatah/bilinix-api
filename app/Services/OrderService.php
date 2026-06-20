<?php

namespace App\Services;

use App\DTO\OrderDTO;
use App\Integrations\WhmcsService;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OrderService
{
    public function __construct(private readonly WhmcsService $whmcs) {}

    /**
     * Convert the authenticated user's cart into a WHMCS order.
     * Clears the cart on success.
     */
    public function checkout(User $user, Cart $cart, string $paymentMethod = 'banktransfer'): OrderDTO
    {
        $this->requireWhmcsClient($user);

        // ── M8: Idempotency — return cached result on retry ─────────────────
        $idempotencyKey = 'checkout:idempotency:' . $cart->session_token;
        if ($cachedRaw = Cache::get($idempotencyKey)) {
            return OrderDTO::fromWhmcs($cachedRaw);
        }

        $cart->loadMissing('items');

        if ($cart->items->isEmpty()) {
            throw new RuntimeException('Your cart is empty. Please add products before checking out.');
        }

        // Only numeric product IDs map to WHMCS products; skip domain slugs etc.
        $orderable = $cart->items->filter(fn($item) => is_numeric($item->product_id));

        if ($orderable->isEmpty()) {
            throw new RuntimeException('Cart contains no WHMCS-orderable products.');
        }

        // ── M3: Reject zero-total checkout ──────────────────────────────────
        $calculatedTotal = $orderable->sum(fn($item) => (float) $item->unit_price * (int) $item->quantity);
        if ($calculatedTotal <= 0.0) {
            throw new RuntimeException('Order total must be greater than zero.');
        }

        // ── M2: Expand by quantity so WHMCS receives one PID per unit ───────
        $pids   = [];
        $cycles = [];
        foreach ($orderable as $item) {
            $qty = max(1, (int) $item->quantity);
            for ($i = 0; $i < $qty; $i++) {
                $pids[]   = (int) $item->product_id;
                $cycles[] = $item->billing_cycle;
            }
        }

        $result = $this->whmcs->createOrder((int) $user->whmcs_client_id, [
            'pid'            => $pids,
            'billingcycle'   => $cycles,
            'payment_method' => $paymentMethod,
        ]);

        $orderId = (int) ($result['orderid'] ?? 0);
        if ($orderId <= 0) {
            throw new RuntimeException('WHMCS did not return a valid order ID.');
        }

        // Clear the cart after the order is placed
        $cart->items()->delete();

        // Re-fetch for full order detail (status, invoice ID, line items)
        try {
            $orderRaw = $this->whmcs->getOrder($orderId);
        } catch (\Throwable) {
            // Fallback: build from AddOrder response (status will be 'pending')
            $orderRaw = array_merge($result, ['status' => 'pending']);
        }

        // Cache so a network-retry returns the same order instead of creating a second one
        Cache::put($idempotencyKey, $orderRaw, now()->addDay());

        return OrderDTO::fromWhmcs($orderRaw);
    }

    /**
     * Return all orders for the authenticated user.
     *
     * @return OrderDTO[]
     */
    public function list(User $user): array
    {
        if (! $user->whmcs_client_id) {
            return [];
        }

        $orders = $this->whmcs->getOrders((int) $user->whmcs_client_id);

        return array_map(
            fn(array $order) => OrderDTO::fromWhmcs($order),
            $orders
        );
    }

    /**
     * Return a single order, verifying the authenticated user owns it.
     */
    public function get(User $user, int $orderId): OrderDTO
    {
        $raw = $this->whmcs->getOrder($orderId);
        $this->authorize($user, $raw);

        return OrderDTO::fromWhmcs($raw);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Verify the WHMCS order belongs to the authenticated user.
     * GetOrders returns 'userid' per order record.
     */
    private function authorize(User $user, array $raw): void
    {
        if (! $user->whmcs_client_id) {
            Log::warning('Authorization denied: no WHMCS client linked', [
                'user_id'       => $user->id,
                'resource_type' => 'order',
                'resource_id'   => $raw['id'] ?? $raw['orderid'] ?? null,
                'ip'            => request()->ip(),
            ]);
            throw new RuntimeException('Order not found.');
        }

        if ((int) ($raw['userid'] ?? -1) !== (int) $user->whmcs_client_id) {
            Log::warning('Authorization denied: order ownership mismatch', [
                'user_id'           => $user->id,
                'resource_type'     => 'order',
                'resource_id'       => $raw['id'] ?? $raw['orderid'] ?? null,
                'ip'                => request()->ip(),
                'owner_client_id'   => $raw['userid'] ?? null,
                'request_client_id' => $user->whmcs_client_id,
            ]);
            throw new RuntimeException('Order not found.');
        }
    }

    private function requireWhmcsClient(User $user): void
    {
        if (! $user->whmcs_client_id) {
            throw new RuntimeException('No WHMCS account is linked to this user.');
        }
    }
}
