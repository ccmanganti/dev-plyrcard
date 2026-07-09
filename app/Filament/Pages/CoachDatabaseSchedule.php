<?php

namespace App\Filament\Pages;

use BackedEnum;
use UnitEnum;

class CoachDatabaseSchedule extends CoachDatabase
{
    protected static ?string $slug = 'coach-database/schedule';

    protected static ?string $navigationLabel = 'My Schedule';

    protected static ?string $title = 'My Schedule';

    protected static string|UnitEnum|null $navigationGroup = 'Recruiting Center';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 80;

    protected static bool $shouldRegisterNavigation = true;

    protected function coachDatabaseSection(): string
    {
        return 'schedule';
    }
}