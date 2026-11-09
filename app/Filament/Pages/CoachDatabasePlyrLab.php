<?php

namespace App\Filament\Pages;

use BackedEnum;
use UnitEnum;

class CoachDatabasePlyrLab extends CoachDatabase
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationLabel = 'PLYRLab';

    protected static string | UnitEnum | null $navigationGroup = 'Recruiting Center';

    protected static ?string $title = 'PLYRLab';

    protected static ?string $slug = 'coach-database/plyrlab';

    protected static ?int $navigationSort = 11;

    protected string $view = 'filament.pages.coach-database-plyrlab';

    protected function coachDatabaseSection(): string
    {
        return 'plyrlab';
    }
}
