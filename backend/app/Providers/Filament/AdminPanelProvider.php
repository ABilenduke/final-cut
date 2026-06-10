<?php

namespace App\Providers\Filament;

use App\Http\Middleware\AdminIpAllowlist;
use App\Http\Middleware\ScopeAdminSession;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
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
                // Cinematic Void brand: deep maroon (#550000) is the
                // reactor-core fill. Filament generates the 50–950 palette
                // from the seed. See docs/design-system/DESIGN_SYSTEM.md
                // § Token Mapping.
                'primary' => Color::hex('#550000'),
            ])
            ->brandName('Final Cut')
            ->font('Newsreader')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // Domain widgets (TodayKpis, OpsHealth, TodayShowtimesOccupancy)
            // arrive via discoverWidgets above; FilamentInfoWidget was dropped
            // when they landed (admin-v2 Plan 07).
            ->widgets([
                AccountWidget::class,
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
