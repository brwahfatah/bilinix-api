<?php

namespace App\Http\Controllers\Api;

use App\Models\Server;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ServerPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServerController
{
    public function index(Request $request)
    {
        return $request->user()->servers()->with('invoice')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required',
            'os' => 'required',
            'server_plan_id' => 'required|exists:ServerPlan,id',
            'domain_id' => 'nullable|exists:domains,id',
            'invoice_id' => 'required|exists:invoices,id',
        ]);

        // 🔒 Fetch invoice (must belong to user & unpaid)
        $invoice = Invoice::where('id', $data['invoice_id'])
            ->where('user_id', auth()->id())
            ->where('status', 'unpaid')
            ->firstOrFail();

        // 💰 Server pricing: fetch selected plan and its price
        // (ServerPlan model has a 'price' decimal column)
        $plan = ServerPlan::findOrFail($data['server_plan_id']);
        $price = (float) $plan->price;

        // 1️⃣ Create server (PROVISIONING)
        $server = Server::create([
            'user_id' => auth()->id(),
            'domain_id' => $data['domain_id'] ?? null,
            'invoice_id' => $invoice->id,
            'name' => $data['name'],
            'os' => $data['os'],
            'server_plan_id' => $data['server_plan_id'],
            'status' => 'provisioning',
            'ip_address' => null,
            'ssh_key' => Str::random(32),
        ]);

        // 2️⃣ Invoice item
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'type' => 'server',
            'service_id' => $server->id,
            'description' => "VPS ({$server->plan})",
            'amount' => $price,
        ]);

        // 3️⃣ Update invoice total
        $invoice->increment('amount', $price);

        return response()->json([
            'message' => 'VPS added to invoice. Payment required.',
            'server' => $server,
            'invoice' => $invoice->fresh()->load('items'),
        ]);
    }


    public function stop(Server $server)
{
    abort_if($server->user_id !== auth()->id(), 403);

    if ($server->status === 'stopped') {
        return response()->json(['message' => 'Server already stopped'], 422);
    }

    $server->update(['status' => 'stopped']);

    return response()->json(['message' => 'Server stopped']);
}





    public function destroy(Server $server)
    {
        abort_if($server->user_id !== auth()->id(), 403);

        $server->delete();

        return response()->json(['message' => 'Server destroyed']);
    }

 public function reboot(Server $server)
{
    abort_if($server->user_id !== auth()->id(), 403);

    $server->update(['status' => 'rebooting']);

    // simulate reboot delay
    dispatch(function () use ($server) {
        sleep(3);
        $server->update(['status' => 'running']);
    })->afterResponse();

    return response()->json(['message' => 'Server rebooting']);
}












public function shutdown(Server $server)
{
    abort_if($server->user_id !== auth()->id(), 403);

    if ($server->status !== 'running') {
        return response()->json(['message' => 'Server not running'], 422);
    }

    $server->update(['status' => 'stopped']);

    return response()->json(['message' => 'Server stopped']);
}






public function start(Server $server)
{
    abort_if($server->user_id !== auth()->id(), 403);

    if ($server->status === 'running') {
        return response()->json(['message' => 'Server already running'], 422);
    }

    $server->update(['status' => 'running']);

    return response()->json(['message' => 'Server started']);
}








public function show(Server $server)
{
    abort_if($server->user_id !== auth()->id(), 403);

    return $server->load('plan');
}


}
