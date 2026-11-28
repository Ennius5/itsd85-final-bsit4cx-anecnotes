<?php

namespace App\Http\Controllers;

use App\Http\Resources\SectionResource;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Sign in and get a token.
     *
     * Roles: public. Rate-limited to 5 attempts per minute per email and IP.
     *
     * @unauthenticated
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:255'],
            /** Shown in the token list, e.g. "Android - Redmi Note 12". */
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $key = Str::lower($data['email']).'|'.$request->ip();
        $maxAttempts = (int) config('auth.login_max_attempts', 5);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json(['message' => "Too many login attempts. Please try again in {$seconds} seconds."], 429)
                ->header('Retry-After', (string) $seconds);
        }

        $user = User::where('email', Str::lower($data['email']))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            AuditLog::record('login_failed', $user, null, ['email' => Str::lower($data['email'])]);

            return response()->json(['message' => 'These credentials do not match our records.'], 401);
        }

        if (! $user->canSignIn()) {
            return response()->json(['message' => 'This account is disabled. Contact the administrator.'], 403);
        }

        RateLimiter::clear($key);
        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken($data['device_name'])->plainTextToken;
        AuditLog::record('login', $user, null, ['device' => $data['device_name']], $user->id);

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Revoke the current token.
     *
     * Roles: any signed-in user.
     */
    public function logout(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $user->currentAccessToken()->delete();
        AuditLog::record('logout', $user);

        return response()->noContent();
    }

    /**
     * Current user and the sections they advise.
     *
     * Roles: any signed-in user.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $sections = $user->advisedSections()
            ->whereHas('schoolYear', fn ($q) => $q->where('is_current', true))
            ->orderBy('grade_level')->orderBy('name')
            ->get();

        return response()->json([
            ...(new UserResource($user))->resolve(),
            'advised_sections' => SectionResource::collection($sections)->resolve(),
        ]);
    }
}
