<?php

use App\Http\Controllers\Api\AuthController;
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

// ✅ Auth (registry)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:api')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });
});
