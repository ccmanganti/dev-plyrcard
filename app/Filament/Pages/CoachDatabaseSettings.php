<?php

namespace App\Filament\Pages;

use BackedEnum;
use UnitEnum;

class CoachDatabaseSettings extends CoachDatabase
{
    protected static ?string $slug = 'coach-database/settings';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Settings';

    protected static string|UnitEnum|null $navigationGroup = 'Recruiting Center';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 90;

    protected static bool $shouldRegisterNavigation = true;

    protected function coachDatabaseSection(): string
    {
        return 'settings';
    }
}