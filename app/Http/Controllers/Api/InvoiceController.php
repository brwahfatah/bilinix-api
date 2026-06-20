<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    // ✅ LIST PAGE (DO NOT WRAP DIFFERENTLY)
    public function index()
    {
        $invoices = Invoice::with(['items'])
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $invoices
        ]);
    }

    // ✅ DETAILS PAGE (FULL BILLING SNAPSHOT)
    public function show($id)
    {
        $invoice = Invoice::with([
                'items.domainService',
                'items.serverService',
                'cart.items'
            ])
            ->where('user_id', Auth::id()) // 🔒 security
            ->findOrFail($id);

        // Attach unified service model
        $invoice->items->each(function ($item) {
            $item->service = $item->service();
        });

        return response()->json([
            'success' => true,
            'invoice' => $invoice,
            'cart' => $invoice->cart
        ]);
    }
}

