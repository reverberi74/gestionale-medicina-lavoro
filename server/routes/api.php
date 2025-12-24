<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\Admin\PlansController;
use App\Http\Controllers\Api\Admin\TenantSubscriptionController;
use App\Http\Middleware\EnsureSubscriptionActive;
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
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:api');
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('auth:api');
});

// Billing status (solo auth, così anche se scaduto la UI vede lo stato)
Route::get('billing/status', [BillingController::class, 'status'])->middleware('auth:api');

// Admin (super_admin)
Route::prefix('admin')->middleware('auth:api')->group(function () {
    Route::get('plans', [PlansController::class, 'index']);
    Route::post('tenants/{tenant}/subscription', [TenantSubscriptionController::class, 'store']);
});

// Example protected route (auth + subscription enforcement)
Route::get('protected/ping', function () {
    return response()->json(['ok' => true, 'ts' => now()->toISOString()]);
})->middleware(['auth:api', EnsureSubscriptionActive::class]);
