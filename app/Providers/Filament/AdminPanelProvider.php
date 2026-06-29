<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\PasswordReset\RequestPasswordReset;
use App\Filament\Pages\Auth\PasswordReset\ResetPassword;
use App\Filament\Pages\CoachDatabase;
use App\Filament\Pages\ForcePasswordChange;
use App\Filament\Resources\Profiles\ProfileResource;
use App\Filament\Widgets\GhlProfileViewersWidget;
use App\Filament\Widgets\PlayerCardOverview;
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
            ->font('BlinkMacSystemFont')
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
            ->brandLogo(asset('logoDark.png'))
            ->darkModeBrandLogo(asset('logo.png'))
            ->brandLogoHeight('3rem')
            ->globalSearch(false)
            ->colors([
                'primary' => '#ff6338',
            ])
            ->sidebarWidth('16rem')
            ->collapsedSidebarWidth('4rem')
            ->discoverResources(
                in: app_path('Filament/Resources'),
                for: 'App\\Filament\\Resources',
            )
            ->discoverPages(
                in: app_path('Filament/Pages'),
                for: 'App\\Filament\\Pages',
            )
            ->pages([
                Dashboard::class,
                CoachDatabase::class,
                ForcePasswordChange::class,
            ])
            ->discoverWidgets(
                in: app_path('Filament/Widgets'),
                for: 'App\\Filament\\Widgets',
            )
            ->widgets([
                PlayerCardOverview::class,
                GhlProfileViewersWidget::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => <<<'HTML'
                    <style>
                        :root {
                            --font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
                            --sidebar-width: 16rem;
                            --collapsed-sidebar-width: 4rem;
                            --plyr-orange: #ff6338;
                            --plyr-orange-soft: rgba(255, 99, 56, .15);
                            --plyr-orange-soft-hover: rgba(255, 99, 56, .21);
                        }

                        html,
                        body,
                        .fi-body,
                        .fi-sidebar,
                        .fi-topbar,
                        .fi-main,
                        .fi-page,
                        .fi-btn,
                        .fi-input,
                        .fi-select,
                        .fi-ta,
                        .fi-modal,
                        .fi-dropdown,
                        .fi-tabs,
                        .fi-section,
                        .fi-fo-field-wrp,
                        [class*="fi-"] {
                            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif !important;
                        }

                        .fi-sidebar {
                            width: 16rem !important;
                            max-width: 16rem !important;
                        }

                        .fi-sidebar-header {
                            padding-left: 1.1rem !important;
                            padding-right: 1.1rem !important;
                        }

                        .fi-sidebar-nav {
                            padding-left: .85rem !important;
                            padding-right: .85rem !important;
                            padding-bottom: 1rem !important;
                            scrollbar-width: thin;
                            scrollbar-color: rgba(148, 163, 184, .18) transparent;
                        }

                        .fi-sidebar-nav::-webkit-scrollbar {
                            width: 6px;
                        }

                        .fi-sidebar-nav::-webkit-scrollbar-track {
                            background: transparent;
                        }

                        .fi-sidebar-nav::-webkit-scrollbar-thumb {
                            background: transparent;
                            border-radius: 999px;
                        }

                        .fi-sidebar:hover .fi-sidebar-nav::-webkit-scrollbar-thumb {
                            background: rgba(148, 163, 184, .2);
                        }

                        .dark .fi-sidebar:hover .fi-sidebar-nav::-webkit-scrollbar-thumb {
                            background: rgba(148, 163, 184, .26);
                        }

                        .fi-sidebar-nav::-webkit-scrollbar-thumb:hover {
                            background: rgba(148, 163, 184, .36) !important;
                        }

                        .fi-sidebar-group {
                            gap: .22rem !important;
                        }

                        .fi-sidebar-group-label {
                            margin-top: .85rem !important;
                            margin-bottom: .35rem !important;
                            padding-left: .35rem !important;
                            font-size: .68rem !important;
                            font-weight: 500 !important;
                            letter-spacing: .055em !important;
                            color: rgba(100, 116, 139, .76) !important;
                            text-transform: uppercase !important;
                        }

                        .dark .fi-sidebar-group-label {
                            color: rgba(148, 163, 184, .72) !important;
                        }

                        .fi-sidebar-item-button,
                        .fi-sidebar a[href] {
                            position: relative !important;
                            min-height: 2.95rem !important;
                            display: flex !important;
                            align-items: center !important;
                            gap: .75rem !important;
                            padding: .34rem .75rem .34rem .42rem !important;
                            border-radius: .9rem !important;
                            color: #111827 !important;
                            background: transparent !important;
                            font-weight: 500 !important;
                            text-decoration: none !important;
                            transition:
                                background-color .18s ease,
                                color .18s ease,
                                box-shadow .18s ease,
                                transform .18s ease !important;
                        }

                        .dark .fi-sidebar-item-button,
                        .dark .fi-sidebar a[href] {
                            color: #e5e7eb !important;
                        }

                        .fi-sidebar-item-icon,
                        .fi-sidebar a[href] svg {
                            box-sizing: content-box !important;
                            width: 1.08rem !important;
                            height: 1.08rem !important;
                            min-width: 1.08rem !important;
                            padding: .56rem !important;
                            border-radius: .72rem !important;
                            color: #8b95a7 !important;
                            background: #eef1f5 !important;
                            stroke-width: 1.8 !important;
                            transition:
                                background-color .18s ease,
                                color .18s ease,
                                box-shadow .18s ease,
                                transform .18s ease !important;
                        }

                        .dark .fi-sidebar-item-icon,
                        .dark .fi-sidebar a[href] svg {
                            color: #94a3b8 !important;
                            background: rgba(148, 163, 184, .12) !important;
                        }

                        .fi-sidebar-item-label,
                        .fi-sidebar a[href] span {
                            font-size: .9rem !important;
                            line-height: 1.15 !important;
                            font-weight: 500 !important;
                            color: #111827 !important;
                            transition: color .18s ease !important;
                        }

                        .dark .fi-sidebar-item-label,
                        .dark .fi-sidebar a[href] span {
                            color: #e5e7eb !important;
                        }

                        .fi-sidebar-item-button:hover,
                        .fi-sidebar a[href]:hover {
                            background: rgba(243, 244, 246, .95) !important;
                            color: #111827 !important;
                        }

                        .dark .fi-sidebar-item-button:hover,
                        .dark .fi-sidebar a[href]:hover {
                            background: rgba(30, 41, 59, .72) !important;
                            color: #f8fafc !important;
                        }

                        .fi-sidebar-item-button:hover .fi-sidebar-item-icon,
                        .fi-sidebar a[href]:hover svg {
                            color: var(--plyr-orange) !important;
                            background: rgba(255, 99, 56, .12) !important;
                            transform: translateY(-1px) !important;
                        }

                        .fi-sidebar-item-button:hover .fi-sidebar-item-label,
                        .fi-sidebar a[href]:hover span {
                            color: #111827 !important;
                            font-weight: 500 !important;
                        }

                        .dark .fi-sidebar-item-button:hover .fi-sidebar-item-label,
                        .dark .fi-sidebar a[href]:hover span {
                            color: #f8fafc !important;
                        }

                        /*
                         * Active state.
                         * This targets Filament's active state AND the forced state from JS.
                         */
                        .fi-sidebar-item.fi-active > .fi-sidebar-item-button,
                        .fi-sidebar-item.fi-active .fi-sidebar-item-button,
                        .fi-sidebar-item-active > .fi-sidebar-item-button,
                        .fi-sidebar-item-active .fi-sidebar-item-button,
                        .fi-sidebar-item.plyr-sidebar-item-active > .fi-sidebar-item-button,
                        .fi-sidebar-item.plyr-sidebar-item-active .fi-sidebar-item-button,
                        .fi-sidebar-item-button.fi-active,
                        .fi-sidebar-item-button[aria-current="page"],
                        .fi-sidebar-item-button[aria-current="true"],
                        .fi-sidebar-item-button[data-plyr-active="true"],
                        .fi-sidebar a[href].plyr-sidebar-item-active,
                        .fi-sidebar a[href][data-plyr-active="true"],
                        .plyr-sidebar-item-active .fi-sidebar-item-button {
                            background: var(--plyr-orange-soft) !important;
                            color: var(--plyr-orange) !important;
                            box-shadow: none !important;
                            font-weight: 500 !important;
                        }

                        .fi-sidebar-item.fi-active > .fi-sidebar-item-button:hover,
                        .fi-sidebar-item.fi-active .fi-sidebar-item-button:hover,
                        .fi-sidebar-item-active > .fi-sidebar-item-button:hover,
                        .fi-sidebar-item-active .fi-sidebar-item-button:hover,
                        .fi-sidebar-item.plyr-sidebar-item-active > .fi-sidebar-item-button:hover,
                        .fi-sidebar-item.plyr-sidebar-item-active .fi-sidebar-item-button:hover,
                        .fi-sidebar-item-button.fi-active:hover,
                        .fi-sidebar-item-button[aria-current="page"]:hover,
                        .fi-sidebar-item-button[aria-current="true"]:hover,
                        .fi-sidebar-item-button[data-plyr-active="true"]:hover,
                        .fi-sidebar a[href].plyr-sidebar-item-active:hover,
                        .fi-sidebar a[href][data-plyr-active="true"]:hover,
                        .plyr-sidebar-item-active .fi-sidebar-item-button:hover {
                            background: var(--plyr-orange-soft-hover) !important;
                        }

                        .fi-sidebar-item.fi-active > .fi-sidebar-item-button .fi-sidebar-item-icon,
                        .fi-sidebar-item.fi-active .fi-sidebar-item-button .fi-sidebar-item-icon,
                        .fi-sidebar-item-active > .fi-sidebar-item-button .fi-sidebar-item-icon,
                        .fi-sidebar-item-active .fi-sidebar-item-button .fi-sidebar-item-icon,
                        .fi-sidebar-item.plyr-sidebar-item-active > .fi-sidebar-item-button .fi-sidebar-item-icon,
                        .fi-sidebar-item.plyr-sidebar-item-active .fi-sidebar-item-button .fi-sidebar-item-icon,
                        .fi-sidebar-item-button.fi-active .fi-sidebar-item-icon,
                        .fi-sidebar-item-button[aria-current="page"] .fi-sidebar-item-icon,
                        .fi-sidebar-item-button[aria-current="true"] .fi-sidebar-item-icon,
                        .fi-sidebar-item-button[data-plyr-active="true"] .fi-sidebar-item-icon,
                        .fi-sidebar a[href].plyr-sidebar-item-active svg,
                        .fi-sidebar a[href][data-plyr-active="true"] svg,
                        .plyr-sidebar-item-active .fi-sidebar-item-button .fi-sidebar-item-icon {
                            color: #ffffff !important;
                            background: var(--plyr-orange) !important;
                            box-shadow: 0 10px 22px rgba(255, 99, 56, .25) !important;
                        }

                        .fi-sidebar-item.fi-active > .fi-sidebar-item-button .fi-sidebar-item-label,
                        .fi-sidebar-item.fi-active .fi-sidebar-item-button .fi-sidebar-item-label,
                        .fi-sidebar-item-active > .fi-sidebar-item-button .fi-sidebar-item-label,
                        .fi-sidebar-item-active .fi-sidebar-item-button .fi-sidebar-item-label,
                        .fi-sidebar-item.plyr-sidebar-item-active > .fi-sidebar-item-button .fi-sidebar-item-label,
                        .fi-sidebar-item.plyr-sidebar-item-active .fi-sidebar-item-button .fi-sidebar-item-label,
                        .fi-sidebar-item-button.fi-active .fi-sidebar-item-label,
                        .fi-sidebar-item-button[aria-current="page"] .fi-sidebar-item-label,
                        .fi-sidebar-item-button[aria-current="true"] .fi-sidebar-item-label,
                        .fi-sidebar-item-button[data-plyr-active="true"] .fi-sidebar-item-label,
                        .fi-sidebar a[href].plyr-sidebar-item-active span,
                        .fi-sidebar a[href][data-plyr-active="true"] span,
                        .plyr-sidebar-item-active .fi-sidebar-item-button .fi-sidebar-item-label {
                            color: var(--plyr-orange) !important;
                            font-weight: 500 !important;
                        }

                        .dark .fi-sidebar-item.fi-active > .fi-sidebar-item-button,
                        .dark .fi-sidebar-item.fi-active .fi-sidebar-item-button,
                        .dark .fi-sidebar-item-active > .fi-sidebar-item-button,
                        .dark .fi-sidebar-item-active .fi-sidebar-item-button,
                        .dark .fi-sidebar-item.plyr-sidebar-item-active > .fi-sidebar-item-button,
                        .dark .fi-sidebar-item.plyr-sidebar-item-active .fi-sidebar-item-button,
                        .dark .fi-sidebar-item-button.fi-active,
                        .dark .fi-sidebar-item-button[aria-current="page"],
                        .dark .fi-sidebar-item-button[aria-current="true"],
                        .dark .fi-sidebar-item-button[data-plyr-active="true"],
                        .dark .fi-sidebar a[href].plyr-sidebar-item-active,
                        .dark .fi-sidebar a[href][data-plyr-active="true"],
                        .dark .plyr-sidebar-item-active .fi-sidebar-item-button {
                            background: rgba(255, 99, 56, .16) !important;
                        }

                        .fi-sidebar-item-badge {
                            min-width: 1.25rem !important;
                            height: 1.25rem !important;
                            border-radius: 999px !important;
                            background: var(--plyr-orange) !important;
                            color: #ffffff !important;
                            font-size: .68rem !important;
                            font-weight: 600 !important;
                            display: inline-flex !important;
                            align-items: center !important;
                            justify-content: center !important;
                            padding: 0 .38rem !important;
                        }

                        .fi-sidebar-header button[title*="Collapse"],
                        .fi-sidebar-header button[title*="Expand"],
                        .fi-sidebar-header button[aria-label*="Collapse"],
                        .fi-sidebar-header button[aria-label*="Expand"],
                        .fi-sidebar button[title*="Collapse"],
                        .fi-sidebar button[title*="Expand"],
                        .fi-sidebar button[aria-label*="Collapse"],
                        .fi-sidebar button[aria-label*="Expand"],
                        button.fi-icon-btn[title*="Collapse"],
                        button.fi-icon-btn[title*="Expand"],
                        button.fi-icon-btn[aria-label*="Collapse"],
                        button.fi-icon-btn[aria-label*="Expand"] {
                            display: none !important;
                        }

                        @media (min-width: 1024px) {
                            .fi-main-ctn {
                                width: 100% !important;
                                max-width: none !important;
                            }

                            .fi-main {
                                width: 100% !important;
                                max-width: none !important;
                                margin-inline: 0 !important;
                                padding-left: 2rem !important;
                                padding-right: 2rem !important;
                            }

                            .fi-main > div,
                            .fi-main > section,
                            .fi-page,
                            .fi-page > section,
                            .fi-page-content,
                            .fi-page .fi-page-content,
                            .fi-page .fi-section,
                            .fi-page .fi-wi,
                            .fi-page .fi-wi-stats-overview {
                                width: 100% !important;
                                max-width: none !important;
                                margin-left: 0 !important;
                                margin-right: 0 !important;
                            }

                            .fi-main .mx-auto,
                            .fi-page .mx-auto,
                            .fi-main [class*="max-w-"],
                            .fi-page [class*="max-w-"] {
                                max-width: none !important;
                            }

                            .fi-page .rc-wrap,
                            .fi-page .rc-home-dashboard,
                            .fi-page .rc-dashboard,
                            .fi-page [class*="rc-home"],
                            .fi-page [class*="rc-dashboard"] {
                                width: 100% !important;
                                max-width: none !important;
                                margin-left: 0 !important;
                                margin-right: 0 !important;
                            }
                        }

                        .plyr-sidebar-footer {
                            margin-top: auto;
                            padding: .85rem 1.55rem .85rem .55rem;
                            display: grid;
                            gap: .75rem;
                        }

                        .plyr-sidebar-profile,
                        .plyr-sidebar-plan {
                            width: calc(100% - .55rem);
                            display: flex;
                            align-items: center;
                            gap: .72rem;
                            border-radius: 1rem;
                            border: 1px solid rgba(226, 232, 240, .95);
                            background: rgba(255, 255, 255, .86);
                            padding: .72rem;
                            box-shadow: 0 10px 26px rgba(15, 23, 42, .05);
                        }

                        .plyr-sidebar-profile {
                            text-decoration: none;
                            transition: border-color .18s ease, background .18s ease, transform .18s ease;
                        }

                        .plyr-sidebar-profile:hover {
                            border-color: rgba(255, 99, 56, .35);
                            background: rgba(255, 255, 255, .96);
                            transform: translateY(-1px);
                        }

                        .dark .plyr-sidebar-profile,
                        .dark .plyr-sidebar-plan {
                            border-color: rgba(148, 163, 184, .16);
                            background: rgba(15, 23, 42, .72);
                            box-shadow: none;
                        }

                        .dark .plyr-sidebar-profile:hover {
                            border-color: rgba(255, 99, 56, .32);
                            background: rgba(15, 23, 42, .9);
                        }

                        .plyr-sidebar-avatar {
                            width: 2.65rem;
                            height: 2.65rem;
                            border-radius: 999px;
                            padding: 2px;
                            background: linear-gradient(135deg, #ff6338, #ff8a70);
                            flex: 0 0 auto;
                        }

                        .plyr-sidebar-avatar img {
                            width: 100%;
                            height: 100%;
                            border-radius: inherit;
                            object-fit: cover;
                            border: 2px solid #fff;
                        }

                        .dark .plyr-sidebar-avatar img {
                            border-color: #0f172a;
                        }

                        .plyr-sidebar-profile-main,
                        .plyr-sidebar-plan-main {
                            min-width: 0;
                            flex: 1;
                        }

                        .plyr-sidebar-profile-name,
                        .plyr-sidebar-plan-name {
                            display: block;
                            font-size: .86rem;
                            line-height: 1.15;
                            font-weight: 500;
                            color: #0f172a;
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                        }

                        .dark .plyr-sidebar-profile-name,
                        .dark .plyr-sidebar-plan-name {
                            color: #f8fafc;
                        }

                        .plyr-sidebar-profile-link,
                        .plyr-sidebar-plan-link {
                            margin-top: .18rem;
                            display: inline-flex;
                            font-size: .76rem;
                            line-height: 1;
                            font-weight: 500;
                            color: var(--plyr-orange);
                        }

                        .plyr-sidebar-chevron {
                            width: 1rem;
                            height: 1rem;
                            color: #0f172a;
                            flex: 0 0 auto;
                        }

                        .dark .plyr-sidebar-chevron {
                            color: #f8fafc;
                        }

                        .plyr-sidebar-plan {
                            border-color: rgba(255, 99, 56, .22);
                            background: rgba(255, 99, 56, .07);
                            cursor: default;
                        }

                        .dark .plyr-sidebar-plan {
                            border-color: rgba(255, 99, 56, .22);
                            background: rgba(255, 99, 56, .08);
                        }

                        .plyr-sidebar-plan-icon {
                            width: 2.2rem;
                            height: 2.2rem;
                            border-radius: .75rem;
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            background: rgba(255, 99, 56, .12);
                            color: var(--plyr-orange);
                            flex: 0 0 auto;
                        }

                        .plyr-sidebar-plan-icon svg {
                            width: 1.1rem;
                            height: 1.1rem;
                        }

                        @media (max-width: 1023px) {
                            .fi-main,
                            .fi-main-ctn,
                            .fi-page,
                            .fi-page-content {
                                width: 100% !important;
                                max-width: none !important;
                            }
                        }
                    </style>
                HTML,
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_END,
                function (): string {
                    $user = auth()->user();

                    if (! $user) {
                        return '';
                    }

                    $user->loadMissing('roles');

                    $name = trim((string) ($user->name ?? '')) ?: trim(collect([
                        $user->first_name ?? null,
                        $user->last_name ?? null,
                    ])->filter()->implode(' ')) ?: 'My Profile';

                    $profileUrl = ProfileResource::getUrl('index');

                    $avatarUrl = null;

                    if (method_exists($user, 'getFilamentAvatarUrl')) {
                        $avatarUrl = $user->getFilamentAvatarUrl();
                    }

                    $avatarUrl = $avatarUrl
                        ?: ($user->profile_photo_url ?? null)
                        ?: ($user->avatar_url ?? null)
                        ?: 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=ff6338&color=fff';

                    $roleNames = collect();

                    if (method_exists($user, 'getRoleNames')) {
                        $roleNames = $user->getRoleNames();
                    } elseif ($user->relationLoaded('roles')) {
                        $roleNames = $user->roles->pluck('name');
                    }

                    $normalizedRoles = $roleNames
                        ->filter()
                        ->map(fn ($role) => trim((string) $role))
                        ->values();

                    $planLabel = match (true) {
                        $normalizedRoles->contains(fn ($role) => strcasecmp($role, 'My Journey') === 0) => 'My Journey',
                        $normalizedRoles->contains(fn ($role) => strcasecmp($role, 'Plyr') === 0) => 'Plyr',
                        $normalizedRoles->contains(fn ($role) => strcasecmp($role, 'PLYR+') === 0) => 'PLYR+',
                        $normalizedRoles->contains(fn ($role) => strcasecmp($role, 'Free') === 0) => 'Free',
                        default => $normalizedRoles->first() ?: 'Free',
                    };

                    return Blade::render(<<<'BLADE'
                        <div class="plyr-sidebar-footer">
                            <a href="{{ $profileUrl }}" class="plyr-sidebar-profile">
                                <span class="plyr-sidebar-avatar">
                                    <img src="{{ $avatarUrl }}" alt="{{ $name }}">
                                </span>

                                <span class="plyr-sidebar-profile-main">
                                    <span class="plyr-sidebar-profile-name">{{ $name }}</span>
                                    <span class="plyr-sidebar-profile-link">View Profile</span>
                                </span>

                                <svg class="plyr-sidebar-chevron" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="M6.5 8L10 11.5L13.5 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>

                            <div class="plyr-sidebar-plan" aria-label="Current plan">
                                <span class="plyr-sidebar-plan-icon">
                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M13 2L5 13H11L10 22L19 10H13L13 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                    </svg>
                                </span>

                                <span class="plyr-sidebar-plan-main">
                                    <span class="plyr-sidebar-plan-name">{{ $planLabel }}</span>
                                    <span class="plyr-sidebar-plan-link">Current Plan</span>
                                </span>
                            </div>
                        </div>
                    BLADE, [
                        'user' => $user,
                        'name' => $name,
                        'profileUrl' => $profileUrl,
                        'avatarUrl' => $avatarUrl,
                        'planLabel' => $planLabel,
                    ]);
                },
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                function (): string {
                    $user = auth()->user();

                    $html = Blade::render('@include("partials.navigation", ["activePage" => "admin"])');

                    $html .= <<<'HTML'
                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                const ORANGE = '#ff6338';
                                const ORANGE_SOFT = 'rgba(255, 99, 56, .15)';
                                const ORANGE_SOFT_HOVER = 'rgba(255, 99, 56, .21)';
                                const GREY_ICON = '#8b95a7';
                                const GREY_ICON_BG = '#eef1f5';

                                function normalizePath(url) {
                                    try {
                                        const parsed = new URL(url, window.location.origin);
                                        return parsed.pathname.replace(/\/+$/, '');
                                    } catch (error) {
                                        return '';
                                    }
                                }

                                function resetSidebarLink(link) {
                                    link.classList.remove('plyr-sidebar-item-active');
                                    link.removeAttribute('data-plyr-active');

                                    link.style.removeProperty('background');
                                    link.style.removeProperty('color');
                                    link.style.removeProperty('font-weight');
                                    link.style.removeProperty('box-shadow');

                                    const item = link.closest('.fi-sidebar-item');

                                    if (item) {
                                        item.classList.remove('plyr-sidebar-item-active');
                                    }

                                    const label = link.querySelector('.fi-sidebar-item-label');

                                    if (label) {
                                        label.style.removeProperty('color');
                                        label.style.setProperty('font-weight', '500', 'important');
                                    }

                                    const icon = link.querySelector('.fi-sidebar-item-icon, svg');

                                    if (icon) {
                                        icon.style.removeProperty('box-shadow');
                                        icon.style.setProperty('color', GREY_ICON, 'important');
                                        icon.style.setProperty('background', GREY_ICON_BG, 'important');
                                    }
                                }

                                function activateSidebarLink(link) {
                                    link.classList.add('plyr-sidebar-item-active');
                                    link.setAttribute('data-plyr-active', 'true');

                                    link.style.setProperty('background', ORANGE_SOFT, 'important');
                                    link.style.setProperty('color', ORANGE, 'important');
                                    link.style.setProperty('font-weight', '500', 'important');
                                    link.style.setProperty('box-shadow', 'none', 'important');

                                    const item = link.closest('.fi-sidebar-item');

                                    if (item) {
                                        item.classList.add('plyr-sidebar-item-active');
                                    }

                                    const label = link.querySelector('.fi-sidebar-item-label');

                                    if (label) {
                                        label.style.setProperty('color', ORANGE, 'important');
                                        label.style.setProperty('font-weight', '500', 'important');
                                    }

                                    const icon = link.querySelector('.fi-sidebar-item-icon, svg');

                                    if (icon) {
                                        icon.style.setProperty('color', '#ffffff', 'important');
                                        icon.style.setProperty('background', ORANGE, 'important');
                                        icon.style.setProperty('box-shadow', '0 10px 22px rgba(255, 99, 56, .25)', 'important');
                                    }

                                    link.addEventListener('mouseenter', function () {
                                        if (link.getAttribute('data-plyr-active') === 'true') {
                                            link.style.setProperty('background', ORANGE_SOFT_HOVER, 'important');
                                        }
                                    });

                                    link.addEventListener('mouseleave', function () {
                                        if (link.getAttribute('data-plyr-active') === 'true') {
                                            link.style.setProperty('background', ORANGE_SOFT, 'important');
                                        }
                                    });
                                }

                                function applyPlyrSidebarActiveState() {
                                    const currentPath = window.location.pathname.replace(/\/+$/, '');
                                    const sidebarLinks = Array.from(document.querySelectorAll('.fi-sidebar a[href]'))
                                        .filter(function (link) {
                                            return ! link.closest('.plyr-sidebar-footer');
                                        });

                                    sidebarLinks.forEach(resetSidebarLink);

                                    let bestMatch = null;
                                    let bestMatchLength = 0;

                                    sidebarLinks.forEach(function (link) {
                                        const linkPath = normalizePath(link.href);

                                        if (! linkPath) {
                                            return;
                                        }

                                        const isExact = currentPath === linkPath;
                                        const isChild = currentPath.startsWith(linkPath + '/');

                                        if ((isExact || isChild) && linkPath.length > bestMatchLength) {
                                            bestMatch = link;
                                            bestMatchLength = linkPath.length;
                                        }
                                    });

                                    if (bestMatch) {
                                        activateSidebarLink(bestMatch);
                                    }
                                }

                                applyPlyrSidebarActiveState();

                                setTimeout(applyPlyrSidebarActiveState, 50);
                                setTimeout(applyPlyrSidebarActiveState, 250);
                                setTimeout(applyPlyrSidebarActiveState, 800);

                                document.addEventListener('livewire:navigated', applyPlyrSidebarActiveState);
                                document.addEventListener('livewire:update', applyPlyrSidebarActiveState);
                            });
                        </script>
                    HTML;

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