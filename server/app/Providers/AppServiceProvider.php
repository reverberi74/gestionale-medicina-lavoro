<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Login: 5/min per IP + email (anti brute force)
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email', ''));
            $key = $request->ip() . '|' . $email;

            return Limit::perMinute(5)
                ->by($key)
                ->response(function (Request $request, array $headers) use ($email) {
                    return response()->json([
                        'ok' => false,
                        'error' => 'RATE_LIMITED',
                        'message' => 'Troppi tentativi di login. Riprova più tardi.',
                        'email' => $email !== '' ? $email : null,
                    ], 429, $headers);
                });
        });

        // Admin: 60/min per IP (anti abuso su control-plane)
        RateLimiter::for('admin', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'ok' => false,
                        'error' => 'RATE_LIMITED',
                        'message' => 'Troppe richieste admin. Riprova più tardi.',
                    ], 429, $headers);
                });
        });
    }
}
