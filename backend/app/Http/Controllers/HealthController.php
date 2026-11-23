<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    /**
     * Health check.
     *
     * Reports whether the API and database are up, and which commit is deployed.
     *
     * @unauthenticated
     */
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
            $database = 'ok';
        } catch (Throwable) {
            $database = 'down';
        }

        $ok = $database === 'ok';

        return response()->json([
            'status' => $ok ? 'ok' : 'degraded',
            'commit' => config('app.version'),
            'database' => $database,
            'time' => now()->toIso8601String(),
        ], $ok ? 200 : 503);
    }
}
