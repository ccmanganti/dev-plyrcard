<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\InteractsWithCoachDatabase;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class CoachDatabaseCoaches extends Page
{
    use InteractsWithCoachDatabase;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Coaches';
    protected static string | UnitEnum | null $navigationGroup = 'Recruiting Center';
    protected static ?string $title = 'Coaches';
    protected static ?string $slug = 'coach-database/coaches';
    protected static ?int $navigationSort = 12;
    protected string $view = 'filament.pages.coach-database';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected function coachDatabaseSection(): string
    {
        return 'coaches';
    }
}