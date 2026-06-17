<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\InteractsWithCoachDatabase;
use BackedEnum;
use UnitEnum;
use Filament\Pages\Page;

class CoachDatabaseFavorites extends Page
{
    use InteractsWithCoachDatabase;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationLabel = 'Favorites';

    protected static string | UnitEnum | null $navigationGroup = 'Recruiting Center';

    protected static ?string $title = 'Favorites';

    protected static ?string $slug = 'coach-database/favorites';

    protected static ?int $navigationSort = 13;

    protected string $view = 'filament.pages.coach-database';

    protected function coachDatabaseSection(): string
    {
        return 'favorites';
    }
}