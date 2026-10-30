<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SuperadminControlCenterWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class SuperadminDashboard extends BaseDashboard
{
    protected static ?string $slug = '/';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Overview';

    protected static ?int $navigationSort = 1;

    public function mount(): void
    {
        if (! static::isSuperadmin()) {
            $this->redirect(url('/admin/coach-database'), navigate: false);

            return;
        }
    }

    public function getHeading(): string
    {
        return 'Dashboard';
    }

    public function getSubheading(): ?string
    {
        return 'Every athlete, plan, request, payment, and follow-up in one place.';
    }

    public function getColumns(): int | array
    {
        return 1;
    }

    /**
     * Keep the Superadmin dashboard isolated from the player-facing widgets that
     * are registered globally on the Admin panel.
     */
    public function getWidgets(): array
    {
        return [
            SuperadminControlCenterWidget::class,
        ];
    }

    public function getHeaderWidgets(): array
    {
        return [];
    }

    public function getFooterWidgets(): array
    {
        return [];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::isSuperadmin();
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    protected static function isSuperadmin(): bool
    {
        $user = auth()->user();

        return $user
            && method_exists($user, 'hasRole')
            && (
                $user->hasRole('Superadmin')
                || $user->hasRole('superadmin')
                || $user->hasRole('Super Admin')
            );
    }
}