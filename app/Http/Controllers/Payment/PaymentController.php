<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly PaymentService $payments) {}

    /**
     * POST /api/payments/checkout
     *
     * Body: { "invoice_id": 123, "provider": "stripe" }
     * Returns a checkout_url the frontend should redirect the user to.
     */
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'integer', 'min:1'],
            'provider'   => ['sometimes', 'string', 'in:stripe'],
        ]);

        try {
            $dto = $this->payments->createCheckout(
                user:      $request->user(),
                invoiceId: (int) $data['invoice_id'],
                provider:  $data['provider'] ?? 'stripe',
            );

            return $this->success($dto->toArray(), 'Checkout session created');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        }
    }

    /**
     * GET /api/payments/success?session_id=cs_xxx&provider=stripe
     *
     * Called by the frontend after the payment provider redirects the user back.
     * Informational — the webhook is the authoritative confirmation.
     */
    public function success(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => ['required', 'string'],
            'provider'   => ['sometimes', 'string'],
        ]);

        $dto = $this->payments->success(
            sessionId: $request->query('session_id'),
            provider:  $request->query('provider', 'stripe'),
        );

        return $this->success($dto->toArray(), 'Payment is being processed');
    }

    /**
     * GET /api/payments/cancel?session_id=cs_xxx&provider=stripe
     *
     * Called by the frontend when the user cancels at the provider's checkout page.
     */
    public function cancel(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => ['required', 'string'],
            'provider'   => ['sometimes', 'string'],
        ]);

        $dto = $this->payments->cancel(
            sessionId: $request->query('session_id'),
            provider:  $request->query('provider', 'stripe'),
        );

        return $this->success($dto->toArray(), 'Payment was cancelled');
    }

    /**
     * POST /api/payments/webhook/stripe
     *
     * Stripe sends signed webhook events here.
     * No auth middleware — signature is verified inside PaymentService::webhook().
     * Raw body MUST be read before any JSON parsing; $request->getContent() gives it.
     */
    public function stripeWebhook(Request $request): JsonResponse
    {
        $payload   = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');

        try {
            $result = $this->payments->webhook('stripe', $payload, $signature);
            return $this->success($result, 'Webhook received');
        } catch (RuntimeException $e) {
            // Return 400 so Stripe retries the delivery
            return $this->error($e->getMessage(), null, 400);
        }
    }
}
