<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SuperadminControlCenterWidget;
use App\Support\SuperadminWidgets\SuperadminPlyrLabControlCenterWidget;
use App\Models\PlyrLabBooking;
use BackedEnum;

class SuperadminPlyrLab extends SuperadminAthletes
{
    protected static ?string $slug = 'plyrlab';

    protected static ?string $navigationLabel = 'PLYRLab Management';

    protected static ?string $title = 'PLYRLab Management';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-beaker';

    protected static string | \UnitEnum | null $navigationGroup = 'PLYRLab';

    protected static ?int $navigationSort = 14;

    protected static string $controlCenterWidgetClass = SuperadminPlyrLabControlCenterWidget::class;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $isSuperadmin = method_exists($user, 'hasRole')
            && (
                $user->hasRole('Superadmin')
                || $user->hasRole('superadmin')
                || $user->hasRole('Super Admin')
            );

        return $isSuperadmin || $user->plyrLabTrainerProfile()->exists();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        $user = auth()->user();
        $isTrainer = $user && $user->plyrLabTrainerProfile()->exists();
        $isSuperadmin = $user && method_exists($user, 'hasRole')
            && (
                $user->hasRole('Superadmin')
                || $user->hasRole('superadmin')
                || $user->hasRole('Super Admin')
            );

        return $isTrainer && ! $isSuperadmin ? 'Trainer Portal' : 'PLYRLab Management';
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        $isSuperadmin = $user && method_exists($user, 'hasRole')
            && (
                $user->hasRole('Superadmin')
                || $user->hasRole('superadmin')
                || $user->hasRole('Super Admin')
            );

        if ($user && ! $isSuperadmin && $user->plyrLabTrainerProfile()->exists()) {
            $trainer = $user->plyrLabTrainerProfile;

            $count = PlyrLabBooking::query()
                ->where('trainer_id', $trainer->getKey())
                ->whereIn('status', PlyrLabBooking::OPEN_STATUSES)
                ->count();

            return $count > 0 ? number_format($count) : null;
        }

        $count = (int) (SuperadminControlCenterWidget::navigationMetrics()['plyrlab'] ?? 0);

        return $count > 0 ? number_format($count) : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'gray';
    }
}
