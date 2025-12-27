<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDomainScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'ok' => false,
                'error' => 'UNAUTHENTICATED',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $host = strtolower($request->getHost());
        $resolvedTenantId = app()->bound('tenant.id') ? app('tenant.id') : null;
        $role = (string) ($user->role ?? '');

        if ($role === 'super_admin') {
            if (!$this->isAllowedAdminHost($host)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'ADMIN_DOMAIN_ONLY',
                    'message' => 'Super admin can operate only from the admin domain.',
                    'host' => $host,
                ], 403);
            }

            return $next($request);
        }

        // Non-super_admin: deve essere su host tenant
        if ($resolvedTenantId === null) {
            return response()->json([
                'ok' => false,
                'error' => 'TENANT_DOMAIN_REQUIRED',
                'message' => 'Tenant users can operate only from a tenant domain.',
                'host' => $host,
            ], 403);
        }

        if ($user->tenant_id === null) {
            return response()->json([
                'ok' => false,
                'error' => 'USER_TENANT_REQUIRED',
                'message' => 'User is not linked to a tenant.',
            ], 403);
        }

        if ((int) $user->tenant_id !== (int) $resolvedTenantId) {
            return response()->json([
                'ok' => false,
                'error' => 'TENANT_MISMATCH',
                'message' => 'Tenant mismatch for current domain.',
                'host' => $host,
                'resolved_tenant_id' => (int) $resolvedTenantId,
                'user_tenant_id' => (int) $user->tenant_id,
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
