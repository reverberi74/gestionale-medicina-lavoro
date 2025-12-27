<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuditTrail
{
    public function handle(Request $request, Closure $next): Response
    {
        $t0 = microtime(true);

        $response = $next($request);

        try {
            $this->writeAudit($request, $response, $t0);
        } catch (\Throwable $e) {
            // mai bloccare la request per audit
        }

        return $response;
    }

    private function writeAudit(Request $request, Response $response, float $t0): void
    {
        $path = '/' . ltrim($request->path(), '/'); // es: /api/auth/login
        $method = strtoupper($request->method());
        $status = (int) $response->getStatusCode();

        // Skip noise
        if ($request->is('api/health')) {
            return;
        }

        $tenantId = app()->bound('tenant.id') ? (int) app('tenant.id') : null;
        $host = strtolower($request->getHost());
        $ip = (string) $request->ip();
        $ua = (string) $request->userAgent();

        $user = auth('api')->user();
        $userId = $user?->id ?? null;

        $event = null;
        $meta = [
            'duration_ms' => (int) round((microtime(true) - $t0) * 1000),
        ];

        // 1) Login audit (success/fail)
        if ($request->is('api/auth/login') && $request->isMethod('post')) {
            $event = ($status >= 200 && $status < 300) ? 'AUTH_LOGIN_SUCCESS' : 'AUTH_LOGIN_FAILED';
            $meta['email'] = Str::lower((string) $request->input('email', ''));

            // prova a prendere "error" dal JSON response
            $body = (string) $response->getContent();
            $decoded = json_decode($body, true);
            if (is_array($decoded) && isset($decoded['error'])) {
                $meta['error'] = $decoded['error'];
            }
        }

        // 2) Admin audit (tutte le chiamate /api/admin/*)
        if ($event === null && $request->is('api/admin/*')) {
            $event = 'ADMIN_REQUEST';
        }

        // 3) Tenant write audit (POST/PUT/PATCH/DELETE su dominio tenant, esclusi auth/admin)
        $isWrite = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        if ($event === null && $isWrite && $tenantId !== null && ! $request->is('api/auth/*') && ! $request->is('api/admin/*')) {
            $event = 'TENANT_WRITE';
        }

        if ($event === null) {
            return;
        }

        DB::connection('registry')->table('audit_logs')->insert([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'event' => $event,
            'method' => $method,
            'path' => $path,
            'status_code' => $status,
            'ip' => $ip,
            'host' => $host,
            'user_agent' => $ua !== '' ? $ua : null,
            'meta' => empty($meta) ? null : json_encode($meta),
            'created_at' => now(),
        ]);
    }
}
