<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * GET /api/health
     * Returns 200 when the database and cache are reachable, 503 otherwise.
     */
    public function check(): JsonResponse
    {
        $db    = $this->checkDatabase();
        $cache = $this->checkCache();

        $healthy = $db === 'ok' && $cache === 'ok';

        return response()->json([
            'status'   => $healthy ? 'ok' : 'degraded',
            'database' => $db,
            'cache'    => $cache,
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): string
    {
        try {
            DB::select('SELECT 1');
            return 'ok';
        } catch (\Throwable) {
            return 'unavailable';
        }
    }

    private function checkCache(): string
    {
        try {
            $key = '_health_probe_' . getmypid();
            Cache::put($key, 1, 5);
            $hit = Cache::get($key) === 1;
            Cache::forget($key);
            return $hit ? 'ok' : 'unavailable';
        } catch (\Throwable) {
            return 'unavailable';
        }
    }
}
