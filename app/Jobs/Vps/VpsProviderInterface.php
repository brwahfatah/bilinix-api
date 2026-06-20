<?php

namespace App\Services\Vps;

use App\Models\Server;

interface VpsProviderInterface
{
    public function createServer(Server $server): array;
}
