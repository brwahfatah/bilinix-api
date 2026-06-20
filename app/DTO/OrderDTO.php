<?php

namespace App\DTO;

use App\Enums\OrderStatus;
use Carbon\Carbon;

final class OrderDTO
{
    public function __construct(
        public readonly string  $id,
        public readonly string  $status,
        public readonly ?string $invoiceId,
        public readonly string  $total,
        public readonly string  $createdAt,
        public readonly array   $items,
    ) {}

    /**
     * Accepts both AddOrder response (checkout) and GetOrders item (list/detail).
     */
    public static function fromWhmcs(array $data): self
    {
        $status = OrderStatus::fromWhmcs($data['status'] ?? 'pending');

        // AddOrder returns 'orderid'; GetOrders items return 'id'
        $id        = (string) ($data['orderid'] ?? $data['id'] ?? '');
        $invoiceId = ! empty($data['invoiceid']) ? (string) $data['invoiceid'] : null;

        return new self(
            id:        $id,
            status:    $status->value,
            invoiceId: $invoiceId,
            total:     number_format((float) ($data['amount'] ?? 0), 2, '.', ''),
            createdAt: self::parseDate($data['date'] ?? null) ?? now()->toIso8601String(),
            items:     self::parseItems($data['lineitems'] ?? []),
        );
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'status'     => $this->status,
            'invoice_id' => $this->invoiceId,
            'total'      => $this->total,
            'created_at' => $this->createdAt,
            'items'      => $this->items,
        ];
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Parse lineitems.lineitem from GetOrders response.
     * AddOrder responses have no lineitems → returns empty array.
     */
    private static function parseItems(mixed $lineitems): array
    {
        if (empty($lineitems)) {
            return [];
        }

        $raw = is_array($lineitems) && isset($lineitems['lineitem'])
            ? $lineitems['lineitem']
            : $lineitems;

        if (empty($raw) || ! is_array($raw)) {
            return [];
        }

        $list = array_is_list($raw) ? $raw : [$raw];

        return array_values(array_map(
            fn($item) => [
                'product'       => $item['product'] ?? $item['producttype'] ?? '',
                'type'          => $item['type'] ?? '',
                'domain'        => $item['domain'] ?? '',
                'billing_cycle' => strtolower($item['billingcycle'] ?? ''),
                'amount'        => number_format((float) ($item['amount'] ?? 0), 2, '.', ''),
            ],
            array_filter($list, 'is_array'),
        ));
    }

    private static function parseDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        try {
            return Carbon::parse($date)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}
