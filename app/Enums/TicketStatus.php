<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open          = 'open';
    case Answered      = 'answered';
    case CustomerReply = 'customer_reply';
    case Closed        = 'closed';

    public static function fromWhmcs(string $status): self
    {
        return match (strtolower(trim($status))) {
            'open', 'on hold'                     => self::Open,
            'answered'                            => self::Answered,
            'customer-reply', 'customer reply'    => self::CustomerReply,
            'closed'                              => self::Closed,
            default                               => self::Open,
        };
    }

    /** Ticket is actionable (not closed) */
    public function isOpen(): bool
    {
        return in_array($this, [self::Open, self::Answered, self::CustomerReply], true);
    }

    public function isClosed(): bool
    {
        return $this === self::Closed;
    }
}
