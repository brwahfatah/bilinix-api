<?php

namespace App\Http\Controllers\Api;

use App\Models\Domain;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;

class DomainController
{
    public function index(Request $request)
    {
        return $request->user()->domains()->with('invoice')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'domain' => 'required|unique:domains,domain',
            'tld' => 'required|in:.com,.net,.org,.io',
            'nameserver1' => 'required',
            'nameserver2' => 'required',
        ]);

        // 💰 Domain pricing
        $price = match ($data['tld']) {
            '.com' => 10,
            '.net' => 12,
            '.org' => 11,
            '.io' => 30,
        };

        // 1️⃣ Create invoice
        $invoice = Invoice::create([
            'user_id' => auth()->id(),
            'amount' => $price,
            'currency' => 'USD',
            'status' => 'unpaid',
        ]);

        // 2️⃣ Create domain (PENDING)
        $domain = Domain::create([
            'user_id' => auth()->id(),
            'domain' => $data['domain'],
            'status' => 'pending',
            'nameserver1' => $data['nameserver1'],
            'nameserver2' => $data['nameserver2'],
            'invoice_id' => $invoice->id,
        ]);

        // 3️⃣ Invoice item
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'type' => 'domain',
            'service_id' => $domain->id,
            'description' => "Domain registration ({$domain->domain})",
            'amount' => $price,
        ]);

        return response()->json([
            'message' => 'Domain order created. Payment required.',
            'domain' => $domain,
            'invoice' => $invoice->load('items'),
        ]);
    }

    public function destroy(Domain $domain)
    {
        abort_if($domain->user_id !== auth()->id(), 403);

        $domain->delete();

        return response()->json(['message' => 'Domain deleted']);
    }
}
