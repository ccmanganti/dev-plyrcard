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
use Illuminate\Support\Facades\Route;
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


                        /* Headerless shell: hide only the global topbar.
                         * Keep Filament page/resource headers visible because they
                         * contain Create, Edit, Import, Export, and other actions.
                         */
                        .fi-topbar {
                            display: none !important;
                            height: 0 !important;
                            min-height: 0 !important;
                            margin: 0 !important;
                            padding: 0 !important;
                            border: 0 !important;
                            overflow: hidden !important;
                        }

                        /* Restore resource/page actions that were previously hidden. */
                        .fi-header,
                        header.fi-header,
                        .fi-page-header,
                        .fi-ta-header {
                            display: flex !important;
                            height: auto !important;
                            min-height: 0 !important;
                            overflow: visible !important;
                        }

                        .fi-header,
                        header.fi-header,
                        .fi-page-header {
                            align-items: flex-start !important;
                            justify-content: space-between !important;
                            gap: 1rem !important;
                            margin: 0 0 1rem 0 !important;
                            padding: 0 !important;
                            border: 0 !important;
                        }

                        .fi-header-actions,
                        .fi-page-header-actions,
                        .fi-ac,
                        .fi-ac-action,
                        .fi-btn {
                            visibility: visible !important;
                            opacity: 1 !important;
                            pointer-events: auto !important;
                        }

                        /* Keep resource actions, but hide Filament's duplicate page title/description. */
                        .fi-header-heading,
                        .fi-header-subheading,
                        .fi-page-header-heading,
                        .fi-page-header-subheading,
                        .fi-header > div:first-child:not(.fi-header-actions):not(.fi-page-header-actions) {
                            display: none !important;
                        }

                        .fi-header:has(.fi-header-actions),
                        .fi-page-header:has(.fi-page-header-actions) {
                            justify-content: flex-end !important;
                            min-height: 0 !important;
                            margin-bottom: .75rem !important;
                        }

                        .fi-header-actions,
                        .fi-page-header-actions {
                            display: flex !important;
                            align-items: center !important;
                            justify-content: flex-end !important;
                            gap: .5rem !important;
                            margin-left: auto !important;
                            flex-wrap: wrap !important;
                        }

                        html,
                        body,
                        .fi-body,
                        .fi-layout,
                        .fi-main-ctn,
                        .fi-main,
                        .fi-page,
                        .fi-page-content {
                            margin-top: 0 !important;
                            padding-top: 0 !important;
                        }

                        .fi-main-ctn {
                            min-height: 100vh !important;
                        }

                        .fi-main {
                            padding-top: 1.5rem !important;
                        }

                        .fi-sidebar {
                            top: 0 !important;
                        }

                        .fi-sidebar-nav,
                        .fi-sidebar-nav-groups {
                            padding-top: 0 !important;
                            margin-top: 0 !important;
                        }

                        .fi-sidebar {
                            min-height: 100vh !important;
                            border-right: 1px solid rgba(226, 232, 240, .8) !important;
                            background: #ffffff !important;
                        }

                        .dark .fi-sidebar {
                            border-right-color: rgba(148, 163, 184, .14) !important;
                            background: #020617 !important;
                        }

                        .fi-sidebar-header {
                            min-height: 5rem !important;
                            border-bottom: 1px solid rgba(226, 232, 240, .72) !important;
                            margin-bottom: .35rem !important;
                        }

                        .dark .fi-sidebar-header {
                            border-bottom-color: rgba(148, 163, 184, .12) !important;
                        }

                        /* The original Filament sidebar header is hidden because we render the brand inside the nav itself. */
                        .fi-sidebar-header {
                            display: none !important;
                        }

                        .plyr-sidebar-brand-wrap {
                            padding: 1.15rem .55rem 1.05rem .55rem;
                            margin: 0 0 .55rem 0;
                            border-bottom: 1px solid rgba(226, 232, 240, .72);
                            overflow: hidden;
                        }

                        .dark .plyr-sidebar-brand-wrap {
                            border-bottom-color: rgba(148, 163, 184, .12);
                        }

                        .plyr-sidebar-brand {
                            display: flex;
                            align-items: center;
                            width: 100%;
                            max-width: 10.75rem;
                            text-decoration: none;
                            overflow: hidden;
                        }

                        .plyr-sidebar-brand img {
                            display: block !important;
                            height: 2.85rem;
                            width: auto;
                            max-width: 10.5rem;
                            object-fit: contain;
                            object-position: left center;
                            flex: 0 0 auto;
                        }

                        .plyr-sidebar-user-actions {
                            width: calc(100% - .55rem);
                            border-radius: 1rem;
                            border: 1px solid rgba(226, 232, 240, .95);
                            background: rgba(255, 255, 255, .9);
                            box-shadow: 0 14px 34px rgba(15, 23, 42, .06);
                            overflow: hidden;
                        }

                        .dark .plyr-sidebar-user-actions {
                            border-color: rgba(148, 163, 184, .16);
                            background: rgba(15, 23, 42, .74);
                            box-shadow: none;
                        }

                        .plyr-sidebar-user-action,
                        .plyr-sidebar-user-action-button {
                            width: 100%;
                            min-height: 2.55rem;
                            display: flex;
                            align-items: center;
                            gap: .72rem;
                            padding: .62rem .78rem;
                            border: 0;
                            background: transparent;
                            color: #1f2937;
                            text-decoration: none;
                            font-size: .86rem;
                            font-weight: 500;
                            text-align: left;
                            cursor: pointer;
                            transition: background .18s ease, color .18s ease;
                        }

                        .plyr-sidebar-user-action:hover,
                        .plyr-sidebar-user-action-button:hover {
                            background: rgba(243, 244, 246, .95);
                            color: var(--plyr-orange);
                        }

                        .dark .plyr-sidebar-user-action,
                        .dark .plyr-sidebar-user-action-button {
                            color: #e5e7eb;
                        }

                        .dark .plyr-sidebar-user-action:hover,
                        .dark .plyr-sidebar-user-action-button:hover {
                            background: rgba(30, 41, 59, .72);
                            color: #ff8a70;
                        }

                        .plyr-sidebar-user-action svg,
                        .plyr-sidebar-user-action-button svg {
                            width: 1.05rem;
                            height: 1.05rem;
                            color: #8b95a7;
                            flex: 0 0 auto;
                        }

                        .plyr-sidebar-user-action:hover svg,
                        .plyr-sidebar-user-action-button:hover svg {
                            color: currentColor;
                        }

                        .plyr-sidebar-user-actions-separator {
                            height: 1px;
                            background: rgba(226, 232, 240, .9);
                            margin: .25rem .78rem;
                        }

                        .dark .plyr-sidebar-user-actions-separator {
                            background: rgba(148, 163, 184, .14);
                        }

                        .plyr-sidebar-user-action-danger,
                        .plyr-sidebar-user-action-danger svg {
                            color: var(--plyr-orange) !important;
                        }

                        .plyr-sidebar-user-action-danger:hover,
                        .plyr-sidebar-user-action-danger:hover svg {
                            color: #e14f2d !important;
                        }



                        /* Final sidebar logo/account polish. */
                        [x-cloak] {
                            display: none !important;
                        }

                        .fi-sidebar-header {
                            display: none !important;
                            height: 0 !important;
                            min-height: 0 !important;
                            max-height: 0 !important;
                            padding: 0 !important;
                            margin: 0 !important;
                            border: 0 !important;
                            overflow: hidden !important;
                        }

                        .fi-sidebar-nav {
                            padding-top: 0 !important;
                        }

                        .plyr-sidebar-brand-wrap {
                            padding: 1.25rem 1.05rem 1.15rem 1.05rem !important;
                            margin: 0 0 .75rem 0 !important;
                            border-bottom: 1px solid rgba(226, 232, 240, .72) !important;
                            background: transparent !important;
                            border-radius: 0 !important;
                            box-shadow: none !important;
                            overflow: visible !important;
                        }

                        .dark .plyr-sidebar-brand-wrap {
                            border-bottom-color: rgba(148, 163, 184, .14) !important;
                            background: transparent !important;
                        }

                        .plyr-sidebar-brand {
                            display: inline-flex !important;
                            align-items: center !important;
                            width: auto !important;
                            max-width: 11.5rem !important;
                            height: auto !important;
                            padding: 0 !important;
                            margin: 0 !important;
                            background: transparent !important;
                            border: 0 !important;
                            border-radius: 0 !important;
                            box-shadow: none !important;
                            overflow: visible !important;
                        }

                        .plyr-sidebar-brand:hover {
                            background: transparent !important;
                            box-shadow: none !important;
                            transform: none !important;
                        }

                        .plyr-sidebar-brand img {
                            display: block !important;
                            height: auto !important;
                            width: min(13.1rem, 100%) !important;
                            max-width: 13.1rem !important;
                            object-fit: contain !important;
                            object-position: left center !important;
                            padding: 0 !important;
                            margin: 0 !important;
                            background: transparent !important;
                            border: 0 !important;
                            border-radius: 0 !important;
                            box-shadow: none !important;
                        }

                        .plyr-sidebar-brand-logo-dark {
                            display: none !important;
                        }

                        .dark .plyr-sidebar-brand-logo-light {
                            display: none !important;
                        }

                        .dark .plyr-sidebar-brand-logo-dark {
                            display: block !important;
                        }

                        .plyr-sidebar-account {
                            position: relative;
                            width: calc(100% - .55rem);
                        }

                        .plyr-sidebar-account .plyr-sidebar-profile {
                            width: 100%;
                            cursor: pointer;
                        }

                        .plyr-sidebar-account-trigger {
                            border: 1px solid rgba(255, 99, 56, .55) !important;
                        }

                        .plyr-sidebar-user-actions {
                            position: absolute;
                            left: 0;
                            right: 0;
                            bottom: calc(100% + .55rem);
                            z-index: 30;
                            width: 100% !important;
                            opacity: 0;
                            visibility: hidden;
                            transform: translateY(.35rem) scale(.985);
                            transform-origin: bottom center;
                            pointer-events: none;
                            transition:
                                opacity .16s ease,
                                visibility .16s ease,
                                transform .16s ease;
                        }

                        .plyr-sidebar-account:hover .plyr-sidebar-user-actions,
                        .plyr-sidebar-account:focus-within .plyr-sidebar-user-actions,
                        .plyr-sidebar-account[data-open="true"] .plyr-sidebar-user-actions {
                            opacity: 1;
                            visibility: visible;
                            transform: translateY(0) scale(1);
                            pointer-events: auto;
                        }

                        .plyr-sidebar-footer > .plyr-sidebar-profile {
                            display: none !important;
                        }



                        /* Clean single sidebar brand logo: no active background, no duplicate logo, no card. */
                        .plyr-sidebar-brand-wrap,
                        .fi-sidebar .plyr-sidebar-brand-wrap {
                            padding: 1.15rem 1.15rem 1rem 1.15rem !important;
                            margin: 0 0 .7rem 0 !important;
                            border-bottom: 1px solid rgba(226, 232, 240, .72) !important;
                            background: transparent !important;
                            border-radius: 0 !important;
                            box-shadow: none !important;
                            overflow: visible !important;
                        }

                        .dark .plyr-sidebar-brand-wrap,
                        .dark .fi-sidebar .plyr-sidebar-brand-wrap {
                            border-bottom-color: rgba(148, 163, 184, .14) !important;
                            background: transparent !important;
                        }

                        .fi-sidebar a.plyr-sidebar-brand,
                        .fi-sidebar a.plyr-sidebar-brand:hover,
                        .fi-sidebar a.plyr-sidebar-brand:focus,
                        .fi-sidebar a.plyr-sidebar-brand[data-plyr-active="true"],
                        .fi-sidebar a.plyr-sidebar-brand.plyr-sidebar-item-active {
                            display: inline-flex !important;
                            width: auto !important;
                            min-height: 0 !important;
                            height: auto !important;
                            max-width: 11.5rem !important;
                            padding: 0 !important;
                            margin: 0 !important;
                            gap: 0 !important;
                            border: 0 !important;
                            border-radius: 0 !important;
                            background: transparent !important;
                            color: inherit !important;
                            box-shadow: none !important;
                            transform: none !important;
                            overflow: visible !important;
                        }

                        .fi-sidebar a.plyr-sidebar-brand img.plyr-sidebar-brand-logo {
                            display: block !important;
                            height: auto !important;
                            width: min(13.1rem, 100%) !important;
                            max-width: 13.1rem !important;
                            object-fit: contain !important;
                            object-position: left center !important;
                            padding: 0 !important;
                            margin: 0 !important;
                            border: 0 !important;
                            border-radius: 0 !important;
                            background: transparent !important;
                            box-shadow: none !important;
                        }



                        /* Final fix: larger plain logo, stable account flyout, dashboard dark toggle. */

                        .fi-topbar,
                        .fi-sidebar-header .fi-logo,
                        .fi-sidebar-header .fi-brand,
                        .fi-sidebar-header a:not(.plyr-sidebar-brand),
                        .fi-sidebar-header img:not(.plyr-sidebar-brand-logo) {
                            display: none !important;
                            height: 0 !important;
                            min-height: 0 !important;
                            max-height: 0 !important;
                            padding: 0 !important;
                            margin: 0 !important;
                            border: 0 !important;
                            overflow: hidden !important;
                        }

                        .fi-sidebar-header,
                        .fi-sidebar-header *:not(.plyr-sidebar-brand):not(.plyr-sidebar-brand *) {
                            display: none !important;
                            height: 0 !important;
                            min-height: 0 !important;
                            max-height: 0 !important;
                            padding: 0 !important;
                            margin: 0 !important;
                            border: 0 !important;
                            overflow: hidden !important;
                        }

                        .fi-sidebar .plyr-sidebar-brand-wrap {
                            padding: 1.65rem 1.15rem 1.35rem 1.15rem !important;
                            margin: 0 0 1.15rem 0 !important;
                            background: transparent !important;
                            border-bottom: 1px solid rgba(226, 232, 240, .72) !important;
                            border-radius: 0 !important;
                            box-shadow: none !important;
                            overflow: visible !important;
                        }

                        .dark .fi-sidebar .plyr-sidebar-brand-wrap {
                            background: transparent !important;
                            border-bottom-color: rgba(148, 163, 184, .14) !important;
                        }

                        .fi-sidebar a.plyr-sidebar-brand,
                        .fi-sidebar a.plyr-sidebar-brand:hover,
                        .fi-sidebar a.plyr-sidebar-brand:focus,
                        .fi-sidebar a.plyr-sidebar-brand[data-plyr-active="true"] {
                            display: inline-flex !important;
                            align-items: center !important;
                            width: auto !important;
                            min-height: 0 !important;
                            height: auto !important;
                            max-width: none !important;
                            padding: 0 !important;
                            margin: 0 !important;
                            gap: 0 !important;
                            border: 0 !important;
                            border-radius: 0 !important;
                            background: transparent !important;
                            box-shadow: none !important;
                            transform: none !important;
                            overflow: visible !important;
                        }

                        .fi-sidebar a.plyr-sidebar-brand img.plyr-sidebar-brand-logo {
                            display: block !important;
                            height: 5.35rem !important;
                            width: auto !important;
                            max-width: 14.9rem !important;
                            object-fit: contain !important;
                            object-position: left center !important;
                            padding: 0 !important;
                            margin: 0 !important;
                            border: 0 !important;
                            border-radius: 0 !important;
                            background: transparent !important;
                            box-shadow: none !important;
                        }

                        .plyr-sidebar-account {
                            position: relative !important;
                            isolation: isolate;
                        }

                        .plyr-sidebar-account::before {
                            content: "";
                            position: absolute;
                            left: 0;
                            right: 0;
                            bottom: 100%;
                            height: .8rem;
                            z-index: 29;
                        }

                        .plyr-sidebar-account .plyr-sidebar-user-actions {
                            bottom: calc(100% + .18rem) !important;
                            z-index: 50 !important;
                            pointer-events: none;
                        }

                        .plyr-sidebar-account:hover .plyr-sidebar-user-actions,
                        .plyr-sidebar-account:focus-within .plyr-sidebar-user-actions,
                        .plyr-sidebar-account[data-open="true"] .plyr-sidebar-user-actions {
                            pointer-events: auto !important;
                        }

                        .rc-home-dark-toggle-v2 {
                            width: 2.65rem;
                            height: 2.65rem;
                            display: inline-grid;
                            place-items: center;
                            border: 1px solid #e5e7eb;
                            border-radius: .85rem;
                            background: rgba(255,255,255,.94);
                            color: #0f172a;
                            box-shadow: 0 8px 24px rgba(15,23,42,.08);
                            cursor: pointer;
                            transition: transform .18s ease, border-color .18s ease, background .18s ease;
                        }

                        .rc-home-dark-toggle-v2:hover {
                            transform: translateY(-1px);
                            border-color: rgba(255, 99, 56, .35);
                        }

                        .rc-home-dark-toggle-v2 svg {
                            width: 1.12rem;
                            height: 1.12rem;
                        }

                        .rc-home-dark-toggle-v2 .rc-dark-icon-sun { display: none; }
                        .dark .rc-home-dark-toggle-v2 {
                            border-color: rgba(148,163,184,.18);
                            background: rgba(17,24,39,.82);
                            color: #f8fafc;
                            box-shadow: none;
                        }
                        .dark .rc-home-dark-toggle-v2 .rc-dark-icon-moon { display: none; }
                        .dark .rc-home-dark-toggle-v2 .rc-dark-icon-sun { display: block; }

                        /* Final override: bigger sidebar logo + true square custom dark toggle. */
                        .fi-sidebar .plyr-sidebar-brand-wrap,
                        .fi-sidebar .plyr-sidebar-brand-wrap:hover,
                        .dark .fi-sidebar .plyr-sidebar-brand-wrap {
                            padding: 1.35rem 1rem 1.1rem 1rem !important;
                            margin: 0 0 1.1rem 0 !important;
                            background: transparent !important;
                            border-radius: 0 !important;
                            box-shadow: none !important;
                            overflow: visible !important;
                        }

                        .fi-sidebar a.plyr-sidebar-brand,
                        .fi-sidebar a.plyr-sidebar-brand:hover,
                        .fi-sidebar a.plyr-sidebar-brand:focus,
                        .fi-sidebar a.plyr-sidebar-brand:active,
                        .fi-sidebar a.plyr-sidebar-brand[data-plyr-active="true"] {
                            background: transparent !important;
                            border: 0 !important;
                            box-shadow: none !important;
                            padding: 0 !important;
                            margin: 0 !important;
                            border-radius: 0 !important;
                            width: 100% !important;
                            max-width: 100% !important;
                            transform: none !important;
                        }

                        .fi-sidebar a.plyr-sidebar-brand img.plyr-sidebar-brand-logo {
                            display: block !important;
                            width: min(13.1rem, 100%) !important;
                            max-width: 13.1rem !important;
                            height: auto !important;
                            max-height: 4rem !important;
                            min-width: 0 !important;
                            object-fit: contain !important;
                            object-position: left center !important;
                            background: transparent !important;
                            border-radius: 0 !important;
                            box-shadow: none !important;
                        }

                        .rc-home-dark-toggle-v2,
                        button.rc-home-dark-toggle-v2,
                        [data-plyr-dark-toggle].rc-home-dark-toggle-v2 {
                            inline-size: 2.75rem !important;
                            block-size: 2.75rem !important;
                            width: 2.75rem !important;
                            min-width: 2.75rem !important;
                            max-width: 2.75rem !important;
                            height: 2.75rem !important;
                            min-height: 2.75rem !important;
                            max-height: 2.75rem !important;
                            aspect-ratio: 1 / 1 !important;
                            padding: 0 !important;
                            border-radius: .9rem !important;
                            display: inline-grid !important;
                            place-items: center !important;
                            flex: 0 0 2.75rem !important;
                            overflow: hidden !important;
                        }


                        /* Final sticky sidebar logo fix: visible while sidebar nav scrolls, never clipped. */
                        .fi-sidebar .plyr-sidebar-brand-wrap {
                            position: sticky !important;
                            top: 0 !important;
                            z-index: 80 !important;
                            display: flex !important;
                            align-items: center !important;
                            padding: 1.05rem 1rem 1rem 1rem !important;
                            margin: 0 0 1rem 0 !important;
                            min-height: 5.75rem !important;
                            width: 100% !important;
                            background: #ffffff !important;
                            border-bottom: 1px solid rgba(226, 232, 240, .72) !important;
                            overflow: visible !important;
                            transform: translateZ(0) !important;
                        }

                        .dark .fi-sidebar .plyr-sidebar-brand-wrap {
                            background: #020617 !important;
                            border-bottom-color: rgba(148, 163, 184, .14) !important;
                        }

                        .fi-sidebar a.plyr-sidebar-brand {
                            display: flex !important;
                            align-items: center !important;
                            width: 100% !important;
                            max-width: 100% !important;
                            min-height: 0 !important;
                            padding: 0 !important;
                            margin: 0 !important;
                            background: transparent !important;
                            border: 0 !important;
                            box-shadow: none !important;
                            overflow: visible !important;
                        }

                        .fi-sidebar a.plyr-sidebar-brand img.plyr-sidebar-brand-logo {
                            display: block !important;
                            width: 11.65rem !important;
                            max-width: 100% !important;
                            height: auto !important;
                            max-height: 3.6rem !important;
                            object-fit: contain !important;
                            object-position: left center !important;
                            background: transparent !important;
                            border: 0 !important;
                            border-radius: 0 !important;
                            box-shadow: none !important;
                        }

                        .fi-sidebar-nav {
                            scroll-padding-top: 6rem !important;
                        }
                    </style>
                HTML,
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn (): string => Blade::render(<<<'BLADE'
                    <div class="plyr-sidebar-brand-wrap">
                        <a href="{{ url('/admin/coach-database') }}" class="plyr-sidebar-brand" aria-label="PLYRCARD Dashboard">
                            <img
                                src="{{ asset('logoDark.png') }}"
                                data-light-src="{{ asset('logoDark.png') }}"
                                data-dark-src="{{ asset('logo.png') }}"
                                alt="PLYRCARD"
                                class="plyr-sidebar-brand-logo"
                            >
                        </a>
                    </div>
                BLADE),
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

                    $profileUrl = Route::has('filament.admin.auth.profile')
                        ? route('filament.admin.auth.profile')
                        : ProfileResource::getUrl('index');

                    $editProfileUrl = $profileUrl;

                    $changePasswordUrl = Route::has('filament.admin.pages.force-password-change')
                        ? route('filament.admin.pages.force-password-change')
                        : url('/admin/force-password-change');

                    $logoutUrl = Route::has('filament.admin.auth.logout')
                        ? route('filament.admin.auth.logout')
                        : url('/admin/logout');

                    $managePlanUrl = url('/admin');

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
                            <div class="plyr-sidebar-account" x-data="{ open: false, closeTimer: null, show() { clearTimeout(this.closeTimer); this.open = true }, hide() { clearTimeout(this.closeTimer); this.closeTimer = setTimeout(() => this.open = false, 220) } }" x-bind:data-open="open ? 'true' : 'false'" x-on:mouseenter="show()" x-on:mouseleave="hide()">
                                <div class="plyr-sidebar-user-actions" x-cloak x-show="open" x-transition.opacity.scale.origin.bottom aria-label="Account options">
                                    <a href="{{ $profileUrl }}" class="plyr-sidebar-user-action">
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M20 21a8 8 0 0 0-16 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                            <path d="M12 13a5 5 0 1 0 0-10a5 5 0 0 0 0 10Z" stroke="currentColor" stroke-width="1.8"/>
                                        </svg>
                                        <span>View Profile</span>
                                    </a>

                                    <a href="{{ $editProfileUrl }}" class="plyr-sidebar-user-action">
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M4 20h4l10.5-10.5a2.83 2.83 0 0 0-4-4L4 16v4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                            <path d="M13.5 6.5l4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        </svg>
                                        <span>Edit Profile</span>
                                    </a>

                                    <a href="{{ $changePasswordUrl }}" class="plyr-sidebar-user-action">
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M12 15.5v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                            <path d="M7 10V8a5 5 0 0 1 10 0v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                            <path d="M6.5 10h11A1.5 1.5 0 0 1 19 11.5v7A1.5 1.5 0 0 1 17.5 20h-11A1.5 1.5 0 0 1 5 18.5v-7A1.5 1.5 0 0 1 6.5 10Z" stroke="currentColor" stroke-width="1.8"/>
                                        </svg>
                                        <span>Change Password</span>
                                    </a>

                                    <div class="plyr-sidebar-user-actions-separator"></div>

                                    <form method="POST" action="{{ $logoutUrl }}">
                                        @csrf
                                        <button type="submit" class="plyr-sidebar-user-action-button plyr-sidebar-user-action-danger">
                                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M15 17l5-5l-5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                                <path d="M20 12H9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                <path d="M12 20H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                            </svg>
                                            <span>Sign Out</span>
                                        </button>
                                    </form>
                                </div>

                                <button type="button" class="plyr-sidebar-profile plyr-sidebar-account-trigger" x-on:click="open = ! open" x-on:keydown.escape.window="open = false" aria-label="Open account menu">
                                    <span class="plyr-sidebar-avatar">
                                        <img src="{{ $avatarUrl }}" alt="{{ $name }}">
                                    </span>

                                    <span class="plyr-sidebar-profile-main">
                                        <span class="plyr-sidebar-profile-name">{{ $name }}</span>
                                        <span class="plyr-sidebar-profile-link">View Profile</span>
                                    </span>

                                    <svg class="plyr-sidebar-chevron" viewBox="0 0 20 20" fill="none" aria-hidden="true" x-bind:style="open ? 'transform: rotate(180deg)' : ''">
                                        <path d="M6.5 8L10 11.5L13.5 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </div>

                            <a href="{{ $managePlanUrl }}" class="plyr-sidebar-plan" aria-label="Manage current plan">
                                <span class="plyr-sidebar-plan-icon">
                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M13 2L5 13H11L10 22L19 10H13L13 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                    </svg>
                                </span>

                                <span class="plyr-sidebar-plan-main">
                                    <span class="plyr-sidebar-plan-name">{{ $planLabel }}</span>
                                    <span class="plyr-sidebar-plan-link">Manage Plan</span>
                                </span>
                            </a>
                        </div>
                    BLADE, [
                        'user' => $user,
                        'name' => $name,
                        'profileUrl' => $profileUrl,
                        'editProfileUrl' => $editProfileUrl,
                        'changePasswordUrl' => $changePasswordUrl,
                        'logoutUrl' => $logoutUrl,
                        'managePlanUrl' => $managePlanUrl,
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

                                try {
                                    const savedTheme = localStorage.getItem('theme') || localStorage.getItem('filament-theme') || localStorage.getItem('color-theme') || localStorage.getItem('plyr-theme');

                                    if (savedTheme === 'dark' || savedTheme === 'light') {
                                        document.documentElement.classList.toggle('dark', savedTheme === 'dark');
                                        document.body.classList.toggle('dark', savedTheme === 'dark');
                                        document.documentElement.style.colorScheme = savedTheme === 'dark' ? 'dark' : 'light';
                                    }
                                } catch (error) {}

                                function isPlyrDarkModeActive() {
                                    return document.documentElement.classList.contains('dark') || document.body.classList.contains('dark');
                                }

                                function updatePlyrDarkButtons() {
                                    const isDark = isPlyrDarkModeActive();

                                    document.querySelectorAll('[data-plyr-dark-toggle]').forEach(function (button) {
                                        button.setAttribute('aria-pressed', isDark ? 'true' : 'false');
                                        button.setAttribute('title', isDark ? 'Switch to light mode' : 'Switch to dark mode');
                                    });
                                }

                                function findNativeFilamentDarkToggle() {
                                    const selectors = [
                                        'button[aria-label*=\"dark\" i]',
                                        'button[aria-label*=\"theme\" i]',
                                        'button[title*=\"dark\" i]',
                                        'button[title*=\"theme\" i]',
                                        '[data-theme-toggle]',
                                        '[data-filament-theme-toggle]'
                                    ];

                                    return selectors
                                        .flatMap(function (selector) { return Array.from(document.querySelectorAll(selector)); })
                                        .find(function (element) {
                                            return ! element.closest('[data-plyr-dark-toggle]') && ! element.closest('.rc-home-dark-toggle-v2');
                                        }) || null;
                                }

                                function fallbackToggleFilamentDarkMode() {
                                    const shouldBeDark = ! isPlyrDarkModeActive();

                                    document.documentElement.classList.toggle('dark', shouldBeDark);
                                    document.body.classList.toggle('dark', shouldBeDark);

                                    try {
                                        localStorage.setItem('theme', shouldBeDark ? 'dark' : 'light');
                                        localStorage.setItem('filament-theme', shouldBeDark ? 'dark' : 'light');
                                        localStorage.setItem('color-theme', shouldBeDark ? 'dark' : 'light');
                                    } catch (error) {}

                                    document.dispatchEvent(new CustomEvent('plyr:dark-mode-toggled', { detail: { dark: shouldBeDark } }));
                                }

                                function applyPlyrDarkMode(shouldBeDark) {
                                    document.documentElement.classList.toggle('dark', shouldBeDark);
                                    document.body.classList.toggle('dark', shouldBeDark);
                                    document.documentElement.style.colorScheme = shouldBeDark ? 'dark' : 'light';

                                    try {
                                        localStorage.setItem('theme', shouldBeDark ? 'dark' : 'light');
                                        localStorage.setItem('filament-theme', shouldBeDark ? 'dark' : 'light');
                                        localStorage.setItem('color-theme', shouldBeDark ? 'dark' : 'light');
                                        localStorage.setItem('plyr-theme', shouldBeDark ? 'dark' : 'light');
                                    } catch (error) {}

                                    document.dispatchEvent(new CustomEvent('plyr:dark-mode-toggled', { detail: { dark: shouldBeDark } }));
                                    window.dispatchEvent(new CustomEvent('plyr:dark-mode-toggled', { detail: { dark: shouldBeDark } }));
                                }

                                function togglePlyrDarkMode() {
                                    const shouldBeDark = ! isPlyrDarkModeActive();

                                    applyPlyrDarkMode(shouldBeDark);

                                    setTimeout(function () { syncPlyrSidebarLogo(); updatePlyrDarkButtons(); }, 25);
                                    setTimeout(function () { syncPlyrSidebarLogo(); updatePlyrDarkButtons(); }, 200);
                                    setTimeout(function () { syncPlyrSidebarLogo(); updatePlyrDarkButtons(); }, 700);
                                }

                                document.addEventListener('click', function (event) {
                                    const toggle = event.target.closest('[data-plyr-dark-toggle]');

                                    if (! toggle) {
                                        return;
                                    }

                                    event.preventDefault();
                                    togglePlyrDarkMode();
                                });

                                function syncPlyrSidebarLogo() {
                                    const logo = document.querySelector('.plyr-sidebar-brand-logo');

                                    if (! logo) {
                                        return;
                                    }

                                    const isDark = document.documentElement.classList.contains('dark') || document.body.classList.contains('dark');
                                    const nextSrc = isDark ? logo.getAttribute('data-dark-src') : logo.getAttribute('data-light-src');

                                    if (nextSrc && logo.getAttribute('src') !== nextSrc) {
                                        logo.setAttribute('src', nextSrc);
                                    }
                                }

                                function normalizePath(url) {
                                    try {
                                        const parsed = new URL(url, window.location.origin);
                                        return parsed.pathname.replace(/\/+$/, '');
                                    } catch (error) {
                                        return '';
                                    }
                                }

                                function updateDiscoverSchoolsNavigation() {
                                    const compassSvg = '<svg class="fi-sidebar-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M15.8 8.2l-2.1 5.5l-5.5 2.1l2.1-5.5l5.5-2.1Z"></path><circle cx="12" cy="12" r="1"></circle></svg>';

                                    Array.from(document.querySelectorAll('.fi-sidebar a[href]')).forEach(function (link) {
                                        if (link.closest('.plyr-sidebar-footer') || link.closest('.plyr-sidebar-brand-wrap')) {
                                            return;
                                        }

                                        const label = link.querySelector('.fi-sidebar-item-label') || link.querySelector('span');
                                        const labelText = (label ? label.textContent : '').trim();
                                        const href = (link.getAttribute('href') || '').toLowerCase();

                                        if (labelText === 'Schools' && (href.includes('schools') || href.includes('coach-database'))) {
                                            if (label) {
                                                label.textContent = 'Discover Schools';
                                            }

                                            const icon = link.querySelector('.fi-sidebar-item-icon, svg');

                                            if (icon && ! icon.closest('.plyr-sidebar-brand-wrap')) {
                                                icon.outerHTML = compassSvg;
                                            }
                                        }
                                    });
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
                                            return ! link.closest('.plyr-sidebar-footer') && ! link.closest('.plyr-sidebar-brand-wrap') && ! link.classList.contains('plyr-sidebar-brand');
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

                                syncPlyrSidebarLogo();
                                updatePlyrDarkButtons();
                                updateDiscoverSchoolsNavigation();
                                applyPlyrSidebarActiveState();

                                setTimeout(function () { syncPlyrSidebarLogo(); updatePlyrDarkButtons(); updateDiscoverSchoolsNavigation(); applyPlyrSidebarActiveState(); }, 50);
                                setTimeout(function () { syncPlyrSidebarLogo(); updatePlyrDarkButtons(); updateDiscoverSchoolsNavigation(); applyPlyrSidebarActiveState(); }, 250);
                                setTimeout(function () { syncPlyrSidebarLogo(); updatePlyrDarkButtons(); updateDiscoverSchoolsNavigation(); applyPlyrSidebarActiveState(); }, 800);

                                document.addEventListener('livewire:navigated', function () { syncPlyrSidebarLogo(); updatePlyrDarkButtons(); updateDiscoverSchoolsNavigation(); applyPlyrSidebarActiveState(); });
                                document.addEventListener('livewire:update', function () { syncPlyrSidebarLogo(); updatePlyrDarkButtons(); updateDiscoverSchoolsNavigation(); applyPlyrSidebarActiveState(); });

                                if (window.MutationObserver) {
                                    new MutationObserver(function () { syncPlyrSidebarLogo(); updatePlyrDarkButtons(); updateDiscoverSchoolsNavigation(); }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
                                    new MutationObserver(function () { syncPlyrSidebarLogo(); updatePlyrDarkButtons(); updateDiscoverSchoolsNavigation(); }).observe(document.body, { attributes: true, attributeFilter: ['class'] });
                                }
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