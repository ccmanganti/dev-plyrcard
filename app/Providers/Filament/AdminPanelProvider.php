<?php

namespace App\Providers\Filament;

use App\Filament\Pages\ForcePasswordChange;
use App\Support\ProfilePlanInfo;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
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
            ->path('admin')
            ->font('Antonio', provider: GoogleFontProvider::class)
            ->maxContentWidth(Width::Full)
            ->login()
            ->brandLogo(asset('logo.png'))
            ->brandLogoHeight('3rem')
            ->globalSearch(false)
            ->colors([
                'primary' => '#ff6338',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->sidebarCollapsibleOnDesktop()
            ->pages([
                Dashboard::class,
                ForcePasswordChange::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                \App\Filament\Widgets\PlayerCardOverview::class,
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): string => auth()->check()
                    ? Blade::render(
                        '@include("filament.components.topbar-plan-info", ["planInfo" => $planInfo])',
                        [
                            'planInfo' => ProfilePlanInfo::for(auth()->user()),
                        ],
                    )
                    : '',
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => auth()->check() && auth()->user()->shouldSeeOnboarding()
                    ? Blade::render('@include("filament.hooks.onboarding-script")')
                    : '',
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                'require-password-change',
            ]);
    }
}