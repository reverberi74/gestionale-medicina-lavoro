<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\Admin\PlansController;
use App\Http\Controllers\Api\Admin\TenantSubscriptionController;
use App\Http\Middleware\EnsureAdminDomainOnly;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function (Request $request) {
    $tenantKey = app()->bound('tenant.key') ? app('tenant.key') : null;
    $tenantId  = app()->bound('tenant.id') ? app('tenant.id') : null;
    $tenantDbResolved = app()->bound('tenant.db') ? app('tenant.db') : null;

    return response()->json([
        'ok' => true,
        'host' => $request->getHost(),
        'default_connection' => config('database.default'),

        'tenant_key' => $tenantKey,
        'tenant_id' => $tenantId,
        'tenant_db_resolved' => $tenantDbResolved,

        'registry_db' => DB::connection('registry')->getDatabaseName(),
        'tenant_db' => DB::connection('tenant')->getDatabaseName(),
        'tenant_connection_ok' => true,
    ]);
});

// Auth (JWT)
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware(['throttle:login']);

    // Per tutte le rotte autenticate: enforce dominio coerente col ruolo
    Route::post('logout', [AuthController::class, 'logout'])->middleware(['auth:api', 'domain.scope', 'user.active']);
    Route::get('me', [AuthController::class, 'me'])->middleware(['auth:api', 'domain.scope', 'user.active']);
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware(['auth:api', 'domain.scope', 'user.active']);
});

// Billing status (solo auth + domain scope, così anche se scaduto la UI vede lo stato)
Route::get('billing/status', [BillingController::class, 'status'])
    ->middleware(['auth:api', 'domain.scope', 'user.active']);

// Admin (control-plane): super_admin + SOLO dominio root/admin
Route::prefix('admin')
    ->middleware([
        'auth:api',
        'user.active',
        'throttle:admin',
        EnsureAdminDomainOnly::class,
        EnsureSuperAdmin::class,
    ])
    ->group(function () {
        Route::get('plans', [PlansController::class, 'index']);
        Route::post('tenants/{tenant}/subscription', [TenantSubscriptionController::class, 'store']);
    });

// Example protected route (tenant-only + subscription enforcement)
Route::get('protected/ping', function () {
    return response()->json(['ok' => true, 'ts' => now()->toISOString()]);
})->middleware(['auth:api', 'domain.scope', 'tenant.domain', EnsureSubscriptionActive::class]);
