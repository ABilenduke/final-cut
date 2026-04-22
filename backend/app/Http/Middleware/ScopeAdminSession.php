<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rewrites the session config at request time so the admin panel uses
 * its own cookie name, cookie domain, and Redis connection — keeping
 * admin sessions fully disjoint from customer (Sanctum) sessions.
 *
 * Why a middleware and not per-panel session config:
 * Filament (both 3.x and 5.x) does not expose per-panel session driver
 * settings on the Panel builder. Laravel's session manager resolves
 * driver/cookie/domain from `config('session')` at StartSession time,
 * so the only hook point is to mutate that config *before* StartSession
 * runs. This middleware must therefore be registered as the first entry
 * in the admin panel's middleware stack, ahead of StartSession.
 */
class ScopeAdminSession
{
    public function handle(Request $request, Closure $next): Response
    {
        Config::set('session.cookie', config('filament.admin_session.cookie'));
        Config::set('session.domain', config('filament.admin_session.domain'));
        Config::set('session.connection', config('filament.admin_session.connection'));

        return $next($request);
    }
}
