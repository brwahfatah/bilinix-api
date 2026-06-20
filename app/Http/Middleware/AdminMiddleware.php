<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'admin') {
            return response()->json([
                'data'    => null,
                'message' => 'Forbidden. Admin access required.',
                'errors'  => null,
            ], 403);
        }

        return $next($request);
    }
}
