<?php

namespace App\DTO;

use App\Enums\PaymentStatus;
use Carbon\Carbon;

final class PaymentDTO
{
    public function __construct(
        public readonly string  $id,
        public readonly string  $invoiceId,
        public readonly string  $amount,
        public readonly string  $currency,
        public readonly string  $provider,
        public readonly string  $status,
        public readonly ?string $checkoutUrl,
        public readonly string  $createdAt,
    ) {}

    /**
     * Build from a Stripe Checkout Session object.
     * amount_total is in the smallest currency unit (cents for USD).
     */
    public static function fromStripeSession(array $session, string $invoiceId): self
    {
        $status = PaymentStatus::fromStripe($session['status'] ?? 'open');

        return new self(
            id:          (string) ($session['id'] ?? ''),
            invoiceId:   $invoiceId,
            amount:      number_format((int) ($session['amount_total'] ?? 0) / 100, 2, '.', ''),
            currency:    strtoupper($session['currency'] ?? 'USD'),
            provider:    'stripe',
            status:      $status->value,
            checkoutUrl: $session['url'] ?? null,
            createdAt:   Carbon::createFromTimestamp((int) ($session['created'] ?? time()))
                               ->toIso8601String(),
        );
    }

    /**
     * Build from cache (used for success/cancel redirect responses).
     * Webhook confirms the real final status; these are informational only.
     */
    public static function fromCache(string $sessionId, array $cached, PaymentStatus $status): self
    {
        return new self(
            id:          $sessionId,
            invoiceId:   (string) ($cached['invoice_id'] ?? ''),
            amount:      (string) ($cached['amount'] ?? '0.00'),
            currency:    strtoupper((string) ($cached['currency'] ?? 'USD')),
            provider:    (string) ($cached['provider'] ?? 'stripe'),
            status:      $status->value,
            checkoutUrl: null,
            createdAt:   now()->toIso8601String(),
        );
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'invoice_id'   => $this->invoiceId,
            'amount'       => $this->amount,
            'currency'     => $this->currency,
            'provider'     => $this->provider,
            'status'       => $this->status,
            'checkout_url' => $this->checkoutUrl,
            'created_at'   => $this->createdAt,
        ];
    }
}
