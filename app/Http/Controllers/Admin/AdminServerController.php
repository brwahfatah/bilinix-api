<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Server;

class AdminServerController extends Controller
{
    public function index()
    {
        return Server::with('user')->latest()->get();
    }

    public function activate(Server $server)
    {
        $server->update(['status' => 'active']);
        return response()->json(['message' => 'Server activated']);
    }

    public function suspend(Server $server)
    {
        $server->update(['status' => 'suspended']);
        return response()->json(['message' => 'Server suspended']);
    }

    public function terminate(Server $server)
    {
        $server->update(['status' => 'terminated']);
        return response()->json(['message' => 'Server terminated']);
    }
}
