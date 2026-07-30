<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\InteractsWithCoachDatabase;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class CoachDatabaseSupport extends Page
{
    use InteractsWithCoachDatabase;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-lifebuoy';

    protected static ?string $navigationLabel = 'Support';

    protected static string | UnitEnum | null $navigationGroup = 'Recruiting Center';

    protected static ?string $title = 'Support';

    protected static ?string $slug = 'coach-database/support';

    // Keep Support immediately before the Profile navigation item.
    protected static ?int $navigationSort = 98;

    protected string $view = 'filament.pages.coach-database';

    protected function coachDatabaseSection(): string
    {
        return 'support';
    }
}
