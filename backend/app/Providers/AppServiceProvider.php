<?php

namespace App\Providers;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
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
        // audit surface is intentionally guard-scoped.
        Event::listen(Login::class, function (Login $event) {
            if ($event->guard !== 'admin') {
                return;
            }

            activity('auth')->causedBy($event->user)->log('login');

            $event->user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => request()->ip(),
            ])->save();
        });

        Event::listen(Logout::class, function (Logout $event) {
            if ($event->guard !== 'admin' || $event->user === null) {
                return;
            }

            activity('auth')->causedBy($event->user)->log('logout');
        });

        Event::listen(Failed::class, function (Failed $event) {
            if ($event->guard !== 'admin') {
                return;
            }

            activity('auth')
                ->withProperties(['email' => $event->credentials['email'] ?? null])
                ->log('login_failed');
        });
    }
}
