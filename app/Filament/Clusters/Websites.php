<?php

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

class Websites extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::GlobeAlt;
    protected static ?string $navigationLabel = 'Websites';
    protected static ?string $slug = 'websites';
    protected static ?int $navigationSort = 3;
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
