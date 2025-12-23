<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $tenantKey = $this->extractTenantKey($host);

        app()->instance('tenant.key', $tenantKey);

        // Registry-only request (no tenant)
        if (!$tenantKey) {
            app()->instance('tenant.id', null);
            app()->instance('tenant.db', null);
            return $next($request);
        }

        // Lookup tenant in REGISTRY (control-plane)
        $tenant = DB::connection('registry')
            ->table('tenants')
            ->where('key', $tenantKey)
            ->first();

        if (!$tenant) {
            return response()->json([
                'ok' => false,
                'error' => 'TENANT_NOT_FOUND',
                'tenant_key' => $tenantKey,
                'host' => $host,
            ], 404);
        }

        app()->instance('tenant.id', $tenant->id);
        app()->instance('tenant.db', $tenant->db_name);

        // Switch tenant DB dynamically
        config(['database.connections.tenant.database' => $tenant->db_name]);
        DB::purge('tenant');

        return $next($request);
    }

    private function extractTenantKey(string $host): ?string
    {
        if ($host === 'localhost') {
            return null;
        }

        // raw IP → no tenant
        if (preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $host)) {
            return null;
        }

        $parts = explode('.', $host);
        if (count($parts) < 2) {
            return null;
        }

        $candidate = $parts[0];

        $reserved = ['api', 'app', 'www'];
        if (in_array($candidate, $reserved, true)) {
            return null;
        }

        if (!preg_match('/^[a-z0-9-]+$/', $candidate)) {
            return null;
        }

        return $candidate ?: null;
    }
}
