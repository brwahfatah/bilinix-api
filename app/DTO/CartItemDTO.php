<?php

namespace App\DTO;

use App\Models\CartItem;

final class CartItemDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $productId,
        public readonly string $name,
        public readonly string $type,
        public readonly string $billingCycle,
        public readonly int    $quantity,
        public readonly string $unitPrice,
        public readonly string $total,
    ) {}

    public static function fromModel(CartItem $item): self
    {
        $unit  = (float) $item->unit_price;
        $total = $unit * $item->quantity;

        return new self(
            id:           (string) $item->id,
            productId:    $item->product_id,
            name:         $item->name,
            type:         $item->type,
            billingCycle: $item->billing_cycle,
            quantity:     $item->quantity,
            unitPrice:    number_format($unit, 2, '.', ''),
            total:        number_format($total, 2, '.', ''),
        );
    }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'product_id'    => $this->productId,
            'name'          => $this->name,
            'type'          => $this->type,
            'billing_cycle' => $this->billingCycle,
            'quantity'      => $this->quantity,
            'unit_price'    => $this->unitPrice,
            'total'         => $this->total,
        ];
    }
}
