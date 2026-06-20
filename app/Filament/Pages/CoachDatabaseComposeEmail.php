<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\InteractsWithCoachDatabase;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class CoachDatabaseComposeEmail extends Page
{
    use InteractsWithCoachDatabase;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'Compose Email';
    protected static string | UnitEnum | null $navigationGroup = 'Recruiting Center';
    protected static ?string $title = 'Compose Email';
    protected static ?string $slug = 'coach-database/compose-email';
    protected static ?int $navigationSort = 17;
    protected string $view = 'filament.pages.coach-database';

    protected function coachDatabaseSection(): string
    {
        return 'compose';
    }
}