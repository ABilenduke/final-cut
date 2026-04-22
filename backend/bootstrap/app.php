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
            // domain(s) so it cannot answer on the admin subdomain. Filament
            // declares its own ->domain() on the panel provider, producing
            // the reciprocal constraint. Asserted by RouteDomainScopingTest.
            //
            // When APP_PRIMARY_DOMAIN is a single host the route's domain
            // attribute is set to that literal, so `$route->domain()`
            // returns the host (what RouteDomainScopingTest asserts).
            // When multiple hosts are configured (e.g. e2e, where requests
            // come in with Host=nginx), use a parametric {customerHost}
            // placeholder with a regex `where` — Laravel matches the Host
            // against the regex at runtime while keeping a stable domain
            // string for introspection.
            $primary = config('app.primary_domains');
            $group = function () {
                Route::middleware('api')
                    ->prefix('api')
                    ->group(base_path('routes/api.php'));

                Route::middleware('web')
                    ->group(base_path('routes/web.php'));
            };

            if (count($primary) === 1) {
                Route::domain($primary[0])->group($group);

                return;
            }

            $pattern = implode('|', array_map(
                fn (string $host): string => preg_quote($host, '/'),
                $primary,
            ));

            Route::domain('{customerHost}')
                ->where('customerHost', $pattern)
                ->group($group);
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
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
