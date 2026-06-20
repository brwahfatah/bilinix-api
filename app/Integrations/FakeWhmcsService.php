<?php

namespace App\Integrations;

/**
 * Local-development stub for WhmcsService.
 *
 * Active when WHMCS_DRIVER=fake (or ENABLE_DEV_MOCKS=true / no WHMCS_API_URL).
 * All records belong to CLIENT_ID=1 which matches admin@test.com seeded user.
 */
class FakeWhmcsService extends WhmcsService
{
    private const CLIENT_ID = 1;

    // ── Auth ──────────────────────────────────────────────────────────────────

    public function validateLogin(string $email, string $password): array
    {
        return ['result' => 'success', 'userid' => self::CLIENT_ID, 'passwordhash' => password_hash($password, PASSWORD_BCRYPT)];
    }

    public function createClient(array $data): array
    {
        return ['result' => 'success', 'clientid' => self::CLIENT_ID];
    }

    public function getClientDetails(int $clientId): array
    {
        return [
            'result' => 'success',
            'client' => [
                'id'             => self::CLIENT_ID,
                'firstname'      => 'Test',
                'lastname'       => 'Admin',
                'email'          => 'admin@test.com',
                'phonenumber'    => '+1-555-0100',
                'address1'       => '123 Hosting Street',
                'city'           => 'Cloud City',
                'state'          => 'CA',
                'postcode'       => '90210',
                'countrycode'    => 'US',
                'currency'       => 1,
                'currencyprefix' => '$',
                'currencysuffix' => ' USD',
            ],
        ];
    }

    public function resetPassword(string $email): void {}

    public function updateClientDetails(int $clientId, array $data): array
    {
        return array_merge($this->getClientDetails($clientId), $data);
    }

    public function updateClientPassword(int $clientId, string $newPassword): void {}

    // ── VPS / Servers ─────────────────────────────────────────────────────────

    public function listServers(int $clientId): array
    {
        return [$this->serverRecord(1001), $this->serverRecord(1002)];
    }

    public function getServer(int $serviceId): array
    {
        return $this->serverRecord($serviceId);
    }

    public function createServer(int $clientId, array $data): array
    {
        return $this->serverRecord(1099);
    }

    public function startServer(int $serviceId): void {}

    public function stopServer(int $serviceId): void {}

    public function rebootServer(int $serviceId): void {}

    public function destroyServer(int $serviceId): void {}

    // ── Domains ───────────────────────────────────────────────────────────────

    public function listDomains(int $clientId): array
    {
        return [$this->domainRecord(1), $this->domainRecord(2)];
    }

    public function getDomain(int $domainId): array
    {
        return $this->domainRecord($domainId);
    }

    public function updateDomain(int $domainId, array $data): array
    {
        return $this->domainRecord($domainId);
    }

    public function renewDomain(int $domainId, int $years = 1): array
    {
        return $this->domainRecord($domainId);
    }

    public function setAutoRenew(int $domainId, bool $enabled): array
    {
        return array_merge($this->domainRecord($domainId), ['autorenew' => $enabled ? '1' : '0']);
    }

    public function lockDomain(int $domainId): array
    {
        return array_merge($this->domainRecord($domainId), ['locked' => '1']);
    }

    public function unlockDomain(int $domainId): array
    {
        return array_merge($this->domainRecord($domainId), ['locked' => '0']);
    }

    public function updateNameservers(int $domainId, array $nameservers): array
    {
        $record = $this->domainRecord($domainId);
        foreach (array_values($nameservers) as $i => $ns) {
            $record['nameserver' . ($i + 1)] = $ns;
        }
        return $record;
    }

    public function searchDomain(string $sld, array $tlds = []): array
    {
        if (empty($tlds)) {
            $tlds = ['.com', '.net', '.org', '.io'];
        }

        $pricing  = $this->getDomainPricing();
        $sldLower = strtolower($sld);
        // Simulate taken domains — common names or explicit test keywords
        $takenSlds = ['google', 'facebook', 'amazon', 'apple', 'microsoft', 'taken', 'unavailable'];

        return array_map(function (string $tld) use ($sldLower, $pricing, $takenSlds) {
            $taken     = in_array($sldLower, $takenSlds, true);
            $available = ! $taken;
            $price     = $available ? ($pricing[$tld]['register']['1'] ?? '12.99') : null;

            return [
                'domain'    => $sldLower . $tld,
                'tld'       => $tld,
                'available' => $available,
                'price'     => $price,
            ];
        }, $tlds);
    }

    // ── Products ─────────────────────────────────────────────────────────────

    public function getProducts(int $gid = null): array
    {
        return [
            $this->sharedHostingProduct(1, 'Shared Hosting Starter',  '4.99',  '49.99'),
            $this->sharedHostingProduct(2, 'Shared Hosting Business',  '9.99',  '99.99'),
            $this->vpsProduct(3,  'VPS 2GB',          '9.99',  '99.99',  true),
            $this->vpsProduct(4,  'VPS 4GB',          '19.99', '199.99', false),
            $this->dedicatedProduct(5, 'Dedicated Server', '79.99', '799.99'),
            $this->sslProduct(6,  'SSL Certificate',   '9.99'),
        ];
    }

    public function getProduct(int $productId): array
    {
        return $this->getProducts()[$productId - 1] ?? $this->vpsProduct($productId, "Product {$productId}", '9.99', '99.99', false);
    }

    public function getDomainPricing(): array
    {
        return [
            '.com' => [
                'register' => ['1' => '12.99', '2' => '25.98'],
                'renew'    => ['1' => '14.99', '2' => '29.98'],
                'transfer' => ['1' => '12.99'],
            ],
            '.net' => [
                'register' => ['1' => '13.99', '2' => '27.98'],
                'renew'    => ['1' => '15.99', '2' => '31.98'],
                'transfer' => ['1' => '13.99'],
            ],
            '.org' => [
                'register' => ['1' => '11.99', '2' => '23.98'],
                'renew'    => ['1' => '13.99', '2' => '27.98'],
                'transfer' => ['1' => '11.99'],
            ],
            '.io' => [
                'register' => ['1' => '39.99', '2' => '79.98'],
                'renew'    => ['1' => '44.99', '2' => '89.98'],
                'transfer' => ['1' => '39.99'],
            ],
            '.test' => [
                'register' => ['1' => '0.99'],
                'renew'    => ['1' => '0.99'],
                'transfer' => ['1' => '0.99'],
            ],
        ];
    }

    // ── Billing ───────────────────────────────────────────────────────────────

    public function listInvoices(int $clientId): array
    {
        return [
            $this->invoiceListRecord(2001, 'Paid',   '2026-06-01'),
            $this->invoiceListRecord(2002, 'Unpaid', '2026-08-01'),
            $this->invoiceListRecord(2003, 'Unpaid', '2025-01-01'), // past duedate → auto-promoted to overdue
        ];
    }

    public function getInvoice(int $invoiceId): array
    {
        return $this->invoiceDetailRecord($invoiceId);
    }

    public function getPaymentUrl(int $clientId, int $invoiceId): string
    {
        return 'https://whmcs.example.com/viewinvoice.php?id=' . $invoiceId . '&token=fake-sso-token';
    }

    public function payInvoice(int $invoiceId, string $gateway, float $amount): void {}

    public function getDashboardSummary(int $clientId): array
    {
        return [
            'servers'  => ['total' => 2, 'running' => 2, 'provisioning' => 0],
            'domains'  => ['total' => 2, 'active' => 2, 'expired' => 0],
            'invoices' => ['total' => 3, 'paid' => 1, 'unpaid' => 1, 'overdue' => 1],
        ];
    }

    // ── Tickets ───────────────────────────────────────────────────────────────

    public function listTickets(int $clientId): array
    {
        return [
            $this->ticketListRecord(3001, 'Open'),
            $this->ticketListRecord(3002, 'Answered'),
            $this->ticketListRecord(3003, 'Closed'),
        ];
    }

    public function getTicket(int $ticketId): array
    {
        return $this->ticketDetailRecord($ticketId);
    }

    public function createTicket(int $clientId, array $data): array
    {
        return ['result' => 'success', 'id' => 3099, 'tid' => 'TKT-' . mt_rand(1000, 9999)];
    }

    public function replyToTicket(int $ticketId, int $clientId, string $message): array
    {
        return $this->ticketDetailRecord($ticketId);
    }

    public function closeTicket(int $ticketId): void {}

    // ── Orders ───────────────────────────────────────────────────────────────

    public function createOrder(int $clientId, array $data): array
    {
        return [
            'result'     => 'success',
            'orderid'    => 4099,
            'invoiceid'  => 2002,
            'amount'     => '9.99',
            'date'       => now()->format('Y-m-d H:i:s'),
            'status'     => 'Pending',
            'productids' => implode(',', (array) ($data['pid'] ?? [3])),
        ];
    }

    public function getOrders(int $clientId): array
    {
        return [$this->orderRecord(4001, 'Processing'), $this->orderRecord(4002, 'Active')];
    }

    public function getOrder(int $orderId): array
    {
        $status = $orderId === 4001 ? 'Processing' : 'Active';
        return $this->orderRecord($orderId, $status);
    }

    // ── Admin scope ───────────────────────────────────────────────────────────

    public function adminListAllOrders(int $limit = 200): array
    {
        return [$this->orderRecord(4001, 'Processing'), $this->orderRecord(4002, 'Active')];
    }

    public function adminDashboardStats(): array
    {
        return [
            'active_vps'      => 2,
            'active_domains'  => 2,
            'open_tickets'    => 1,
            'unpaid_invoices' => 2,
            'monthly_revenue' => 29.98,
        ];
    }

    // ── Private record builders ───────────────────────────────────────────────

    private function serverRecord(int $id): array
    {
        $plans = [
            1001 => ['label' => 'vps-2gb.beeliin.test', 'cpu' => '1', 'ram' => '2048',  'disk' => '50',  'ip' => '10.0.0.1'],
            1002 => ['label' => 'vps-4gb.beeliin.test', 'cpu' => '2', 'ram' => '4096',  'disk' => '100', 'ip' => '10.0.0.2'],
            1099 => ['label' => 'vps-new.beeliin.test', 'cpu' => '1', 'ram' => '2048',  'disk' => '50',  'ip' => '10.0.0.99'],
        ];
        $p = $plans[$id] ?? [
            'label' => "vps-{$id}.beeliin.test",
            'cpu'   => '1',
            'ram'   => '2048',
            'disk'  => '50',
            'ip'    => '10.0.0.' . ($id % 256),
        ];

        return [
            'id'            => $id,
            'serviceid'     => $id,
            'domain'        => $p['label'],   // VpsDTO maps data['domain'] → label
            'status'        => 'Active',
            'clientid'      => self::CLIENT_ID,
            'configoption1' => $p['cpu'],     // vCPU count
            'configoption2' => $p['ram'],     // RAM in MB
            'configoption3' => $p['disk'],    // Disk in GB
            'dedicatedip'   => $p['ip'],
            'nextduedate'   => '2026-12-18',
            'regdate'       => '2026-06-18',
        ];
    }

    private function domainRecord(int $id): array
    {
        $names = [1 => 'beeliin.test', 2 => 'example.com'];

        return [
            'id'               => $id,
            'domainname'       => $names[$id] ?? "domain{$id}.com",
            'status'           => 'Active',
            'expirydate'       => '2027-06-18',
            'autorenew'        => '1',
            'locked'           => '0',
            'idprotection'     => '0',
            'nameserver1'      => 'ns1.beeliin.com',
            'nameserver2'      => 'ns2.beeliin.com',
            'nameserver3'      => '',
            'nameserver4'      => '',
            'nameserver5'      => '',
            'registrationdate' => '2026-06-18',
            'userid'           => self::CLIENT_ID,
            'firstname'        => 'Test',
            'lastname'         => 'Admin',
            'email'            => 'admin@test.com',
            'countrycode'      => 'US',
        ];
    }

    private function invoiceListRecord(int $id, string $status = 'Unpaid', string $duedate = '2026-07-01'): array
    {
        return [
            'id'         => $id,
            'invoiceid'  => $id,
            'userid'     => self::CLIENT_ID,
            'invoicenum' => 'INV-' . str_pad($id, 5, '0', STR_PAD_LEFT),
            'status'     => $status,
            'date'       => '2026-06-01',
            'duedate'    => $duedate,
            'datepaid'   => $status === 'Paid' ? '2026-06-15' : '0000-00-00',
            'subtotal'   => '9.99',
            'tax'        => '0.00',
            'total'      => '9.99',
        ];
    }

    private function invoiceDetailRecord(int $invoiceId): array
    {
        $status  = $invoiceId === 2001 ? 'Paid' : 'Unpaid';
        $duedate = $invoiceId === 2003 ? '2025-01-01' : ($invoiceId === 2001 ? '2026-06-01' : '2026-08-01');
        $datepaid = $status === 'Paid' ? '2026-06-15' : '0000-00-00';

        return [
            'result'     => 'success',
            'invoiceid'  => $invoiceId,
            'id'         => $invoiceId,
            'userid'     => self::CLIENT_ID,
            'invoicenum' => 'INV-' . str_pad($invoiceId, 5, '0', STR_PAD_LEFT),
            'status'     => $status,
            'date'       => '2026-06-01',
            'duedate'    => $duedate,
            'datepaid'   => $datepaid,
            'subtotal'   => '9.99',
            'tax'        => '0.00',
            'total'      => '9.99',
            'notes'      => '',
            'items'      => [
                'item' => [
                    ['id' => 1, 'description' => 'VPS 2GB - Monthly', 'amount' => '9.99'],
                ],
            ],
        ];
    }

    private function ticketListRecord(int $id, string $status = 'Open'): array
    {
        $subjects = [
            3001 => 'Cannot connect to VPS via SSH',
            3002 => 'Invoice total seems incorrect',
            3003 => 'How do I set up email hosting?',
        ];
        $depts = [3001 => 'Technical', 3002 => 'Billing', 3003 => 'General'];

        return [
            'id'        => $id,
            'ticketid'  => $id,
            'tid'       => 'TKT-' . $id,
            'subject'   => $subjects[$id] ?? "Support Ticket #{$id}",
            'deptname'  => $depts[$id] ?? 'General',
            'status'    => $status,
            'priority'  => 'Medium',
            'date'      => '2026-06-18 10:00:00',
            'lastreply' => '2026-06-18 11:00:00',
            'userid'    => self::CLIENT_ID,
        ];
    }

    private function ticketDetailRecord(int $ticketId): array
    {
        $list = $this->ticketListRecord($ticketId);

        return array_merge($list, [
            'result'  => 'success',
            'message' => 'Hello, I have a question about my account.',
            'replies' => [
                'reply' => [
                    [
                        'id'      => 1,
                        'message' => 'Thank you for contacting us. We are looking into this.',
                        'name'    => 'Support Agent',
                        'date'    => '2026-06-18 11:00:00',
                    ],
                ],
            ],
        ]);
    }

    private function sharedHostingProduct(int $pid, string $name, string $monthly, string $annually): array
    {
        return [
            'pid'          => $pid,
            'name'         => $name,
            'description'  => 'Reliable shared hosting with cPanel, free SSL, and 24/7 support.',
            'type'         => 'hostingaccount',
            'module'       => 'cpanel',
            'gid'          => 1,
            'stockcontrol' => 0,
            'stocklevel'   => 0,
            'customfields' => [],
            'pricing'      => [
                'USD' => [
                    'monthly'      => $monthly,
                    'quarterly'    => '-1.00',
                    'semiannually' => '-1.00',
                    'annually'     => $annually,
                    'biennially'   => '-1.00',
                    'triennially'  => '-1.00',
                ],
            ],
        ];
    }

    private function vpsProduct(int $pid, string $name, string $monthly, string $annually, bool $featured): array
    {
        return [
            'pid'          => $pid,
            'name'         => $name,
            'description'  => 'Cloud VPS with full root access, dedicated resources, and SSD storage.',
            'type'         => 'server',
            'module'       => 'virtfusion',
            'gid'          => 2,
            'stockcontrol' => 0,
            'stocklevel'   => 0,
            'customfields' => $featured
                ? ['customfield' => [['name' => 'featured', 'value' => 'yes']]]
                : [],
            'pricing' => [
                'USD' => [
                    'monthly'      => $monthly,
                    'quarterly'    => '-1.00',
                    'semiannually' => '-1.00',
                    'annually'     => $annually,
                    'biennially'   => '-1.00',
                    'triennially'  => '-1.00',
                ],
            ],
        ];
    }

    private function dedicatedProduct(int $pid, string $name, string $monthly, string $annually): array
    {
        return [
            'pid'          => $pid,
            'name'         => $name,
            'description'  => 'Bare-metal dedicated server with full hardware isolation.',
            'type'         => 'server',
            'module'       => 'dedicatedserver',
            'gid'          => 3,
            'stockcontrol' => 0,
            'stocklevel'   => 0,
            'customfields' => [],
            'pricing'      => [
                'USD' => [
                    'monthly'      => $monthly,
                    'quarterly'    => '-1.00',
                    'semiannually' => '-1.00',
                    'annually'     => $annually,
                    'biennially'   => '-1.00',
                    'triennially'  => '-1.00',
                ],
            ],
        ];
    }

    private function sslProduct(int $pid, string $name, string $annually): array
    {
        return [
            'pid'          => $pid,
            'name'         => $name,
            'description'  => 'Domain Validated SSL certificate. Issued in minutes.',
            'type'         => 'other',
            'module'       => 'sectigo',
            'gid'          => 4,
            'stockcontrol' => 0,
            'stocklevel'   => 0,
            'customfields' => [],
            'pricing'      => [
                'USD' => [
                    'monthly'      => '-1.00',
                    'quarterly'    => '-1.00',
                    'semiannually' => '-1.00',
                    'annually'     => $annually,
                    'biennially'   => '-1.00',
                    'triennially'  => '-1.00',
                ],
            ],
        ];
    }

    private function orderRecord(int $orderId, string $status = 'Active'): array
    {
        $products = [4001 => 'VPS 2GB', 4002 => 'VPS 4GB'];

        return [
            'id'        => $orderId,
            'orderid'   => $orderId,
            'userid'    => self::CLIENT_ID,
            'date'      => '2026-06-18 12:00:00',
            'status'    => $status,
            'amount'    => $orderId === 4001 ? '9.99' : '19.99',
            'invoiceid' => $orderId === 4001 ? 2001 : 2002,
            'lineitems' => [
                'lineitem' => [
                    [
                        'product'      => $products[$orderId] ?? 'VPS 2GB',
                        'domain'       => '',
                        'billingcycle' => 'Monthly',
                        'amount'       => $orderId === 4001 ? '9.99' : '19.99',
                        'type'         => 'Hosting',
                    ],
                ],
            ],
        ];
    }
}
