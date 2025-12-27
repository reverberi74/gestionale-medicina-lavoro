<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Resolve tenant (subdomain) for every API request.
        $middleware->appendToGroup('api', \App\Http\Middleware\ResolveTenant::class);
        // Audit trail (registry)
        $middleware->appendToGroup('api', \App\Http\Middleware\AuditTrail::class);

        // Middleware aliases (Laravel 12)
        $middleware->alias([
            'admin.domain' => \App\Http\Middleware\EnsureAdminDomainOnly::class,
            'tenant.domain' => \App\Http\Middleware\EnsureTenantDomainOnly::class,
            'domain.scope' => \App\Http\Middleware\EnsureDomainScope::class,
            'role' => \App\Http\Middleware\EnsureRole::class,
            'user.active' => \App\Http\Middleware\EnsureUserActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
