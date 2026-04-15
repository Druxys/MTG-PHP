<?php

namespace App\Http\Middleware;

use App\Services\ApiTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function __construct(public ApiTokenService $apiTokenService)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return response()->json([
                'error' => 'Access token required. Please provide a valid authentication token.',
            ], 401);
        }

        $tokenRecord = $this->apiTokenService->findValidTokenRecord($token);
        $user = $tokenRecord?->user;

        if (! $tokenRecord || ! $user instanceof AuthenticatableUser) {
            return response()->json([
                'error' => 'Invalid token. Please authenticate again.',
            ], 401);
        }

        $tokenRecord->forceFill(['last_used_at' => now()])->save();
        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }
}
