<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects tokens of accounts that were deactivated or locked after signing in,
 * and revokes all their tokens.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->canSignIn()) {
            $user->tokens()->delete();

            return response()->json(['message' => 'This account is disabled. Contact the administrator.'], 403);
        }

        return $next($request);
    }
}
