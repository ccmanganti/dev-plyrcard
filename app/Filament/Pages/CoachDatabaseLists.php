<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\InteractsWithCoachDatabase;
use BackedEnum;
use UnitEnum;
use Filament\Pages\Page;

class CoachDatabaseLists extends Page
{
    use InteractsWithCoachDatabase;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationLabel = 'My Lists';

    protected static string | UnitEnum | null $navigationGroup = 'Recruiting Center';

    protected static ?string $title = 'My Lists';

    protected static ?string $slug = 'coach-database/lists';

    protected static ?int $navigationSort = 14;

    protected string $view = 'filament.pages.coach-database';

    protected function coachDatabaseSection(): string
    {
        return 'lists';
    }
}