<?php

namespace App\Integrations\Contracts;

/**
 * Every hosting provider integration (WHMCS, Hetzner, OVH, Proxmox…) must
 * implement this interface so Laravel can swap providers transparently.
 */
interface ProviderInterface
{
    // ── Auth ──────────────────────────────────────────────────────────────────

    public function validateLogin(string $email, string $password): array;

    public function createClient(array $data): array;

    public function resetPassword(string $email): void;

    // ── VPS / Servers ─────────────────────────────────────────────────────────

    public function listServers(int $clientId): array;

    public function getServer(int $serviceId): array;

    public function createServer(int $clientId, array $data): array;

    public function startServer(int $serviceId): void;

    public function stopServer(int $serviceId): void;

    public function rebootServer(int $serviceId): void;

    public function destroyServer(int $serviceId): void;

    // ── Domains ───────────────────────────────────────────────────────────────

    public function listDomains(int $clientId): array;

    public function getDomain(int $domainId): array;

    public function updateDomain(int $domainId, array $data): array;

    public function renewDomain(int $domainId, int $years = 1): array;

    public function setAutoRenew(int $domainId, bool $enabled): array;

    public function lockDomain(int $domainId): array;

    public function unlockDomain(int $domainId): array;

    public function updateNameservers(int $domainId, array $nameservers): array;

    // ── Products ─────────────────────────────────────────────────────────────

    public function getProducts(int $gid = null): array;

    public function getProduct(int $productId): array;

    public function getDomainPricing(): array;

    // ── Billing ───────────────────────────────────────────────────────────────

    public function listInvoices(int $clientId): array;

    public function getInvoice(int $invoiceId): array;

    public function getPaymentUrl(int $clientId, int $invoiceId): string;

    public function payInvoice(int $invoiceId, string $gateway, float $amount): void;

    // ── Orders ───────────────────────────────────────────────────────────────

    public function createOrder(int $clientId, array $data): array;

    public function getOrders(int $clientId): array;

    public function getOrder(int $orderId): array;

    // ── Tickets ───────────────────────────────────────────────────────────────

    public function listTickets(int $clientId): array;

    public function getTicket(int $ticketId): array;

    public function createTicket(int $clientId, array $data): array;

    public function replyToTicket(int $ticketId, int $clientId, string $message): array;

    public function closeTicket(int $ticketId): void;
}
