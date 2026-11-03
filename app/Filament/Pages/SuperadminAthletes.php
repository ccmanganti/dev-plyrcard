<?php

namespace App\Filament\Pages;

use BackedEnum;

class SuperadminAthletes extends SuperadminDashboard
{
    protected static ?string $slug = 'athletes';

    protected static ?string $navigationLabel = 'Athletes';

    protected static ?string $title = 'Athletes';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 2;
}
