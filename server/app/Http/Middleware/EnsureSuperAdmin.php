<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();

        if (! $user || ($user->role ?? null) !== 'super_admin') {
            return response()->json([
                'ok' => false,
                'error' => 'FORBIDDEN',
                'message' => 'Super admin required.',
            ], 403);
        }

        return $next($request);
    }
}
