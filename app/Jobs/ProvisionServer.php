<?php

namespace App\Jobs;

use App\Models\Server;
use App\Services\Vps\VpsProviderFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProvisionServer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 3;

    public function __construct(public Server $server) {}

    public function handle()
    {
        // 🔐 Safety checks
        if ($server = $this->server->fresh()) {

            if ($server->status !== 'active') {
                return;
            }
            if ($server->status !== 'provisioning') {
    return;
}


            if ($server->provisioned_at) {
                return;
            }

            // 🚀 Move to provisioning state
            $server->update(['status' => 'provisioning']);

            try {

                $provider = VpsProviderFactory::make($server->provider ?? 'local');

                $result = $provider->createServer($server);

                $server->update([
                    'status'          => 'active',
                    'ip_address'      => $result['ip'],
                    'ssh_username'    => $result['username'],
                    'ssh_password'    => $result['password'],
                    'provisioned_at'  => now(),
                ]);

            } catch (Throwable $e) {

                $server->update([
                    'status' => 'failed',
                    'last_error' => $e->getMessage(),
                ]);

                throw $e;
            }
        }
    }
}
