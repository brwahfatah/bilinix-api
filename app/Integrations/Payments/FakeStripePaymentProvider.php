<?php

namespace App\Integrations\Payments;

use App\Integrations\Payments\Contracts\PaymentProviderInterface;
use RuntimeException;

/**
 * Fake Stripe provider for WHMCS_DRIVER=fake / development mode.
 *
 * createCheckoutSession returns a session whose URL points directly at the
 * success redirect so the full browser flow can be exercised without real keys.
 */
class FakeStripePaymentProvider implements PaymentProviderInterface
{
    public function createCheckoutSession(
        int    $invoiceId,
        float  $amount,
        string $currency,
        array  $metadata = [],
    ): array {
        $sessionId   = 'cs_fake_' . bin2hex(random_bytes(8));
        $successBase = config('services.stripe.success_url', '');
        // Strip any existing query string before appending ours
        $baseUrl     = strtok($successBase, '?');

        $checkoutUrl = $baseUrl
            . '?payment=success&session_id=' . $sessionId . '&provider=stripe';

        return [
            'id'           => $sessionId,
            'url'          => $checkoutUrl,
            'status'       => 'open',
            'amount_total' => (int) round($amount * 100),
            'currency'     => strtolower($currency),
            'created'      => time(),
            'metadata'     => array_merge(['invoice_id' => (string) $invoiceId], $metadata),
        ];
    }

    public function verifyWebhook(string $payload, string $signature): array
    {
        $event = json_decode($payload, true);

        if (! is_array($event)) {
            throw new RuntimeException('Stripe (fake) webhook payload is not valid JSON.');
        }

        return $event;
    }

    public function refund(string $transactionId, ?float $amount = null): void {}
}
