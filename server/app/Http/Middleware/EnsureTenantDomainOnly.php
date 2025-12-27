<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantDomainOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = app()->bound('tenant.id') ? app('tenant.id') : null;

        if ($tenantId !== null) {
            return $next($request);
        }

        return response()->json([
            'ok' => false,
            'error' => 'TENANT_DOMAIN_REQUIRED',
            'message' => 'This endpoint is accessible only from a tenant domain.',
            'host' => strtolower($request->getHost()),
        ], 403);
    }
}
