<?php

namespace App\DTO;

use App\Enums\InvoiceStatus;
use Carbon\Carbon;

final class InvoiceDTO
{
    public function __construct(
        public readonly string  $id,
        public readonly string  $number,
        public readonly string  $status,
        public readonly string  $subtotal,
        public readonly string  $tax,
        public readonly string  $total,
        public readonly ?string $dueDate,
        public readonly ?string $paidAt,
        public readonly ?string $notes,
        /** @var InvoiceItemDTO[] */
        public readonly array   $items,
    ) {}

    public static function fromWhmcs(array $data): self
    {
        $status = InvoiceStatus::fromWhmcs($data['status'] ?? '');

        // Secondary overdue detection: WHMCS may not have run its cron yet
        if ($status === InvoiceStatus::Unpaid && ! empty($data['duedate'])) {
            try {
                if (Carbon::parse($data['duedate'])->isPast()) {
                    $status = InvoiceStatus::Overdue;
                }
            } catch (\Throwable) {
                // Unparseable date — leave status as-is
            }
        }

        // GetInvoice returns 'invoiceid'; GetInvoices list items return 'id'
        $id     = (string) ($data['invoiceid'] ?? $data['id'] ?? '');
        $number = (string) ($data['invoicenum'] ?? $id);

        $items = self::parseItems($data['items'] ?? []);

        return new self(
            id:       $id,
            number:   $number,
            status:   $status->value,
            subtotal: number_format((float) ($data['subtotal'] ?? 0), 2, '.', ''),
            tax:      number_format((float) ($data['tax'] ?? 0), 2, '.', ''),
            total:    number_format((float) ($data['total'] ?? 0), 2, '.', ''),
            dueDate:  self::parseDate($data['duedate'] ?? null),
            paidAt:   self::parseDate($data['datepaid'] ?? null),
            notes:    ! empty($data['notes']) ? $data['notes'] : null,
            items:    $items,
        );
    }

    public function toArray(): array
    {
        return [
            'id'       => $this->id,
            'number'   => $this->number,
            'status'   => $this->status,
            'subtotal' => $this->subtotal,
            'tax'      => $this->tax,
            'total'    => $this->total,
            'due_date' => $this->dueDate,
            'paid_at'  => $this->paidAt,
            'notes'    => $this->notes,
            'items'    => array_map(fn($item) => $item->toArray(), $this->items),
        ];
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Normalize WHMCS item structure.
     * GetInvoice returns items.item as either a single object or an array of objects.
     *
     * @return InvoiceItemDTO[]
     */
    private static function parseItems(mixed $items): array
    {
        if (empty($items)) {
            return [];
        }

        // items.item wrapping (from GetInvoice)
        if (is_array($items) && isset($items['item'])) {
            $raw = $items['item'];
            // Single item comes as an associative array, not a list
            $list = (isset($raw[0]) || empty($raw)) ? $raw : [$raw];
        } elseif (is_array($items) && array_is_list($items)) {
            $list = $items;
        } else {
            return [];
        }

        return array_values(array_map(
            fn($item) => InvoiceItemDTO::fromWhmcs($item),
            array_filter($list, 'is_array'),
        ));
    }

    private static function parseDate(?string $date): ?string
    {
        if (empty($date) || $date === '0000-00-00') {
            return null;
        }

        try {
            return Carbon::parse($date)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}
