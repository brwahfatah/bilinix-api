<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending   = 'pending';
    case Active    = 'active';
    case Fraud     = 'fraud';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public static function fromWhmcs(string $status): self
    {
        return match (strtolower(trim($status))) {
            'pending'                  => self::Pending,
            'active'                   => self::Active,
            'fraud'                    => self::Fraud,
            'cancelled', 'canceled'    => self::Cancelled,
            'completed'                => self::Completed,
            default                    => self::Pending,
        };
    }
}
