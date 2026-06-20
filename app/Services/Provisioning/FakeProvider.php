<?php

namespace App\Services\Provisioning;

use App\Models\Server;

class FakeProvider
{
    public function createServer(Server $server): void
    {
        sleep(2); // simulate API delay

        $server->update([
            'ip_address' => '45.' . rand(10,200) . '.' . rand(10,200) . '.' . rand(10,200),
            'ssh_key' => 'ssh-rsa FAKEKEY',
            'status' => 'active',
        ]);
    }
}
