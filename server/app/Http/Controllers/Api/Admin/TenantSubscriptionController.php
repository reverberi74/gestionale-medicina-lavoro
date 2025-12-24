<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\Request;

class TenantSubscriptionController extends Controller
{
    /**
     * POST /api/admin/tenants/{tenant}/subscription
     * solo super_admin
     *
     * Payload:
     * - plan_code: string (required)
     * - status: trial|active|past_due|canceled|suspended|expired (optional, default active)
     * - period_days: int (optional, default 30 per monthly / 365 per yearly)
     */
    public function store(Request $request, Tenant $tenant)
    {
        $user = $request->user();
        if (($user->role ?? null) !== 'super_admin') {
            return response()->json([
                'error' => 'FORBIDDEN',
                'message' => 'Accesso negato.',
            ], 403);
        }

        $data = $request->validate([
            'plan_code' => ['required', 'string', 'max:80'],
            'status' => ['sometimes', 'in:trial,active,past_due,canceled,suspended,expired'],
            'period_days' => ['sometimes', 'integer', 'min:1', 'max:3650'],
        ]);

        $plan = Plan::query()->where('code', $data['plan_code'])->first();
        if (! $plan || ! $plan->is_active) {
            return response()->json([
                'error' => 'PLAN_NOT_FOUND',
                'message' => 'Piano non valido.',
            ], 422);
        }

        $status = $data['status'] ?? 'active';

        $defaultDays = $plan->billing_period === 'yearly' ? 365 : 30;
        $periodDays = (int) ($data['period_days'] ?? $defaultDays);

        $start = now();
        $end = now()->addDays($periodDays);

        // chiudi l’attuale (se esiste) come canceled (se non già chiuso)
        if ($tenant->currentSubscription) {
            $current = $tenant->currentSubscription;
            if (in_array($current->status, ['trial', 'active', 'past_due'], true)) {
                $current->status = 'canceled';
                $current->cancel_at_period_end = false;
                $current->save();
            }
        }

        $sub = Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => $status,
            'current_period_start_at' => $start,
            'current_period_end_at' => $end,
            'cancel_at_period_end' => false,
            'provider' => 'manual',
            'provider_ref' => null,
            'meta' => ['assigned_by' => $user->id],
        ]);

        $tenant->current_subscription_id = $sub->id;
        $tenant->save();

        $sub->load('plan');

        return (new SubscriptionResource($sub))->response();
    }
}
