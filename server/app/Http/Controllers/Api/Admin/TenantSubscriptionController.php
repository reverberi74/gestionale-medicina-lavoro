<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantSubscriptionController extends Controller
{
    /**
     * POST /api/admin/tenants/{tenant}/subscription
     * solo super_admin (in pratica già garantito da middleware + defense in depth qui)
     *
     * Payload:
     * - plan_code: string (required)
     * - status: trial|active|past_due|canceled|suspended|expired (optional, default active)
     * - period_days: int (optional, default 30 per monthly / 365 per yearly)
     */
    public function store(Request $request, Tenant $tenant)
    {
        $user = auth('api')->user();

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

        $result = DB::transaction(function () use ($tenant, $plan, $status, $periodDays, $user) {
            // Lock tenant row to avoid races
            $lockedTenant = Tenant::query()
                ->with(['currentSubscription'])
                ->lockForUpdate()
                ->findOrFail($tenant->id);

            $start = now();
            $end = $start->copy()->addDays($periodDays);

            // Close current subscription (if active-like)
            if ($lockedTenant->currentSubscription) {
                $current = $lockedTenant->currentSubscription;
                if (in_array($current->status, ['trial', 'active', 'past_due'], true)) {
                    $current->status = 'canceled';
                    $current->cancel_at_period_end = false;
                    $current->save();
                }
            }

            $sub = Subscription::create([
                'tenant_id' => $lockedTenant->id,
                'plan_id' => $plan->id,
                'status' => $status,
                'current_period_start_at' => $start,
                'current_period_end_at' => $end,
                'cancel_at_period_end' => false,
                'provider' => 'manual',
                'provider_ref' => null,
                'meta' => [
                    'assigned_by' => $user->id,
                ],
            ]);

            $lockedTenant->current_subscription_id = $sub->id;
            $lockedTenant->save();

            $sub->load('plan');

            return $sub;
        });

        return (new SubscriptionResource($result))->response();
    }
}
