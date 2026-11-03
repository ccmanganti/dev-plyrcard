<?php

namespace App\Filament\Pages;

use BackedEnum;

class SuperadminAmplifyCredits extends SuperadminAthletes
{
    protected static ?string $slug = 'amplify-credits';

    protected static ?string $navigationLabel = 'Amplify credits';

    protected static ?string $title = 'Amplify credits';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    protected static ?int $navigationSort = 5;

    protected static string $controlCenterSection = 'amplify';

    public static function getNavigationBadge(): ?string
    {
        return number_format(
            \App\Filament\Widgets\SuperadminControlCenterWidget::navigationMetrics()['amplify'] ?? 0
        );
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'gray';
    }
}
