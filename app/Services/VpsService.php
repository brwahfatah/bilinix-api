<?php

namespace App\Services;

use App\DTO\VpsDTO;
use App\Integrations\WhmcsService;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class VpsService
{
    public function __construct(private readonly WhmcsService $whmcs) {}

    /**
     * Return all VPS owned by the authenticated user.
     *
     * @return VpsDTO[]
     */
    public function list(User $user): array
    {
        if (! $user->whmcs_client_id) {
            return [];
        }

        $servers = $this->whmcs->listServers((int) $user->whmcs_client_id);

        return array_map(
            fn(array $server) => VpsDTO::fromWhmcs($server),
            $servers
        );
    }

    /**
     * Return a single VPS, verifying the authenticated user owns it.
     */
    public function get(User $user, int $serviceId): VpsDTO
    {
        $raw = $this->whmcs->getServer($serviceId);
        $this->authorize($user, $raw);

        return VpsDTO::fromWhmcs($raw);
    }

    /**
     * Provision a new VPS via WHMCS ordering.
     */
    public function create(User $user, array $data): VpsDTO
    {
        $this->requireWhmcsClient($user);

        $raw = $this->whmcs->createServer((int) $user->whmcs_client_id, $data);

        return VpsDTO::fromWhmcs($raw);
    }

    /**
     * Unsuspend (start) the VPS and return the refreshed state.
     */
    public function start(User $user, int $serviceId): VpsDTO
    {
        $raw = $this->whmcs->getServer($serviceId);
        $this->authorize($user, $raw);

        $this->whmcs->startServer($serviceId);

        return VpsDTO::fromWhmcs($this->whmcs->getServer($serviceId));
    }

    /**
     * Suspend (stop) the VPS and return the refreshed state.
     */
    public function stop(User $user, int $serviceId): VpsDTO
    {
        $raw = $this->whmcs->getServer($serviceId);
        $this->authorize($user, $raw);

        $this->whmcs->stopServer($serviceId);

        return VpsDTO::fromWhmcs($this->whmcs->getServer($serviceId));
    }

    /**
     * Reboot the VPS and return the refreshed state.
     */
    public function reboot(User $user, int $serviceId): VpsDTO
    {
        $raw = $this->whmcs->getServer($serviceId);
        $this->authorize($user, $raw);

        $this->whmcs->rebootServer($serviceId);

        return VpsDTO::fromWhmcs($this->whmcs->getServer($serviceId));
    }

    /**
     * Terminate (destroy) the VPS. Irreversible.
     */
    public function terminate(User $user, int $serviceId): void
    {
        $raw = $this->whmcs->getServer($serviceId);
        $this->authorize($user, $raw);

        $this->whmcs->destroyServer($serviceId);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Verify WHMCS clientid on the raw response matches the authenticated user.
     * Returns a generic "not found" to avoid leaking service IDs to other users.
     */
    private function authorize(User $user, array $raw): void
    {
        if (! $user->whmcs_client_id) {
            Log::warning('Authorization denied: no WHMCS client linked', [
                'user_id'       => $user->id,
                'resource_type' => 'vps',
                'resource_id'   => $raw['id'] ?? $raw['serviceid'] ?? null,
                'ip'            => request()->ip(),
            ]);
            throw new RuntimeException('VPS not found.');
        }

        if ((int) ($raw['clientid'] ?? -1) !== (int) $user->whmcs_client_id) {
            Log::warning('Authorization denied: VPS ownership mismatch', [
                'user_id'           => $user->id,
                'resource_type'     => 'vps',
                'resource_id'       => $raw['id'] ?? $raw['serviceid'] ?? null,
                'ip'                => request()->ip(),
                'owner_client_id'   => $raw['clientid'] ?? null,
                'request_client_id' => $user->whmcs_client_id,
            ]);
            throw new RuntimeException('VPS not found.');
        }
    }

    private function requireWhmcsClient(User $user): void
    {
        if (! $user->whmcs_client_id) {
            if (config('services.whmcs.driver') === 'fake' || env('ENABLE_DEV_MOCKS', false)) {
                $user->update(['whmcs_client_id' => 1]);
                $user->refresh();
                return;
            }
            throw new RuntimeException('No WHMCS account is linked to this user.');
        }
    }
}
