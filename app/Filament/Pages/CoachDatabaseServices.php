<?php

namespace App\Filament\Pages;

use BackedEnum;

class CoachDatabaseServices extends CoachDatabase
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationLabel = 'Services';
    protected static ?string $slug = 'coach-database/services';
    protected static ?int $navigationSort = 95;

    protected function coachDatabaseSection(): string
    {
        return 'services';
    }
}