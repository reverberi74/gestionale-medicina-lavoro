<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Se non autenticato, lascia che auth middleware gestisca
        if (! $user) {
            return response()->json([
                'error' => 'UNAUTHENTICATED',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Super admin bypass
        if (($user->role ?? null) === 'super_admin') {
            return $next($request);
        }

        // Risoluzione tenant: prima dal resolver host (dominio), poi dal user->tenant_id
        $resolvedTenantId = app()->bound('tenant.id') ? (int) app('tenant.id') : null;
        $userTenantId = $user->tenant_id !== null ? (int) $user->tenant_id : null;

        $tenantId = $resolvedTenantId ?? $userTenantId;

        if ($tenantId === null) {
            return response()->json([
                'error' => 'TENANT_REQUIRED',
                'message' => 'Tenant richiesto per accedere a questa risorsa.',
            ], 403);
        }

        // Se siamo su host tenant, l’utente deve essere di quel tenant (non super_admin)
        if ($resolvedTenantId !== null && $userTenantId !== null && $resolvedTenantId !== $userTenantId) {
            return response()->json([
                'error' => 'TENANT_MISMATCH',
                'message' => 'Tenant non coerente con il dominio.',
            ], 403);
        }

        /** @var Tenant|null $tenant */
        $tenant = Tenant::query()
            ->with(['currentSubscription.plan'])
            ->find($tenantId);

        if (! $tenant) {
            return response()->json([
                'error' => 'TENANT_NOT_FOUND',
                'message' => 'Tenant non trovato.',
            ], 403);
        }

        // Tenant deve essere attivo (coerente con quanto già fai in login)
        if (($tenant->status ?? null) !== 'active') {
            return response()->json([
                'error' => 'TENANT_NOT_ACTIVE',
                'message' => 'Tenant non attivo.',
            ], 403);
        }

        $sub = $tenant->currentSubscription;

        if (! $sub) {
            return response()->json([
                'error' => 'SUBSCRIPTION_MISSING',
                'message' => 'Subscription mancante.',
            ], 402);
        }

        $allowedStatuses = (array) config('billing.allowed_statuses', ['trial', 'active', 'past_due']);
        if (! in_array($sub->status, $allowedStatuses, true)) {
            return response()->json([
                'error' => 'SUBSCRIPTION_INACTIVE',
                'message' => 'Subscription non attiva.',
                'status' => $sub->status,
            ], 402);
        }

        $endAt = $sub->current_period_end_at;
        if ($endAt) {
            $graceDays = (int) config('billing.grace_days', 7);
            $deadline = $endAt->copy()->addDays($graceDays);

            if (now()->greaterThan($deadline)) {
                return response()->json([
                    'error' => 'SUBSCRIPTION_EXPIRED',
                    'message' => 'Subscription scaduta.',
                    'status' => $sub->status,
                    'current_period_end_at' => $endAt->toISOString(),
                    'grace_days' => $graceDays,
                ], 402);
            }
        }

        return $next($request);
    }
}
