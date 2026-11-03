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
        return static::canAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        return number_format(\App\Filament\Widgets\SuperadminControlCenterWidget::navigationMetrics()['billing'] ?? 0);
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'gray';
    }
}
