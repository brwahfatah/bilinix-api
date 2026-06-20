<?php

namespace App\DTO;

final class AdminDashboardDTO
{
    public function __construct(
        public readonly int    $usersCount,
        public readonly int    $activeVpsCount,
        public readonly int    $activeDomainsCount,
        public readonly int    $openTicketsCount,
        public readonly int    $unpaidInvoicesCount,
        public readonly string $monthlyRevenue,
    ) {}

    public function toArray(): array
    {
        return [
            'users_count'           => $this->usersCount,
            'active_vps_count'      => $this->activeVpsCount,
            'active_domains_count'  => $this->activeDomainsCount,
            'open_tickets_count'    => $this->openTicketsCount,
            'unpaid_invoices_count' => $this->unpaidInvoicesCount,
            'monthly_revenue'       => $this->monthlyRevenue,
        ];
    }
}
