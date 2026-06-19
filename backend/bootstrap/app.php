<?php

use App\Exceptions\SeatConflictException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Customer surface (Nuxt proxy + API) is scoped to the primary
            // domain so it cannot answer on the admin subdomain. Filament
            // declares its own ->domain() on the panel provider, producing
            // the reciprocal constraint. Asserted by RouteDomainScopingTest.
            Route::domain(config('app.primary_domain'))->group(function (): void {
                Route::middleware('api')
                    ->prefix('api')
                    ->group(base_path('routes/api.php'));

                Route::middleware('web')
                    ->group(base_path('routes/web.php'));
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // nginx terminates TLS and forwards to PHP-FPM on :9000 over plain
        // FastCGI, conveying the original scheme via X-Forwarded-Proto. :9000
        // is never published outside the Docker network, so nginx is the only
        // thing that can reach FPM — trusting all proxies is safe here. Without
        // this, Laravel treats the FastCGI hop as insecure and, with
        // SESSION_SECURE_COOKIE=true in production, emits http:// redirects and
        // refuses the session cookie, looping admin/Sanctum logins behind TLS.
        $middleware->trustProxies(at: '*');

        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->throttleApi(env('API_THROTTLE', '60,1'));

        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (SeatConflictException $e) {
            return response()->json([
                'errors' => [[
                    'field' => 'seatIds',
                    'message' => $e->getMessage(),
                    'unavailableSeatIds' => $e->unavailableSeatIds,
                ]],
            ], 409);
        });
    })->create();
