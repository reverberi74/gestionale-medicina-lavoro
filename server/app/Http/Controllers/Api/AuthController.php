<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class AuthController extends Controller
{
    /**
     * Ritorna il guard JWT configurato come "api".
     *
     * @return JWTGuard
     */
    protected function guard(): JWTGuard
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');

        return $guard;
    }

    /**
     * POST /api/auth/login
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            // ✅ PATCH: rimosso "dns" (evita blocchi su domini .test/.local in dev)
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
        ]);

        $guard = $this->guard();

        // ✅ credenziali
        /** @var string|false $token */
        $token = $guard->attempt([
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        if (! $token) {
            return response()->json([
                'error' => 'INVALID_CREDENTIALS',
                'message' => 'Credenziali non valide.',
            ], 401);
        }

        /** @var User $user */
        $user = $guard->user();

        // ✅ user active check
        if (! $user->is_active) {
            $guard->logout();

            return response()->json([
                'error' => 'USER_DISABLED',
                'message' => 'Account disabilitato.',
            ], 403);
        }

        // ✅ Tenant/domain enforcement (se stai su host tenant)
        $resolvedTenantId = app()->bound('tenant.id') ? app('tenant.id') : null;

        if ($resolvedTenantId !== null) {
            // super_admin può entrare ovunque (control-plane)
            if (($user->role ?? null) !== 'super_admin') {
                if ((int) $user->tenant_id !== (int) $resolvedTenantId) {
                    $guard->logout();

                    return response()->json([
                        'error' => 'TENANT_MISMATCH',
                        'message' => 'Tenant non coerente con il dominio.',
                    ], 403);
                }
            }
        }

        // ✅ Tenant status check (solo se user è legato a un tenant)
        if ($user->tenant_id !== null) {
            $tenant = DB::table('tenants')
                ->select(['id', 'status'])
                ->where('id', $user->tenant_id)
                ->first();

            if (! $tenant) {
                $guard->logout();

                return response()->json([
                    'error' => 'TENANT_NOT_FOUND',
                    'message' => 'Tenant associato non trovato.',
                ], 403);
            }

            if (($tenant->status ?? null) !== 'active') {
                $guard->logout();

                return response()->json([
                    'error' => 'TENANT_NOT_ACTIVE',
                    'message' => 'Tenant non attivo.',
                ], 403);
            }
        }

        // ✅ last_login_at
        try {
            $user->forceFill(['last_login_at' => Carbon::now()])->save();
        } catch (\Throwable $e) {
            // non blocchiamo login per un update non critico
        }

        return response()->json([
            'token_type' => 'bearer',
            'access_token' => $token,
            'expires_in' => $guard->factory()->getTTL() * 60,
            'user' => [
                'id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * GET /api/auth/me
     */
    public function me()
    {
        $guard = $this->guard();

        /** @var User $user */
        $user = $guard->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'last_login_at' => $user->last_login_at,
            ],
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout()
    {
        $this->guard()->logout();

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/auth/refresh
     */
    public function refresh()
    {
        $guard = $this->guard();

        /** @var string $token */
        $token = $guard->refresh();

        return response()->json([
            'token_type' => 'bearer',
            'access_token' => $token,
            'expires_in' => $guard->factory()->getTTL() * 60,
        ]);
    }
}
