<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Tenant;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    /**
     * GET /api/billing/status
     * - richiede solo auth (NON subscription middleware), così la UI può vedere lo stato anche se scaduta.
     */
    public function status(Request $request)
    {
        $user = $request->user();

        $resolvedTenantId = app()->bound('tenant.id') ? (int) app('tenant.id') : null;
        $userTenantId = $user->tenant_id !== null ? (int) $user->tenant_id : null;

        $tenantId = $resolvedTenantId ?? $userTenantId;

        if (($user->role ?? null) !== 'super_admin') {
            if ($tenantId === null) {
                return response()->json([
                    'error' => 'TENANT_REQUIRED',
                    'message' => 'Tenant richiesto.',
                ], 403);
            }

            if ($resolvedTenantId !== null && $userTenantId !== null && $resolvedTenantId !== $userTenantId) {
                return response()->json([
                    'error' => 'TENANT_MISMATCH',
                    'message' => 'Tenant non coerente con il dominio.',
                ], 403);
            }
        }

        $tenant = null;
        if ($tenantId !== null) {
            $tenant = Tenant::query()->with(['currentSubscription.plan'])->find($tenantId);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'key' => $tenant->key,
                'name' => $tenant->name,
                'status' => $tenant->status,
                'current_subscription_id' => $tenant->current_subscription_id,
            ] : null,

            // ✅ niente wrapper "data": serializziamo la Resource inline
            'subscription' => ($tenant && $tenant->currentSubscription)
                ? new SubscriptionResource($tenant->currentSubscription)
                : null,

            'config' => [
                'trial_days' => (int) config('billing.trial_days', 14),
                'grace_days' => (int) config('billing.grace_days', 7),
            ],
        ]);
    }
}
