<?php

namespace App\Services;

use App\DTO\DomainDTO;
use App\Integrations\WhmcsService;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DomainService
{
    public function __construct(private readonly WhmcsService $whmcs) {}

    /**
     * Return all domains owned by the authenticated user.
     *
     * @return DomainDTO[]
     */
    public function list(User $user): array
    {
        if (! $user->whmcs_client_id) {
            return [];
        }

        $domains = $this->whmcs->listDomains((int) $user->whmcs_client_id);

        return array_map(
            fn(array $domain) => DomainDTO::fromWhmcs($domain),
            $domains
        );
    }

    /**
     * Return a single domain, verifying the authenticated user owns it.
     */
    public function get(User $user, int $domainId): DomainDTO
    {
        $raw = $this->whmcs->getDomain($domainId);
        $this->authorize($user, $raw);

        return DomainDTO::fromWhmcs($raw);
    }

    /**
     * Renew the domain for one year.
     */
    public function renew(User $user, int $domainId): DomainDTO
    {
        $raw = $this->whmcs->getDomain($domainId);
        $this->authorize($user, $raw);

        $updated = $this->whmcs->renewDomain($domainId);

        return DomainDTO::fromWhmcs($updated);
    }

    /**
     * Toggle auto-renew on or off (reads current state and flips it).
     */
    public function toggleAutoRenew(User $user, int $domainId): DomainDTO
    {
        $raw = $this->whmcs->getDomain($domainId);
        $this->authorize($user, $raw);

        $currently = (bool) ($raw['autorenew'] ?? false);
        $updated   = $this->whmcs->setAutoRenew($domainId, ! $currently);

        return DomainDTO::fromWhmcs($updated);
    }

    /**
     * Enable registrar lock on the domain.
     */
    public function lock(User $user, int $domainId): DomainDTO
    {
        $raw = $this->whmcs->getDomain($domainId);
        $this->authorize($user, $raw);

        $updated = $this->whmcs->lockDomain($domainId);

        return DomainDTO::fromWhmcs($updated);
    }

    /**
     * Remove registrar lock from the domain.
     */
    public function unlock(User $user, int $domainId): DomainDTO
    {
        $raw = $this->whmcs->getDomain($domainId);
        $this->authorize($user, $raw);

        $updated = $this->whmcs->unlockDomain($domainId);

        return DomainDTO::fromWhmcs($updated);
    }

    /**
     * Replace all nameservers for the domain.
     */
    public function updateNameservers(User $user, int $domainId, array $nameservers): DomainDTO
    {
        $raw = $this->whmcs->getDomain($domainId);
        $this->authorize($user, $raw);

        $updated = $this->whmcs->updateNameservers($domainId, $nameservers);

        return DomainDTO::fromWhmcs($updated);
    }

    /**
     * Search domain availability across the given TLDs.
     * Public — no User required. Returns raw WHMCS result array.
     *
     * @return array<int, array{domain: string, tld: string, available: bool, price: ?string}>
     */
    public function search(string $sld, array $tlds): array
    {
        return $this->whmcs->searchDomain($sld, $tlds);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Verify the WHMCS domain belongs to the authenticated user.
     * GetClientsDomains returns 'userid'; fall back to 'clientid' for safety.
     * Returns a generic 404 to prevent leaking other customers' domain IDs.
     */
    private function authorize(User $user, array $raw): void
    {
        if (! $user->whmcs_client_id) {
            Log::warning('Authorization denied: no WHMCS client linked', [
                'user_id'       => $user->id,
                'resource_type' => 'domain',
                'resource_id'   => $raw['id'] ?? $raw['domainid'] ?? null,
                'ip'            => request()->ip(),
            ]);
            throw new RuntimeException('Domain not found.');
        }

        $rawClientId = (int) ($raw['userid'] ?? $raw['clientid'] ?? -1);

        if ($rawClientId !== (int) $user->whmcs_client_id) {
            Log::warning('Authorization denied: domain ownership mismatch', [
                'user_id'           => $user->id,
                'resource_type'     => 'domain',
                'resource_id'       => $raw['id'] ?? $raw['domainid'] ?? null,
                'ip'                => request()->ip(),
                'owner_client_id'   => $rawClientId,
                'request_client_id' => $user->whmcs_client_id,
            ]);
            throw new RuntimeException('Domain not found.');
        }
    }
}
