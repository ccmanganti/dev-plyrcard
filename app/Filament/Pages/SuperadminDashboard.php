<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class SuperadminDashboard extends BaseDashboard
{
    protected static ?string $slug = '/';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 1;

    public function mount(): void
    {
        if (! static::isSuperadmin()) {
            $this->redirect(url('/admin/coach-database'), navigate: false);

            return;
        }
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