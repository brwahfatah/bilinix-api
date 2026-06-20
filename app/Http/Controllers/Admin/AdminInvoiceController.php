<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\PaymentController;
use App\Models\Invoice;
use App\Services\ServiceLifecycleManager;
use Illuminate\Http\Request;


class AdminInvoiceController extends Controller
{
    // GET /admin/invoices
    public function index()
    {
        return Invoice::with(['user', 'items'])
            ->latest()
            ->paginate(20);
    }

    // POST /admin/invoices/{invoice}/mark-paid
    public function markPaid(
        Request $request,
        Invoice $invoice,
        ServiceLifecycleManager $lifecycle
    ) {
        if ($invoice->status === 'paid') {
            return response()->json([
                'message' => 'Invoice already paid'
            ]);
        }

        // Reuse payment logic
        app(PaymentController::class)->pay($request, $invoice, $lifecycle);

        return response()->json([
            'message' => 'Invoice marked paid & services activated'
        ]);
    }
}
