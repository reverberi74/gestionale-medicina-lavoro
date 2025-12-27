<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();

        // Se non autenticato, non interferiamo (ci pensa auth:api dove presente)
        if (! $user) {
            return $next($request);
        }

        if (! (bool) ($user->is_active ?? true)) {
            return response()->json([
                'ok' => false,
                'error' => 'USER_DISABLED',
                'message' => 'Account disabilitato.',
            ], 403);
        }

        return $next($request);
    }
}
