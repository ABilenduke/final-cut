<?php

namespace App\Providers;

use App\Models\AdminUser;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

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
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $frontendUrl = config('app.frontend_url');

            $email = urlencode($notifiable->getEmailForPasswordReset());

            return "{$frontendUrl}/auth/reset-password?token={$token}&email={$email}";
        });

        // Admin auth event audit. The guard filter keeps customer (web/sanctum)
        // login/logout/failed events out of the admin activity_log — admin's
        // audit surface is intentionally guard-scoped. The AdminUser instanceof
        // check narrows the event's ?Authenticatable to a concrete Eloquent
        // model for both PHPStan and Spatie's ActivityLogger::causedBy().
        Event::listen(Login::class, function (Login $event): void {
            if ($event->guard !== 'admin' || ! $event->user instanceof AdminUser) {
                return;
            }

            activity('auth')->causedBy($event->user)->log('login');

            $event->user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => request()->ip(),
            ])->save();
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->guard !== 'admin' || ! $event->user instanceof AdminUser) {
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
