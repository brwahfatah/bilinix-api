<?php

namespace App\Services;

use App\DTO\PaymentDTO;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Integrations\Payments\Contracts\PaymentProviderInterface;
use App\Integrations\Payments\StripePaymentProvider;
use App\Integrations\WhmcsService;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaymentService
{
    // Cache TTL for checkout session metadata (Stripe sessions expire in 24 h)
    private const SESSION_TTL_SECONDS = 7200; // 2 hours

    public function __construct(private readonly WhmcsService $whmcs) {}

    // ── Public business logic ──────────────────────────────────────────────────

    /**
     * Create a hosted payment checkout session for a WHMCS invoice.
     *
     * Flow: validate ownership → check payability → call provider → cache metadata
     *
     * @param  string $provider  Gateway name: 'stripe' (future: 'paypal', etc.)
     * @return PaymentDTO        Contains checkout_url for the frontend to redirect to
     *
     * @throws RuntimeException  On ownership violation, non-payable invoice, or provider error
     */
    public function createCheckout(User $user, int $invoiceId, string $provider = 'stripe'): PaymentDTO
    {
        $this->requireWhmcsClient($user);

        $raw    = $this->whmcs->getInvoice($invoiceId);
        $this->authorize($user, $raw);

        $status = InvoiceStatus::fromWhmcs($raw['status'] ?? '');

        if (! $status->isPayable()) {
            throw new RuntimeException(
                'This invoice cannot be paid — status is "' . $status->value . '".'
            );
        }

        $amount   = (float) ($raw['total'] ?? 0);
        $currency = strtoupper((string) ($raw['currency'] ?? config('services.stripe.currency', 'USD')));

        $gateway = $this->resolveProvider($provider);

        $session = $gateway->createCheckoutSession(
            invoiceId: $invoiceId,
            amount:    $amount,
            currency:  $currency,
            metadata:  ['user_id' => (string) $user->id],
        );

        $dto = PaymentDTO::fromStripeSession($session, (string) $invoiceId);

        // Cache session metadata so success() / cancel() can build the DTO without
        // making a second provider API call.
        Cache::put(
            $this->cacheKey($session['id']),
            [
                'invoice_id' => (string) $invoiceId,
                'amount'     => $dto->amount,
                'currency'   => $dto->currency,
                'provider'   => $provider,
            ],
            self::SESSION_TTL_SECONDS,
        );

        return $dto;
    }

    /**
     * Handle the success redirect from the payment provider.
     *
     * The frontend calls this after the provider redirects the user back.
     * Status is set to 'processing' because the webhook is the authoritative
     * confirmation — this endpoint is informational only.
     */
    public function success(string $sessionId, string $provider = 'stripe'): PaymentDTO
    {
        $cached = Cache::get($this->cacheKey($sessionId), []);

        return PaymentDTO::fromCache($sessionId, $cached, PaymentStatus::Processing);
    }

    /**
     * Handle the cancel redirect from the payment provider.
     *
     * The user chose to cancel at the provider's hosted checkout page.
     */
    public function cancel(string $sessionId, string $provider = 'stripe'): PaymentDTO
    {
        $cached = Cache::get($this->cacheKey($sessionId), []);

        return PaymentDTO::fromCache($sessionId, $cached, PaymentStatus::Cancelled);
    }

    /**
     * Process an inbound Stripe webhook.
     *
     * Verifies the signature, then on 'checkout.session.completed' records the
     * payment in WHMCS via AddInvoicePayment so the invoice is marked as Paid.
     *
     * @return array{received: bool}
     * @throws RuntimeException  On invalid signature
     */
    public function webhook(string $provider, string $payload, string $signature): array
    {
        $gateway = $this->resolveProvider($provider);
        $event   = $gateway->verifyWebhook($payload, $signature);

        if (($event['type'] ?? '') === 'checkout.session.completed') {
            $this->handleSessionCompleted($event['data']['object'] ?? []);
        }

        return ['received' => true];
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Mark the WHMCS invoice as paid when Stripe confirms the session completed.
     */
    private function handleSessionCompleted(array $session): void
    {
        $invoiceId  = (int) ($session['metadata']['invoice_id'] ?? 0);
        $amountPaid = (int) ($session['amount_total'] ?? 0) / 100;

        if ($invoiceId <= 0) {
            return; // Webhook for a non-invoice session (e.g. test event) — skip
        }

        try {
            $this->whmcs->payInvoice($invoiceId, 'stripe', $amountPaid);
        } catch (\Throwable) {
            // Idempotent: if invoice is already paid, WHMCS throws. Ignore.
        }
    }

    /**
     * Resolve a payment provider by name.
     * Extend this match when adding PayPal, regional gateways, etc.
     */
    private function resolveProvider(string $name): PaymentProviderInterface
    {
        return match ($name) {
            'stripe' => app(StripePaymentProvider::class),
            default  => throw new RuntimeException(
                "Payment provider [{$name}] is not supported. Supported: stripe."
            ),
        };
    }

    /**
     * Verify the WHMCS invoice belongs to the authenticated user.
     * Returns a generic 404-style error to avoid exposing other clients' invoice IDs.
     */
    private function authorize(User $user, array $raw): void
    {
        if ((int) ($raw['userid'] ?? -1) !== (int) $user->whmcs_client_id) {
            Log::warning('Authorization denied: invoice ownership mismatch (payment)', [
                'user_id'           => $user->id,
                'resource_type'     => 'invoice',
                'resource_id'       => $raw['invoiceid'] ?? $raw['id'] ?? null,
                'ip'                => request()->ip(),
                'owner_client_id'   => $raw['userid'] ?? null,
                'request_client_id' => $user->whmcs_client_id,
            ]);
            throw new RuntimeException('Invoice not found.');
        }
    }

    private function requireWhmcsClient(User $user): void
    {
        if (! $user->whmcs_client_id) {
            throw new RuntimeException('No WHMCS account is linked to this user.');
        }
    }

    private function cacheKey(string $sessionId): string
    {
        return 'payment_session:' . $sessionId;
    }
}
