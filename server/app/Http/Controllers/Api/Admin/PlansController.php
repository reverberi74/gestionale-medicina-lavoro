<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlansController extends Controller
{
    /**
     * GET /api/admin/plans
     * solo super_admin
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (($user->role ?? null) !== 'super_admin') {
            return response()->json([
                'error' => 'FORBIDDEN',
                'message' => 'Accesso negato.',
            ], 403);
        }

        $plans = Plan::query()
            ->orderBy('billing_period')
            ->orderBy('price_cents')
            ->get();

        return PlanResource::collection($plans);
    }
}
