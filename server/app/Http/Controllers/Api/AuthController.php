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

        $host = strtolower($request->getHost());
        $resolvedTenantId = app()->bound('tenant.id') ? app('tenant.id') : null;

        // ✅ Domain enforcement (A/1)
        if (($user->role ?? null) === 'super_admin') {
            // super_admin: SOLO control-plane/admin host
            if (! $this->isAllowedAdminHost($host)) {
                $guard->logout();

                return response()->json([
                    'error' => 'ADMIN_DOMAIN_ONLY',
                    'message' => 'Il super admin può accedere solo dal dominio admin (control-plane).',
                    'host' => $host,
                ], 403);
            }
        } else {
            // tenant users: SOLO su dominio tenant (deve risolvere tenant.id)
            if ($resolvedTenantId === null) {
                $guard->logout();

                return response()->json([
                    'error' => 'TENANT_DOMAIN_REQUIRED',
                    'message' => 'Gli utenti tenant possono accedere solo dal dominio del tenant.',
                    'host' => $host,
                ], 403);
            }

            if ($user->tenant_id === null) {
                $guard->logout();

                return response()->json([
                    'error' => 'USER_TENANT_REQUIRED',
                    'message' => 'Utente non associato a nessun tenant.',
                ], 403);
            }

            if ((int) $user->tenant_id !== (int) $resolvedTenantId) {
                $guard->logout();

                return response()->json([
                    'error' => 'TENANT_MISMATCH',
                    'message' => 'Tenant non coerente con il dominio.',
                ], 403);
            }
        }

        // ✅ Tenant status check (solo se user è legato a un tenant)
        if ($user->tenant_id !== null) {
            $tenant = DB::connection('registry')->table('tenants')
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
