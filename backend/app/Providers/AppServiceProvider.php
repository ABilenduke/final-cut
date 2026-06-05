<?php

namespace App\Providers;

use App\Auth\AdminUserProvider;
use App\Models\FeaturedSlide;
use App\Models\MenuItem;
use App\Models\User;
use App\Observers\FeaturedSlideObserver;
use App\Observers\MenuItemObserver;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ── Password policy (ultrareview P1) ────────────────────────────────
        // One shared default for every password-accepting surface (register,
        // reset, profile). The breach check (->uncompromised(), a live HIBP
        // lookup) runs only in production so tests/CI never hit the network.
        Password::defaults(function () {
            $rule = Password::min(12)->mixedCase()->numbers()->symbols();

            return app()->isProduction() ? $rule->uncompromised() : $rule;
        });

        // ── Rate limiters (ultrareview P0 #1) ───────────────────────────────
        // `auth` guards the unauthenticated, brute-forceable customer endpoints
        // (login / register / forgot- / reset-password). Two limits compose:
        // 5/min per ip+email throttles credential-stuffing of a single account,
        // and 20/min per ip caps password-spraying across many accounts from one
        // source. The nginx `auth` zone is the edge backstop; this is the
        // app-layer defense that survives a proxy/topology change.
        //
        // The per-minute caps are config-overridable (config/throttle.php, which
        // reads env so it survives config:cache) so the e2e suite — which logs in
        // as the same test user many times from one CI IP — isn't throttled. The
        // defaults are the production-secure values; only docker-compose.e2e.yml
        // raises them.
        $authPerEmail = (int) config('throttle.auth_per_email', 5);
        $authPerIp = (int) config('throttle.auth_per_ip', 20);
        $publicLookupPerIp = (int) config('throttle.public_lookup_per_ip', 30);

        RateLimiter::for('auth', function (Request $request) use ($authPerEmail, $authPerIp): array {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute($authPerEmail)->by($request->ip().'|'.$email),
                Limit::perMinute($authPerIp)->by((string) $request->ip()),
            ];
        });

        // `public-lookup` throttles unauthenticated code-enumeration surfaces
        // (gift-card balance, booking lookup) that return per-record data keyed
        // by a guessable code.
        RateLimiter::for('public-lookup', function (Request $request) use ($publicLookupPerIp): Limit {
            return Limit::perMinute($publicLookupPerIp)->by((string) $request->ip());
        });

        // Observe MenuItem to invalidate the cross-location food-menu cache on
        // save/delete. Pivot-level invalidation is handled by
        // LocationMenuItemPivot (saved/deleted hooks on the pivot model itself).
        MenuItem::observe(MenuItemObserver::class);

        // Observe FeaturedSlide to invalidate the featured-slides public cache on
        // save/delete.
        FeaturedSlide::observe(FeaturedSlideObserver::class);

        // Filament admin brand overlay — Cinematic Void.
        // Plain CSS layered on top of Filament's compiled stylesheet. Run
        // `make admin-filament-assets` after editing the theme.css source.
        // When a backend Vite pipeline lands, migrate to viteTheme().
        //
        // Noto Serif is registered as a Css asset (not @import'd from
        // theme.css) so Filament emits a non-blocking `<link rel="stylesheet">`
        // instead of a render-blocking @import. Newsreader is already loaded
        // by `->font('Newsreader')` in AdminPanelProvider.
        FilamentAsset::register([
            Css::make('finalcut-admin-theme', resource_path('css/filament/admin/theme.css')),
            Css::make(
                'finalcut-admin-fonts',
                'https://fonts.googleapis.com/css2?family=Noto+Serif:wght@500;700;800&display=swap',
            ),
        ], 'finalcut');

        // Custom user provider for the `admin` guard. Returns null for users
        // without an active AdminProfile, so a customer presenting valid
        // credentials at the admin login page fails the credential check
        // before any Login event fires — the listener below cannot promote
        // an unprivileged User into an admin row.
        Auth::provider('admin_eloquent', function (Application $app, array $config): AdminUserProvider {
            return new AdminUserProvider($app['hash'], $config['model']);
        });

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $frontendUrl = config('app.frontend_url');

            $email = urlencode($notifiable->getEmailForPasswordReset());

            return "{$frontendUrl}/auth/reset-password?token={$token}&email={$email}";
        });

        // Customer (web/sanctum) login/logout/failed events are intentionally
        // not audited here — admin's audit surface is guard-scoped.
        Event::listen(Login::class, function (Login $event): void {
            if ($event->guard !== 'admin' || ! $event->user instanceof User) {
                return;
            }

            activity('auth')->causedBy($event->user)->log('login');

            // Defense in depth: only touch an existing, active profile.
            // The admin user provider already rejects users without one, but
            // never let this listener be the thing that creates entitlement.
            $event->user->adminProfile()
                ->whereNull('disabled_at')
                ->update([
                    'last_login_at' => now(),
                    'last_login_ip' => request()->ip(),
                ]);
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->guard !== 'admin' || ! $event->user instanceof User) {
                return;
            }

            activity('auth')->causedBy($event->user)->log('logout');
        });

        Event::listen(Failed::class, function (Failed $event): void {
            if ($event->guard !== 'admin') {
                return;
            }

            $email = $event->credentials['email'] ?? null;
            $ip = request()->ip();

            // Activity-log entry — consumed by the admin Activity Log page.
            activity('auth')
                ->withProperties(['email' => $email])
                ->log('login_failed');

            // Dedicated JSON channel — consumed by the Fail2ban admin-login
            // jail. The shape (message string + context.ip) is part of a
            // tightly-coupled contract with fail2ban/filter.d/admin-login.conf;
            // CI regenerates a sample log on every run and asserts the regex
            // still matches so Monolog version drift fails CI, not production.
            Log::channel('admin_auth_events')->info('Failed admin login', [
                'ip' => $ip,
                'email' => $email,
            ]);
        });
    }
}
