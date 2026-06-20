<?php

namespace App\Http\Controllers\Admin;
use App\Jobs\ProvisionServer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;


class ServerApprovalController extends Controller
{
    public function approve(Server $server)
    {
        abort_if($server->status !== 'awaiting_approval', 400);

        $server->update([
            'approved_at' => now(),
            'approved_by' => auth()->id(),
            'status' => 'provisioning',
        ]);

        ProvisionServer::dispatch($server);

        return response()->json([
            'message' => 'Server approved and provisioning started',
        ]);
    }
}
