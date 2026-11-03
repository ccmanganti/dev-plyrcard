<?php

namespace App\Filament\Pages;

use BackedEnum;

class SuperadminBilling extends SuperadminAthletes
{
    protected static ?string $slug = 'superadmin-billing';

    protected static ?string $navigationLabel = 'Billing';

    protected static ?string $title = 'Billing';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
