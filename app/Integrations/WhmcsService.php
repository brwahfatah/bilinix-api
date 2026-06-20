<?php

namespace App\Integrations;

use App\Integrations\Contracts\ProviderInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhmcsService implements ProviderInterface
{
    private string $apiUrl;
    private string $identifier;
    private string $secret;
    private string $accessKey;

    public function __construct()
    {
        $this->apiUrl     = rtrim(config('services.whmcs.url', ''), '/') . '/includes/api.php';
        $this->identifier = config('services.whmcs.identifier', '');
        $this->secret     = config('services.whmcs.secret', '');
        $this->accessKey  = config('services.whmcs.access_key', '');
    }

    // ── Core API caller ───────────────────────────────────────────────────────

    /**
     * Execute a single WHMCS API action and return the decoded response.
     *
     * @throws RuntimeException on WHMCS error response or network failure
     */
    public function call(string $action, array $params = []): array
    {
        $this->assertConfigured();

        $payload = array_merge([
            'action'       => $action,
            'identifier'   => $this->identifier,
            'secret'       => $this->secret,
            'responsetype' => 'json',
        ], $params);

        if ($this->accessKey) {
            $payload['accesskey'] = $this->accessKey;
        }

        try {
            $response = Http::asForm()
                ->timeout(15)
                ->post($this->apiUrl, $payload);

            $response->throw();
        } catch (RequestException $e) {
            throw new RuntimeException("WHMCS HTTP error on [{$action}]: " . $e->getMessage(), 0, $e);
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException("WHMCS returned a non-JSON response for action [{$action}].");
        }

        if (($data['result'] ?? '') === 'error') {
            throw new RuntimeException("WHMCS error on [{$action}]: " . ($data['message'] ?? 'Unknown error'));
        }

        return $data;
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    public function validateLogin(string $email, string $password): array
    {
        return $this->call('ValidateLogin', [
            'email'     => $email,
            'password2' => $password,
        ]);
    }

    public function createClient(array $data): array
    {
        return $this->call('AddClient', array_merge([
            'noemail'       => true,
            'skipvalidation' => true,
        ], $data));
    }

    public function getClientDetails(int $clientId): array
    {
        return $this->call('GetClientsDetails', [
            'clientid' => $clientId,
            'stats'    => true,
        ]);
    }

    public function resetPassword(string $email): void
    {
        $this->call('ResetPassword', ['email' => $email]);
    }

    public function updateClientDetails(int $clientId, array $data): array
    {
        return $this->call('UpdateClientDetails', array_merge([
            'clientid'       => $clientId,
            'skipvalidation' => true,
        ], $data));
    }

    public function updateClientPassword(int $clientId, string $newPassword): void
    {
        $this->call('UpdateClientPassword', [
            'clientid'    => $clientId,
            'newpassword' => $newPassword,
        ]);
    }

    // ── VPS / Servers ─────────────────────────────────────────────────────────

    public function listServers(int $clientId): array
    {
        $result   = $this->call('GetClientsProducts', [
            'clientid' => $clientId,
            'limitnum' => 500,
            'stats'    => true,
        ]);

        return $this->toArray($result['products'] ?? []);
    }

    public function getServer(int $serviceId): array
    {
        $result = $this->call('GetClientsProducts', ['serviceid' => $serviceId]);
        $list   = $this->toArray($result['products'] ?? []);

        return $list[0] ?? throw new RuntimeException("Service [{$serviceId}] not found in WHMCS.");
    }

    public function createServer(int $clientId, array $data): array
    {
        $result = $this->call('AddOrder', [
            'clientid'      => $clientId,
            'pid'           => $data['product_id'],
            'billingcycle'  => $data['billing_cycle'] ?? 'monthly',
            'configoptions' => json_encode($data['config_options'] ?? []),
            'paymentmethod' => $data['payment_method'] ?? 'banktransfer',
            'noinvoice'     => false,
        ]);

        // WHMCS returns a comma-separated list of new service IDs
        $serviceId = (int) explode(',', (string) ($result['productids'] ?? ''))[0];

        if ($serviceId <= 0) {
            throw new RuntimeException('WHMCS did not return a service ID for the new VPS order.');
        }

        return $this->getServer($serviceId);
    }

    public function startServer(int $serviceId): void
    {
        $this->call('ModuleUnsuspend', ['serviceid' => $serviceId]);
    }

    public function stopServer(int $serviceId): void
    {
        $this->call('ModuleSuspend', ['serviceid' => $serviceId]);
    }

    public function rebootServer(int $serviceId): void
    {
        $this->call('ModuleCustom', [
            'serviceid' => $serviceId,
            'func_name' => 'reboot',
        ]);
    }

    public function destroyServer(int $serviceId): void
    {
        $this->call('ModuleTerminate', ['serviceid' => $serviceId]);
    }

    // ── Domains ───────────────────────────────────────────────────────────────

    public function listDomains(int $clientId): array
    {
        $result = $this->call('GetClientsDomains', [
            'clientid' => $clientId,
            'limitnum' => 500,
        ]);

        return $this->toArray($result['domains'] ?? []);
    }

    public function getDomain(int $domainId): array
    {
        $result = $this->call('GetClientsDomains', ['domainid' => $domainId]);
        $list   = $this->toArray($result['domains'] ?? []);

        return $list[0] ?? throw new RuntimeException("Domain [{$domainId}] not found in WHMCS.");
    }

    public function updateDomain(int $domainId, array $data): array
    {
        $this->call('UpdateClientDomain', array_merge(['domainid' => $domainId], $data));
        return $this->getDomain($domainId);
    }

    public function renewDomain(int $domainId, int $years = 1): array
    {
        $this->call('RenewDomain', [
            'domainid'  => $domainId,
            'regperiod' => $years,
        ]);

        return $this->getDomain($domainId);
    }

    public function setAutoRenew(int $domainId, bool $enabled): array
    {
        $this->call('UpdateClientDomain', [
            'domainid'  => $domainId,
            'autorenew' => $enabled ? 1 : 0,
        ]);

        return $this->getDomain($domainId);
    }

    public function lockDomain(int $domainId): array
    {
        $this->call('UpdateClientDomain', [
            'domainid' => $domainId,
            'locked'   => 1,
        ]);

        return $this->getDomain($domainId);
    }

    public function unlockDomain(int $domainId): array
    {
        $this->call('UpdateClientDomain', [
            'domainid' => $domainId,
            'locked'   => 0,
        ]);

        return $this->getDomain($domainId);
    }

    public function updateNameservers(int $domainId, array $nameservers): array
    {
        $params = ['domainid' => $domainId];

        foreach (array_values($nameservers) as $i => $ns) {
            $params['ns' . ($i + 1)] = $ns;
        }

        $this->call('DomainUpdateNameservers', $params);

        return $this->getDomain($domainId);
    }

    public function searchDomain(string $sld, array $tlds = []): array
    {
        if (empty($tlds)) {
            $tlds = ['.com', '.net', '.org', '.io'];
        }

        $results = [];
        foreach ($tlds as $tld) {
            $domain = $sld . $tld;
            try {
                $lookup    = $this->call('DomainWhois', ['domain' => $domain]);
                $available = str_contains(strtolower($lookup['status'] ?? ''), 'available');
                $results[] = ['domain' => $domain, 'tld' => $tld, 'available' => $available];
            } catch (\Throwable) {
                $results[] = ['domain' => $domain, 'tld' => $tld, 'available' => false];
            }
        }

        return $results;
    }

    // ── Products ─────────────────────────────────────────────────────────────

    /**
     * Return all products, optionally filtered by group ID.
     */
    public function getProducts(int $gid = null): array
    {
        $params = [];
        if ($gid !== null) {
            $params['gid'] = $gid;
        }

        $result  = $this->call('GetProducts', $params);
        $payload = $result['products'] ?? [];

        // Unwrap the products.product nesting (WHMCS JSON quirk)
        if (is_array($payload) && isset($payload['product'])) {
            $payload = $payload['product'];
        }

        if (! is_array($payload)) {
            return [];
        }

        // Single product comes back as an associative array, not a list
        return array_is_list($payload) ? $payload : [$payload];
    }

    /**
     * Return a single product by its WHMCS product ID.
     *
     * @throws RuntimeException when not found
     */
    public function getProduct(int $productId): array
    {
        $result  = $this->call('GetProducts', ['pid' => $productId]);
        $payload = $result['products'] ?? [];

        if (is_array($payload) && isset($payload['product'])) {
            $payload = $payload['product'];
        }

        if (empty($payload)) {
            throw new RuntimeException("Product [{$productId}] not found in WHMCS.");
        }

        // Normalize single vs list
        return array_is_list($payload) ? $payload[0] : $payload;
    }

    /**
     * Return TLD pricing from WHMCS.
     * Response: array of TLD => { register: {1: price, 2: price, ...}, renew: {...}, transfer: {...} }
     */
    public function getDomainPricing(): array
    {
        $result = $this->call('GetTLDPricing');
        return $result['pricing'] ?? [];
    }

    // ── Billing ───────────────────────────────────────────────────────────────

    public function listInvoices(int $clientId): array
    {
        $result = $this->call('GetInvoices', [
            'userid'   => $clientId,
            'limitnum' => 100,
            'orderby'  => 'date',
            'order'    => 'DESC',
        ]);

        return $this->toArray($result['invoices'] ?? []);
    }

    public function getInvoice(int $invoiceId): array
    {
        return $this->call('GetInvoice', ['invoiceid' => $invoiceId]);
    }

    public function getPaymentUrl(int $clientId, int $invoiceId): string
    {
        $result = $this->call('CreateSsoToken', [
            'client_id'         => $clientId,
            'destination'       => 'sso:custom_redirect',
            'sso_redirect_path' => "/viewinvoice.php?id={$invoiceId}",
        ]);

        return $result['redirect_url'] ?? throw new RuntimeException('WHMCS did not return a redirect URL.');
    }

    /**
     * Record an offline/manual payment against an invoice.
     * Used for admin-initiated payment recording; client payment flows use getPaymentUrl().
     */
    public function payInvoice(int $invoiceId, string $gateway, float $amount): void
    {
        $this->call('AddInvoicePayment', [
            'invoiceid' => $invoiceId,
            'transid'   => 'MANUAL-' . time(),
            'gateway'   => $gateway,
            'amount'    => number_format($amount, 2, '.', ''),
            'noemail'   => false,
        ]);
    }

    public function getDashboardSummary(int $clientId): array
    {
        [$services, $domains, $invoices] = [
            $this->listServers($clientId),
            $this->listDomains($clientId),
            $this->listInvoices($clientId),
        ];

        return [
            'servers'  => [
                'total'        => count($services),
                'running'      => count(array_filter($services, fn($s) => strtolower($s['status'] ?? '') === 'active')),
                'provisioning' => count(array_filter($services, fn($s) => strtolower($s['status'] ?? '') === 'pending')),
            ],
            'domains'  => [
                'total'   => count($domains),
                'active'  => count(array_filter($domains, fn($d) => strtolower($d['status'] ?? '') === 'active')),
                'expired' => count(array_filter($domains, fn($d) => str_contains(strtolower($d['status'] ?? ''), 'expired'))),
            ],
            'invoices' => [
                'total'  => count($invoices),
                'paid'   => count(array_filter($invoices, fn($i) => strtolower($i['status'] ?? '') === 'paid')),
                'unpaid' => count(array_filter($invoices, fn($i) => strtolower($i['status'] ?? '') !== 'paid')),
            ],
        ];
    }

    // ── Orders ───────────────────────────────────────────────────────────────

    /**
     * Create an order in WHMCS.
     * $data['pid'] and $data['billingcycle'] must be arrays of equal length.
     */
    public function createOrder(int $clientId, array $data): array
    {
        return $this->call('AddOrder', [
            'clientid'      => $clientId,
            'pid'           => $data['pid'],
            'billingcycle'  => $data['billingcycle'],
            'paymentmethod' => $data['payment_method'] ?? 'banktransfer',
            'noinvoice'     => false,
        ]);
    }

    /**
     * Return all orders for a WHMCS client.
     *
     * @return array[]
     */
    public function getOrders(int $clientId): array
    {
        $result  = $this->call('GetOrders', [
            'userid'   => $clientId,
            'limitnum' => 200,
        ]);
        $payload = $result['orders'] ?? [];

        if (is_array($payload) && isset($payload['order'])) {
            $payload = $payload['order'];
        }

        if (! is_array($payload)) {
            return [];
        }

        return array_is_list($payload) ? $payload : [$payload];
    }

    /**
     * Return a single WHMCS order by its order ID.
     *
     * @throws RuntimeException when not found
     */
    public function getOrder(int $orderId): array
    {
        $result  = $this->call('GetOrders', ['id' => $orderId]);
        $payload = $result['orders'] ?? [];

        if (is_array($payload) && isset($payload['order'])) {
            $payload = $payload['order'];
        }

        if (empty($payload)) {
            throw new RuntimeException("Order [{$orderId}] not found in WHMCS.");
        }

        return array_is_list($payload) ? $payload[0] : $payload;
    }

    // ── Tickets ───────────────────────────────────────────────────────────────

    public function listTickets(int $clientId): array
    {
        $result = $this->call('GetTickets', [
            'clientid' => $clientId,
            'limitnum' => 100,
        ]);

        return $this->toArray($result['tickets'] ?? []);
    }

    public function getTicket(int $ticketId): array
    {
        return $this->call('GetTicket', ['ticketid' => $ticketId]);
    }

    public function createTicket(int $clientId, array $data): array
    {
        $priorityMap = ['low' => 'Low', 'normal' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'];

        return $this->call('OpenTicket', [
            'clientid' => $clientId,
            'deptid'   => (int) ($data['dept_id'] ?? config('services.whmcs.dept_general', 4)),
            'subject'  => $data['subject'],
            'message'  => $data['message'],
            'priority' => $priorityMap[$data['priority'] ?? 'normal'] ?? 'Medium',
            'noemail'  => false,
        ]);
    }

    public function replyToTicket(int $ticketId, int $clientId, string $message): array
    {
        $this->call('AddTicketReply', [
            'ticketid' => $ticketId,
            'clientid' => $clientId,
            'message'  => $message,
            'noemail'  => false,
        ]);

        return $this->getTicket($ticketId);
    }

    public function closeTicket(int $ticketId): void
    {
        $this->call('CloseTicket', ['ticketid' => $ticketId]);
    }

    // ── Admin scope (cross-client, no ownership filter) ───────────────────────

    /**
     * Return all orders across all WHMCS clients.
     * Used by admin order management; does NOT apply a userid filter.
     *
     * @return array[]
     */
    public function adminListAllOrders(int $limit = 200): array
    {
        $result  = $this->call('GetOrders', ['limitnum' => $limit]);
        $payload = $result['orders'] ?? [];

        if (is_array($payload) && isset($payload['order'])) {
            $payload = $payload['order'];
        }

        if (! is_array($payload)) {
            return [];
        }

        return array_is_list($payload) ? $payload : [$payload];
    }

    /**
     * Collect platform-wide dashboard metrics from WHMCS.
     *
     * Each metric is fetched in an independent try/catch block — a single
     * WHMCS API failure silently returns 0 for that metric rather than
     * throwing and breaking the entire dashboard.
     *
     * @return array{
     *   active_vps: int,
     *   active_domains: int,
     *   open_tickets: int,
     *   unpaid_invoices: int,
     *   monthly_revenue: float,
     * }
     */
    public function adminDashboardStats(): array
    {
        $stats = [
            'active_vps'      => 0,
            'active_domains'  => 0,
            'open_tickets'    => 0,
            'unpaid_invoices' => 0,
            'monthly_revenue' => 0.0,
        ];

        // Active VPS — WHMCS GetClientsProducts supports server-side status filter
        try {
            $r = $this->call('GetClientsProducts', ['status' => 'Active', 'limitnum' => 1]);
            $stats['active_vps'] = (int) ($r['totalresults'] ?? 0);
        } catch (\Throwable) {}

        // Active domains — GetClientsDomains has no status filter; count in PHP
        try {
            $r       = $this->call('GetClientsDomains', ['limitnum' => 1000]);
            $domains = $this->toArray($r['domains'] ?? []);
            $stats['active_domains'] = count(
                array_filter($domains, fn($d) => strtolower($d['status'] ?? '') === 'active')
            );
        } catch (\Throwable) {}

        // Open tickets — fetch all, exclude Closed
        try {
            $r       = $this->call('GetTickets', ['limitnum' => 1000]);
            $tickets = $this->toArray($r['tickets'] ?? []);
            $stats['open_tickets'] = count(
                array_filter($tickets, fn($t) => strtolower($t['status'] ?? '') !== 'closed')
            );
        } catch (\Throwable) {}

        // Unpaid invoices — server-side status filter; Unpaid + Overdue counts
        try {
            $u = (int) ($this->call('GetInvoices', ['status' => 'Unpaid', 'limitnum' => 1])['totalresults'] ?? 0);
            $o = (int) ($this->call('GetInvoices', ['status' => 'Overdue', 'limitnum' => 1])['totalresults'] ?? 0);
            $stats['unpaid_invoices'] = $u + $o;
        } catch (\Throwable) {}

        // Monthly revenue — paid invoices where datepaid falls in the current month
        try {
            $r        = $this->call('GetInvoices', ['status' => 'Paid', 'limitnum' => 500]);
            $invoices = $this->toArray($r['invoices'] ?? []);
            $month    = now()->format('Y-m');
            $stats['monthly_revenue'] = (float) array_sum(array_map(
                fn($i) => (float) ($i['total'] ?? 0),
                array_filter(
                    $invoices,
                    fn($i) => str_starts_with((string) ($i['datepaid'] ?? $i['date'] ?? ''), $month)
                )
            ));
        } catch (\Throwable) {}

        return $stats;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * WHMCS often returns objects where arrays are expected; normalize to a list.
     */
    private function toArray(mixed $value): array
    {
        if (is_array($value) && array_is_list($value)) {
            return $value;
        }

        if (is_array($value)) {
            // WHMCS wraps single items as objects, multiple items as lists inside a key
            $first = reset($value);
            if (is_array($first)) {
                return array_values($value);
            }
            return [$value];
        }

        return [];
    }

    private function assertConfigured(): void
    {
        if (! $this->identifier || ! $this->secret || ! $this->apiUrl) {
            throw new RuntimeException(
                'WHMCS API is not configured. Set WHMCS_API_URL, WHMCS_IDENTIFIER, and WHMCS_SECRET in .env'
            );
        }
    }
}
