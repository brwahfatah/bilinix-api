<?php

namespace App\Services;

use App\DTO\AdminDashboardDTO;
use App\DTO\OrderDTO;
use App\Integrations\WhmcsService;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminService
{
    // Safe user columns to expose through admin endpoints
    private const USER_COLUMNS = [
        'id', 'name', 'email', 'role', 'status', 'whmcs_client_id', 'created_at', 'updated_at',
    ];

    public function __construct(private readonly WhmcsService $whmcs) {}

    // ── Dashboard ─────────────────────────────────────────────────────────────

    /**
     * Aggregate platform-wide stats from Laravel DB + WHMCS.
     * Each WHMCS stat is fetched independently so a single WHMCS failure
     * does not break the entire dashboard — that stat falls back to 0.
     */
    public function dashboard(): AdminDashboardDTO
    {
        $whmcsStats = $this->whmcs->adminDashboardStats();

        return new AdminDashboardDTO(
            usersCount:          (int) User::count(),
            activeVpsCount:      (int) ($whmcsStats['active_vps'] ?? 0),
            activeDomainsCount:  (int) ($whmcsStats['active_domains'] ?? 0),
            openTicketsCount:    (int) ($whmcsStats['open_tickets'] ?? 0),
            unpaidInvoicesCount: (int) ($whmcsStats['unpaid_invoices'] ?? 0),
            monthlyRevenue:      number_format((float) ($whmcsStats['monthly_revenue'] ?? 0), 2, '.', ''),
        );
    }

    // ── Users (Laravel DB) ────────────────────────────────────────────────────

    public function users(int $perPage = 20): LengthAwarePaginator
    {
        return User::select(self::USER_COLUMNS)
                   ->latest()
                   ->paginate($perPage);
    }

    public function user(int $id): User
    {
        return User::select(self::USER_COLUMNS)->findOrFail($id);
    }

    // ── Orders (WHMCS) ────────────────────────────────────────────────────────

    /**
     * Return all orders across all WHMCS clients.
     *
     * @return OrderDTO[]
     */
    public function orders(): array
    {
        $raw = $this->whmcs->adminListAllOrders();

        return array_map(
            fn(array $order) => OrderDTO::fromWhmcs($order),
            $raw
        );
    }

    public function order(int $orderId): OrderDTO
    {
        return OrderDTO::fromWhmcs($this->whmcs->getOrder($orderId));
    }
}
