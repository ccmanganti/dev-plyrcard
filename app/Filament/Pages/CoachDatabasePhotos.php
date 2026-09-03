<?php

namespace App\Filament\Pages;

use BackedEnum;
use UnitEnum;

class CoachDatabasePhotos extends CoachDatabase
{
    protected static ?string $slug = 'coach-database/photos';

    protected static ?string $navigationLabel = 'My Photos';

    protected static ?string $title = 'My Photos';

    protected static string|UnitEnum|null $navigationGroup = 'Recruiting Center';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 70;

    protected static bool $shouldRegisterNavigation = true;

    protected function coachDatabaseSection(): string
    {
        return 'photos';
    }
}