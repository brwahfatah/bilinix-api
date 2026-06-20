<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'servers' => [
                'total' => $user->servers()->count(),
                'running' => $user->servers()->where('status', 'running')->count(),
                'provisioning' => $user->servers()->where('status', 'provisioning')->count(),
                'suspended' => $user->servers()->where('status', 'suspended')->count(),
                'terminated' => $user->servers()->where('status', 'terminated')->count(),
            ],

            'invoices' => [
                'total' => $user->invoices()->count(),
                'paid' => $user->invoices()->where('status', 'paid')->count(),
                'unpaid' => $user->invoices()->where('status', 'unpaid')->count(),
            ],

            'domains' => [
                'total' => $user->domains()->count(),
                'active' => $user->domains()->where('status', 'active')->count(),
                'pending' => $user->domains()->where('status', 'pending')->count(),
                'expired' => $user->domains()->where('status', 'expired')->count(),
            ],
        ]);
    }
}
