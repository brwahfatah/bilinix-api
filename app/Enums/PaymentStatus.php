<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending    = 'pending';
    case Processing = 'processing';
    case Paid       = 'paid';
    case Failed     = 'failed';
    case Cancelled  = 'cancelled';
    case Refunded   = 'refunded';

    /**
     * Map Stripe Checkout Session status to our internal status.
     * Stripe statuses: open | complete | expired
     */
    public static function fromStripe(string $status): self
    {
        return match (strtolower(trim($status))) {
            'complete'      => self::Paid,
            'expired'       => self::Cancelled,
            'open'          => self::Pending,
            default         => self::Pending,
        };
    }

    /**
     * Map Stripe PaymentIntent status to our internal status.
     * Used for refund/status checks via intent rather than session.
     */
    public static function fromStripeIntent(string $status): self
    {
        return match (strtolower(trim($status))) {
            'succeeded'                                 => self::Paid,
            'processing'                                => self::Processing,
            'canceled'                                  => self::Cancelled,
            'requires_payment_method', 'requires_action' => self::Failed,
            default                                     => self::Pending,
        };
    }

    /** Payment cannot be changed further. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Paid, self::Failed, self::Cancelled, self::Refunded], true);
    }

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }
}
