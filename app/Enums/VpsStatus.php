<?php

namespace App\Enums;

enum VpsStatus: string
{
    case Running    = 'running';
    case Stopped    = 'stopped';
    case Suspended  = 'suspended';
    case Pending    = 'pending';
    case Terminated = 'terminated';

    public static function fromWhmcs(string $status): self
    {
        return match (strtolower(trim($status))) {
            'active'                  => self::Running,
            'suspended'               => self::Suspended,
            'terminated', 'cancelled' => self::Terminated,
            'pending'                 => self::Pending,
            default                   => self::Stopped,
        };
    }
}
