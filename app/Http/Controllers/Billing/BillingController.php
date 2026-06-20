<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\BillingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class BillingController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly BillingService $billing) {}

    /**
     * GET /api/billing/invoices
     */
    public function index(Request $request): JsonResponse
    {
        $list = $this->billing->list($request->user());

        return $this->success(
            array_map(fn($dto) => $dto->toArray(), $list),
            'Invoices retrieved'
        );
    }

    /**
     * GET /api/billing/invoices/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $invoice = $this->billing->get($request->user(), $id);
            return $this->success($invoice->toArray(), 'Invoice retrieved');
        } catch (RuntimeException $e) {
            return $this->notFound($e->getMessage());
        }
    }

    /**
     * POST /api/billing/invoices/{id}/pay
     */
    public function pay(Request $request, int $id): JsonResponse
    {
        try {
            $result = $this->billing->pay($request->user(), $id);
            return $this->success($result, 'Redirect to complete payment');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        }
    }
}
