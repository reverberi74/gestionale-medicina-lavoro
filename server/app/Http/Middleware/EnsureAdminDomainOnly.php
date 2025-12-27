<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminDomainOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        // Se ResolveTenant ha trovato un tenant, NON siamo nel control-plane.
        $tenantKey = app()->bound('tenant.key') ? app('tenant.key') : null;
        if ($tenantKey) {
            return response()->json([
                'ok' => false,
                'error' => 'ADMIN_ACCESS_DENIED',
                'message' => 'Control plane is not accessible from tenant domains.',
                'host' => $host,
            ], 403);
        }

        if (! $this->isAllowedAdminHost($host)) {
            return response()->json([
                'ok' => false,
                'error' => 'ADMIN_ACCESS_DENIED',
                'message' => 'Control plane is accessible only from the admin domain.',
                'host' => $host,
            ], 403);
        }

        return $next($request);
    }

    private function isAllowedAdminHost(string $host): bool
    {
        $allowedHosts = (array) config('admin.allowed_hosts', []);
        if (in_array($host, array_map('strtolower', $allowedHosts), true)) {
            return true;
        }

        $adminDomain = strtolower((string) config('admin.domain', ''));
        if ($adminDomain !== '' && $host === $adminDomain) {
            return true;
        }

        $allowDevAdminSubdomain = (bool) config('admin.allow_dev_admin_subdomain', true);
        if ($allowDevAdminSubdomain && app()->environment(['local', 'development', 'testing'])) {
            if (str_starts_with($host, 'admin.')) {
                return true;
            }
        }

        return false;
    }
}
