<?php

namespace App\Integrations\Payments;

use App\Integrations\Payments\Contracts\PaymentProviderInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripePaymentProvider implements PaymentProviderInterface
{
    private const API_BASE      = 'https://api.stripe.com/v1';
    private const SIG_TOLERANCE = 300; // seconds

    private string $secretKey;
    private string $webhookSecret;
    private string $successUrl;
    private string $cancelUrl;

    public function __construct()
    {
        $this->secretKey     = config('services.stripe.key', '');
        $this->webhookSecret = config('services.stripe.webhook_secret', '');
        $this->successUrl    = rtrim(config('services.stripe.success_url', ''), '/');
        $this->cancelUrl     = rtrim(config('services.stripe.cancel_url', ''), '/');

        $this->assertConfigured();
    }

    // ── PaymentProviderInterface ───────────────────────────────────────────────

    public function createCheckoutSession(
        int    $invoiceId,
        float  $amount,
        string $currency,
        array  $metadata = [],
    ): array {
        $response = $this->http()->asForm()->post(self::API_BASE . '/checkout/sessions', [
            'mode'                                                => 'payment',
            'payment_method_types'                                => ['card'],
            'line_items'                                          => [
                [
                    'quantity'   => 1,
                    'price_data' => [
                        'currency'     => strtolower($currency),
                        'unit_amount'  => (int) round($amount * 100),
                        'product_data' => [
                            'name' => "Invoice #{$invoiceId}",
                        ],
                    ],
                ],
            ],
            'success_url' => $this->successUrl . '?session_id={CHECKOUT_SESSION_ID}&provider=stripe',
            'cancel_url'  => $this->cancelUrl  . '?session_id={CHECKOUT_SESSION_ID}&provider=stripe',
            'metadata'    => array_merge(['invoice_id' => (string) $invoiceId], $metadata),
        ]);

        $this->assertOk($response, 'create checkout session');

        return $response->json();
    }

    /**
     * Verify a Stripe webhook signature and return the parsed event.
     *
     * Implements Stripe's HMAC-SHA256 scheme:
     *   signed_payload = "{t}.{raw_body}"
     *   expected       = HMAC-SHA256(signed_payload, webhook_secret)
     */
    public function verifyWebhook(string $payload, string $signature): array
    {
        if (empty($this->webhookSecret)) {
            throw new RuntimeException('Stripe webhook secret is not configured.');
        }

        $parts     = [];
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $signature) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            if ($key === 't') {
                $timestamp = (int) $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if (! $timestamp || empty($signatures)) {
            throw new RuntimeException('Invalid Stripe-Signature header format.');
        }

        if (abs(time() - $timestamp) > self::SIG_TOLERANCE) {
            throw new RuntimeException('Stripe webhook timestamp is outside the tolerance window.');
        }

        $signedPayload = $timestamp . '.' . $payload;
        $expected      = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        $valid = false;
        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                $valid = true;
                break;
            }
        }

        if (! $valid) {
            throw new RuntimeException('Stripe webhook signature verification failed.');
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            throw new RuntimeException('Stripe webhook payload is not valid JSON.');
        }

        return $event;
    }

    public function refund(string $transactionId, ?float $amount = null): void
    {
        $params = ['payment_intent' => $transactionId];

        if ($amount !== null) {
            $params['amount'] = (int) round($amount * 100);
        }

        $response = $this->http()->asForm()->post(self::API_BASE . '/refunds', $params);

        $this->assertOk($response, 'refund');
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withBasicAuth($this->secretKey, '')->timeout(15);
    }

    private function assertOk(
        \Illuminate\Http\Client\Response $response,
        string $action,
    ): void {
        if ($response->failed()) {
            $error = $response->json('error.message') ?? $response->body();
            throw new RuntimeException("Stripe [{$action}] failed: {$error}");
        }
    }

    private function assertConfigured(): void
    {
        if (empty($this->secretKey)) {
            throw new RuntimeException(
                'Stripe is not configured. Set STRIPE_SECRET_KEY in .env'
            );
        }
    }
}
