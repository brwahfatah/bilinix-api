<?php

namespace App\Enums;

enum TicketPriority: string
{
    case Low    = 'low';
    case Medium = 'medium';
    case High   = 'high';

    public static function fromWhmcs(string $priority): self
    {
        return match (strtolower(trim($priority))) {
            'low'                  => self::Low,
            'medium', 'normal', '' => self::Medium,
            'high', 'urgent'       => self::High,
            default                => self::Medium,
        };
    }
}
