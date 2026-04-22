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
            // When multiple hosts are configured (e.g. e2e — Playwright
            // and Nuxt SSR reach the backend via Docker DNS `nginx` rather
            // than the APP_URL host), register the customer route group
            // once per host. Each resulting route carries one of the
            // hosts as its `domain()` attribute; requests to any of them
            // find a match.
            $register = function (string $host): void {
                Route::domain($host)->group(function (): void {
                    Route::middleware('api')
                        ->prefix('api')
                        ->group(base_path('routes/api.php'));

                    Route::middleware('web')
                        ->group(base_path('routes/web.php'));
                });
            };

            foreach (config('app.primary_domains') as $host) {
                $register($host);
            }
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
