<?php

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class CoachDatabaseManagement extends Cluster
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCircleStack;
    protected static ?string $navigationLabel = 'Coach Database';
    protected static ?string $clusterBreadcrumb = 'Coach Database';
    protected static ?int $navigationSort = 30;

    /**
     * The Coaches / Schools navigation is rendered as custom top tabs in the
     * resource views, so disable Filament's default cluster side navigation.
     */
    protected static bool $shouldRegisterSubNavigation = false;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user || ! method_exists($user, 'hasRole')) {
            return false;
        }

        return $user->hasRole('Superadmin')
            || $user->hasRole('superadmin')
            || $user->hasRole('Admin')
            || $user->hasRole('admin');
    }
}