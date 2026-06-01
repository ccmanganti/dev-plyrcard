<?php

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

class Organizations extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::BuildingOffice2;
    protected static ?string $navigationLabel = 'Organizations';
    protected static ?string $slug = 'organizations';
    protected static ?int $navigationSort = 2;
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function shouldRegisterNavigation(): bool
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
