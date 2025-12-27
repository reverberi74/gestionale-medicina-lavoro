<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Middleware parametrico:
     * - role:super_admin
     * - role:tenant_admin,operator
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json([
                'ok' => false,
                'error' => 'UNAUTHENTICATED',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Supporta sia "role:a,b" sia parametri separati
        $allowed = [];
        foreach ($roles as $chunk) {
            foreach (explode(',', (string) $chunk) as $r) {
                $r = trim($r);
                if ($r !== '') {
                    $allowed[] = $r;
                }
            }
        }
        $allowed = array_values(array_unique($allowed));

        $role = (string) ($user->role ?? '');

        if ($role === '' || ! in_array($role, $allowed, true)) {
            return response()->json([
                'ok' => false,
                'error' => 'FORBIDDEN',
                'message' => 'Insufficient role.',
                'required' => $allowed,
                'role' => $role,
            ], 403);
        }

        return $next($request);
    }
}
