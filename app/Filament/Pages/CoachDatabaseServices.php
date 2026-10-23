<?php

namespace App\Filament\Pages;

use BackedEnum;

class CoachDatabaseServices extends CoachDatabase
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-circle-stack';
    protected static ?string $navigationLabel = 'PLYR Points';
    protected static ?string $slug = 'coach-database/services';
    protected static ?int $navigationSort = 95;

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        return number_format((int) ($user->points_available ?? 0)) . ' pts';
    }

    protected function coachDatabaseSection(): string
    {
        return 'services';
    }
}