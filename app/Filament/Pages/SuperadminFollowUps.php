<?php

namespace App\Filament\Pages;

use BackedEnum;

class SuperadminFollowUps extends SuperadminAthletes
{
    protected static ?string $slug = 'follow-ups';

    protected static ?string $navigationLabel = 'Follow-ups';

    protected static ?string $title = 'Follow-ups';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        return number_format(\App\Filament\Widgets\SuperadminControlCenterWidget::navigationMetrics()['followups'] ?? 0);
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    /*
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    */
}
