<?php

namespace App\DTO;

final class InvoiceItemDTO
{
    public function __construct(
        public readonly string $description,
        public readonly string $amount,
    ) {}

    public static function fromWhmcs(array $item): self
    {
        return new self(
            description: $item['description'] ?? $item['type'] ?? 'Service',
            amount:      number_format((float) ($item['amount'] ?? 0), 2, '.', ''),
        );
    }

    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'amount'      => $this->amount,
        ];
    }
}
