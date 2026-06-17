<?php

namespace App\Providers\Filament;

use App\Filament\Clusters\Organizations;
use App\Filament\Clusters\Users;
use App\Filament\Clusters\Websites;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\PasswordReset\RequestPasswordReset;
use App\Filament\Pages\Auth\PasswordReset\ResetPassword;
use App\Filament\Pages\CoachDatabase;
use App\Filament\Pages\ForcePasswordChange;
use App\Filament\Widgets\GhlProfileViewersWidget;
use App\Filament\Widgets\PlayerCardOverview;
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
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
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
            ->login(Login::class)
            ->passwordReset(
                requestAction: RequestPasswordReset::class,
                resetAction: ResetPassword::class,
            )
            ->emailVerification()
            ->profile()
            ->discoverClusters(
                in: app_path('Filament/Clusters'),
                for: 'App\\Filament\\Clusters',
            )
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
                CoachDatabase::class,
                ForcePasswordChange::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                PlayerCardOverview::class,
                GhlProfileViewersWidget::class,
            ])
            ->renderHook(
                PanelsRenderHook::BODY_END,
                function (): string {
                    $user = auth()->user();

                    $html = Blade::render('@include("partials.navigation", ["activePage" => "admin"])');

                    if ($user && $user->shouldSeeOnboarding()) {
                        $html .= Blade::render(
                            '@include("filament.hooks.onboarding-script", ["user" => $user])',
                            ['user' => $user],
                        );
                    }

                    return $html;
                },
            )
            ->middleware([
                \App\Http\Middleware\RedirectParkedAdminDomains::class,
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