<?php

namespace App\Services\Vps;

use App\Services\Vps\Providers\LocalProvider;
use InvalidArgumentException;

class VpsProviderFactory
{
    public static function make(string $provider)
    {
        return match ($provider) {
            'local' => new LocalProvider(),
            default => throw new InvalidArgumentException("Unsupported VPS provider"),
        };
    }
}
