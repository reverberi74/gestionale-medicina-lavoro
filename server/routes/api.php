<?php

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
