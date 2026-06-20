<?php

namespace App\Integrations\Payments\Contracts;

/**
 * All payment gateway integrations (Stripe, PayPal, regional gateways…) must
 * implement this interface so the PaymentService can swap providers at runtime.
 */
interface PaymentProviderInterface
{
    /**
     * Create a hosted checkout session and return the provider's session object.
     *
     * Implementations must include at minimum:
     *   - id            : provider-side session/transaction identifier
     *   - url           : the URL the frontend should redirect the user to
     *   - status        : current status of the session
     *   - amount_total  : amount in smallest currency unit (e.g. cents)
     *   - currency      : ISO 4217 currency code (lowercase)
     *   - created       : Unix timestamp
     *
     * @param  int    $invoiceId  WHMCS invoice ID, stored in session metadata
     * @param  float  $amount     Amount in major currency unit (e.g. 12.50 for $12.50)
     * @param  string $currency   ISO 4217 currency code (e.g. 'USD')
     * @param  array  $metadata   Arbitrary key-value pairs to attach to the session
     * @return array              Normalized session object
     *
     * @throws \RuntimeException on provider API error
     */
    public function createCheckoutSession(
        int    $invoiceId,
        float  $amount,
        string $currency,
        array  $metadata = [],
    ): array;

    /**
     * Verify and parse an inbound webhook payload.
     *
     * Must throw \RuntimeException if the signature is invalid or the
     * timestamp tolerance is exceeded. On success returns the parsed event
     * array with at minimum:
     *   - type          : event type string (e.g. 'checkout.session.completed')
     *   - data.object   : the primary resource the event describes
     *
     * @param  string $payload   Raw HTTP request body (must not be decoded first)
     * @param  string $signature Provider-specific signature header value
     * @return array             Parsed event
     *
     * @throws \RuntimeException on invalid signature or malformed payload
     */
    public function verifyWebhook(string $payload, string $signature): array;

    /**
     * Issue a full or partial refund for a completed payment.
     *
     * @param  string     $transactionId  Provider-side payment intent / charge ID
     * @param  float|null $amount         Amount to refund in major currency unit.
     *                                    Null means full refund.
     *
     * @throws \RuntimeException on provider API error
     */
    public function refund(string $transactionId, ?float $amount = null): void;
}
