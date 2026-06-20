<?php

namespace App\Enums;

enum DomainStatus: string
{
    case Active          = 'active';
    case Expired         = 'expired';
    case Pending         = 'pending';
    case PendingTransfer = 'pending_transfer';
    case Redemption      = 'redemption';
    case Cancelled       = 'cancelled';

    public static function fromWhmcs(string $status): self
    {
        return match (strtolower(trim($status))) {
            'active'                                => self::Active,
            'expired', 'grace period'               => self::Expired,
            'pending', 'pending registration'       => self::Pending,
            'pending transfer'                      => self::PendingTransfer,
            'redemption', 'redemption grace period' => self::Redemption,
            'cancelled', 'canceled'                 => self::Cancelled,
            default                                 => self::Active,
        };
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public function isExpired(): bool
    {
        return in_array($this, [self::Expired, self::Redemption], true);
    }
}
