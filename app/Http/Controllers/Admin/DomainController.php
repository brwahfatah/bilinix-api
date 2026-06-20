<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use Illuminate\Http\JsonResponse;

class DomainController extends Controller
{
    public function index(): JsonResponse
    {
        $domains = Domain::with('user')->latest()->paginate(50);

        return response()->json([
            'data'    => $domains,
            'message' => 'success',
            'errors'  => null,
        ]);
    }
}
