<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\InteractsWithCoachDatabase;
use BackedEnum;
use UnitEnum;
use Filament\Pages\Page;

class CoachDatabaseSchools extends Page
{
    use InteractsWithCoachDatabase;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Schools';

    protected static string | UnitEnum | null $navigationGroup = 'Recruiting Center';

    protected static ?string $title = 'Schools';

    protected static ?string $slug = 'coach-database/schools';

    protected static ?int $navigationSort = 11;

    protected string $view = 'filament.pages.coach-database';

    protected function coachDatabaseSection(): string
    {
        return 'schools';
    }
}