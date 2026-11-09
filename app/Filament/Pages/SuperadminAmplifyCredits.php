<?php

namespace App\Filament\Pages;

use BackedEnum;
use App\Support\SuperadminWidgets\SuperadminAmplifyCreditsControlCenterWidget;

class SuperadminAmplifyCredits extends SuperadminAthletes
{
    protected static ?string $slug = 'amplify-credits';

    protected static ?string $navigationLabel = 'Amplify credits';

    protected static ?string $title = 'Amplify credits';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    protected static ?int $navigationSort = 6;

    protected static string $controlCenterWidgetClass = SuperadminAmplifyCreditsControlCenterWidget::class;

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
