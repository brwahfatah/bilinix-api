<?php

namespace App\DTO;

use App\Models\Cart;

final class CartDTO
{
    public function __construct(
        public readonly string $token,
        /** @var CartItemDTO[] */
        public readonly array  $items,
        public readonly string $subtotal,
        public readonly string $tax,
        public readonly string $total,
    ) {}

    public static function fromCart(Cart $cart): self
    {
        $items = $cart->items
            ->map(fn($item) => CartItemDTO::fromModel($item))
            ->values()
            ->all();

        $subtotal = array_reduce(
            $items,
            fn(float $carry, CartItemDTO $item) => $carry + (float) $item->total,
            0.0
        );

        $tax = 0.0;

        return new self(
            token:    $cart->session_token ?? '',
            items:    $items,
            subtotal: number_format($subtotal, 2, '.', ''),
            tax:      number_format($tax, 2, '.', ''),
            total:    number_format($subtotal + $tax, 2, '.', ''),
        );
    }

    public function toArray(): array
    {
        return [
            'token'    => $this->token,
            'items'    => array_map(fn($item) => $item->toArray(), $this->items),
            'subtotal' => $this->subtotal,
            'tax'      => $this->tax,
            'total'    => $this->total,
        ];
    }
}
