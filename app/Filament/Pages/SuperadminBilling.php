<?php

namespace App\Filament\Pages;

use BackedEnum;
use App\Filament\Widgets\SuperadminSubscriptionsControlCenterWidget;

class SuperadminBilling extends SuperadminAthletes
{
    protected static ?string $slug = 'subscriptions';

    protected static ?string $navigationLabel = 'Subscriptions';

    protected static ?string $title = 'Subscriptions';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 4;

    protected static string $controlCenterWidgetClass = SuperadminSubscriptionsControlCenterWidget::class;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        return number_format(\App\Filament\Widgets\SuperadminControlCenterWidget::navigationMetrics()['subscriptions'] ?? 0);
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'gray';
    }
}
