<?php

namespace App\Services;

use App\DTO\TicketDTO;
use App\Integrations\WhmcsService;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TicketService
{
    public function __construct(private readonly WhmcsService $whmcs) {}

    /**
     * Return all tickets for the authenticated user.
     * Ownership is implicit — listTickets filters by clientId in the WHMCS query.
     *
     * @return TicketDTO[]
     */
    public function list(User $user): array
    {
        if (! $user->whmcs_client_id) {
            return [];
        }

        $tickets = $this->whmcs->listTickets((int) $user->whmcs_client_id);

        return array_map(
            fn(array $ticket) => TicketDTO::fromWhmcs($ticket),
            $tickets
        );
    }

    /**
     * Return a single ticket with full reply thread, verifying ownership.
     */
    public function get(User $user, int $ticketId): TicketDTO
    {
        $raw = $this->whmcs->getTicket($ticketId);
        $this->authorize($user, $raw);

        return TicketDTO::fromWhmcs($raw);
    }

    /**
     * Open a new support ticket on behalf of the authenticated user.
     */
    public function create(User $user, array $payload): TicketDTO
    {
        $this->requireWhmcsClient($user);

        $result = $this->whmcs->createTicket((int) $user->whmcs_client_id, [
            'subject'  => $payload['subject'],
            'message'  => $payload['message'],
            'dept_id'  => $payload['department_id'],
            'priority' => $payload['priority'],
        ]);

        // OpenTicket returns the new numeric ticket ID as 'id'
        $newId = (int) ($result['id'] ?? 0);

        if ($newId <= 0) {
            throw new RuntimeException('WHMCS did not return a ticket ID after creation.');
        }

        return TicketDTO::fromWhmcs($this->whmcs->getTicket($newId));
    }

    /**
     * Post a reply to an existing ticket and return the refreshed thread.
     */
    public function reply(User $user, int $ticketId, string $message): TicketDTO
    {
        $raw = $this->whmcs->getTicket($ticketId);
        $this->authorize($user, $raw);

        $updated = $this->whmcs->replyToTicket($ticketId, (int) $user->whmcs_client_id, $message);

        return TicketDTO::fromWhmcs($updated);
    }

    /**
     * Close a ticket, verifying the authenticated user owns it.
     */
    public function close(User $user, int $ticketId): void
    {
        $raw = $this->whmcs->getTicket($ticketId);
        $this->authorize($user, $raw);

        $this->whmcs->closeTicket($ticketId);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Verify the WHMCS ticket belongs to the authenticated user.
     * GetTicket returns 'userid' at the top level.
     * Returns a generic 404 to prevent leaking other customers' ticket IDs.
     */
    private function authorize(User $user, array $raw): void
    {
        if (! $user->whmcs_client_id) {
            Log::warning('Authorization denied: no WHMCS client linked', [
                'user_id'       => $user->id,
                'resource_type' => 'ticket',
                'resource_id'   => $raw['id'] ?? $raw['tid'] ?? null,
                'ip'            => request()->ip(),
            ]);
            throw new RuntimeException('Ticket not found.');
        }

        $rawClientId = (int) ($raw['userid'] ?? -1);

        if ($rawClientId !== (int) $user->whmcs_client_id) {
            Log::warning('Authorization denied: ticket ownership mismatch', [
                'user_id'           => $user->id,
                'resource_type'     => 'ticket',
                'resource_id'       => $raw['id'] ?? $raw['tid'] ?? null,
                'ip'                => request()->ip(),
                'owner_client_id'   => $rawClientId,
                'request_client_id' => $user->whmcs_client_id,
            ]);
            throw new RuntimeException('Ticket not found.');
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
