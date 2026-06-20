<?php

namespace App\Services\Vps\Providers;

use App\Models\Server;
use App\Services\Vps\VpsProviderInterface;
use Illuminate\Support\Str;

class LocalProvider implements VpsProviderInterface
{
    public function createServer(Server $server): array
    {
        // Simulate provisioning delay
        sleep(3);

        return [
            'ip'       => '45.' . rand(10,200) . '.' . rand(10,200) . '.' . rand(10,200),
            'username' => 'root',
            'password' => Str::random(16),
        ];
    }
}
