<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Paid      = 'paid';
    case Unpaid    = 'unpaid';
    case Overdue   = 'overdue';
    case Cancelled = 'cancelled';
    case Draft     = 'draft';

    public static function fromWhmcs(string $status): self
    {
        return match (strtolower(trim($status))) {
            'paid'                  => self::Paid,
            'unpaid'                => self::Unpaid,
            'overdue'               => self::Overdue,
            'cancelled', 'refunded' => self::Cancelled,
            'draft'                 => self::Draft,
            default                 => self::Unpaid,
        };
    }

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }

    /** Invoice can be sent to the payment page */
    public function isPayable(): bool
    {
        return in_array($this, [self::Unpaid, self::Overdue], true);
    }

    public function isOverdue(): bool
    {
        return $this === self::Overdue;
    }
}
