<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\ServiceLifecycleManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function pay(Request $request, Invoice $invoice, ServiceLifecycleManager $lifecycle)
    {
        Log::info("PaymentController@pay called", [
            'user_id' => auth()->id(),
            'invoice_id' => $invoice->id,
            'invoice_status' => $invoice->status
        ]);

        if ($invoice->user_id !== auth()->id()) {
            Log::warning("Unauthorized payment attempt", [
                'user_id' => auth()->id(),
                'invoice_id' => $invoice->id
            ]);
            abort(403, 'Unauthorized');
        }

        if ($invoice->status === 'paid') {
            Log::info("Invoice already paid", ['invoice_id' => $invoice->id]);
            return response()->json(['message' => 'Invoice already paid']);
        }

        try {
            DB::transaction(function () use ($invoice, $lifecycle) {
                Log::info("Starting payment transaction", ['invoice_id' => $invoice->id]);

                $invoice->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
                Log::info("Invoice marked as paid", [
                    'invoice_id' => $invoice->id,
                    'status' => $invoice->status,
                    'paid_at' => $invoice->paid_at
                ]);

                $invoice->load('items');
                Log::info("Loaded invoice items", ['count' => $invoice->items->count()]);

                foreach ($invoice->items as $item) {
                    Log::info("Processing item", [
                        'item_id' => $item->id,
                        'type' => $item->type,
                        'description' => $item->description
                    ]);

                    // ===== SERVER ITEMS =====
                    if ($item->type === 'server') {
                        $server = $item->serverService; // use serverService relation

                        if (!$server) {
                            Log::info("Server not found, creating...", ['item_id' => $item->id]);
                            $server = $lifecycle->createServer($item);

                            // Save the newly created server ID in the invoice item
                            $item->service_id = $server->id;
                            $item->save();

                            Log::info("Server created", ['server_id' => $server->id]);
                        }

                        $lifecycle->activateServer($server);
                        Log::info("Server activated", ['server_id' => $server->id]);
                    }

                    // ===== DOMAIN ITEMS =====
                    if ($item->type === 'domain') {
                        $domain = $item->domainService;
                        if ($domain) {
                            $lifecycle->activateDomain($domain);
                            Log::info("Domain activated", ['domain_id' => $domain->id]);
                        }
                    }

                    // ===== RENEWALS =====
                    if ($item->description === 'domain_renewal') {
                        $domain = $item->domainService;
                        if ($domain) {
                            $lifecycle->renewDomain($domain);
                            Log::info("Domain renewed", ['domain_id' => $domain->id]);
                        }
                    }

                    if ($item->description === 'server_renewal') {
                        $server = $item->serverService;
                        if ($server) {
                            $lifecycle->renewServer($server);
                            Log::info("Server renewed", ['server_id' => $server->id]);
                        }
                    }
                }

                Log::info("Payment transaction completed successfully", ['invoice_id' => $invoice->id]);
            });
        } catch (\Throwable $e) {
            Log::error("Payment transaction failed", [
                'invoice_id' => $invoice->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Payment failed'], 500);
        }

        return response()->json([
            'message' => 'Payment successful',
            'invoice_status' => $invoice->status
        ]);
    }
}
