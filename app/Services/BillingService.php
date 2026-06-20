<?php

namespace App\Services;

use App\DTO\InvoiceDTO;
use App\Enums\InvoiceStatus;
use App\Integrations\WhmcsService;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BillingService
{
    public function __construct(private readonly WhmcsService $whmcs) {}

    /**
     * Return all invoices for the authenticated user.
     *
     * @return InvoiceDTO[]
     */
    public function list(User $user): array
    {
        if (! $user->whmcs_client_id) {
            return [];
        }

        $invoices = $this->whmcs->listInvoices((int) $user->whmcs_client_id);

        return array_map(
            fn(array $invoice) => InvoiceDTO::fromWhmcs($invoice),
            $invoices
        );
    }

    /**
     * Return a single invoice with full line-item detail.
     */
    public function get(User $user, int $invoiceId): InvoiceDTO
    {
        $raw = $this->whmcs->getInvoice($invoiceId);
        $this->authorize($user, $raw);

        return InvoiceDTO::fromWhmcs($raw);
    }

    /**
     * Generate a WHMCS SSO payment URL for the invoice.
     * Returns ['payment_url' => '...'] so the frontend can redirect the user.
     *
     * @return array{ payment_url: string }
     */
    public function pay(User $user, int $invoiceId): array
    {
        $raw = $this->whmcs->getInvoice($invoiceId);
        $this->authorize($user, $raw);

        $status = InvoiceStatus::fromWhmcs($raw['status'] ?? '');

        if (! $status->isPayable()) {
            throw new RuntimeException(
                'This invoice cannot be paid — it is already ' . $status->value . '.'
            );
        }

        $paymentUrl = $this->whmcs->getPaymentUrl(
            (int) $user->whmcs_client_id,
            $invoiceId
        );

        return ['payment_url' => $paymentUrl];
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Verify the WHMCS invoice belongs to the authenticated user.
     * GetInvoice returns 'userid' at the top level.
     * Returns a generic 404 to prevent leaking other customers' invoice IDs.
     */
    private function authorize(User $user, array $raw): void
    {
        if (! $user->whmcs_client_id) {
            Log::warning('Authorization denied: no WHMCS client linked', [
                'user_id'       => $user->id,
                'resource_type' => 'invoice',
                'resource_id'   => $raw['invoiceid'] ?? $raw['id'] ?? null,
                'ip'            => request()->ip(),
            ]);
            throw new RuntimeException('Invoice not found.');
        }

        $rawClientId = (int) ($raw['userid'] ?? -1);

        if ($rawClientId !== (int) $user->whmcs_client_id) {
            Log::warning('Authorization denied: invoice ownership mismatch', [
                'user_id'           => $user->id,
                'resource_type'     => 'invoice',
                'resource_id'       => $raw['invoiceid'] ?? $raw['id'] ?? null,
                'ip'                => request()->ip(),
                'owner_client_id'   => $rawClientId,
                'request_client_id' => $user->whmcs_client_id,
            ]);
            throw new RuntimeException('Invoice not found.');
        }
    }
}
