<?php

namespace App\Providers\Filament;

use App\Http\Middleware\AdminIpAllowlist;
use App\Http\Middleware\ScopeAdminSession;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->domain(config('filament.admin_domain'))
            ->path('/')
            ->authGuard('admin')
            ->authPasswordBroker('admin_users')
            ->login()
            ->colors([
                'primary' => [
                    50  => '#1c1609',
                    100 => '#2a2010',
                    200 => '#4a371a',
                    300 => '#7a5a2b',
                    400 => '#a88040',
                    500 => '#DAC769',
                    600 => '#e6d489',
                    700 => '#f0e1a9',
                    800 => '#f8edc6',
                    900 => '#fdf6e0',
                    950 => '#fefae8',
                ],
                'danger'  => Color::hex('#b5443d'),
                'success' => Color::hex('#5b8f6c'),
                'warning' => Color::hex('#c78438'),
                'info'    => Color::hex('#5a8aa0'),
                'gray'    => Color::Stone,
            ])
            ->font('Newsreader', provider: GoogleFontProvider::class)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                // AdminIpAllowlist must run before ScopeAdminSession so an
                // IP-rejected request never hits session machinery, Redis,
                // or the auth password broker. See backend/config/admin.php
                // for the fail-closed contract.
                AdminIpAllowlist::class,
                ScopeAdminSession::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
