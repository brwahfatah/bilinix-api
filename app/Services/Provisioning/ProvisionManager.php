<?php

namespace App\Services\Provisioning;

use App\Models\Server;

class ProvisionManager
{
    public function provision(Server $server): void
    {
        $provider = match (config('provisioning.driver')) {
            'fake' => app(FakeProvider::class),
            default => throw new \Exception('Provision driver not configured'),
        };

        $provider->createServer($server);
    }
}
