<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SuperadminControlCenterWidget;
use App\Support\SuperadminWidgets\SuperadminPlyrLabControlCenterWidget;
use BackedEnum;

class SuperadminPlyrLab extends SuperadminAthletes
{
    protected static ?string $slug = 'plyrlab';

    protected static ?string $navigationLabel = 'PLYRLab Management';

    protected static ?string $title = 'PLYRLab Management';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-beaker';

    protected static string | \UnitEnum | null $navigationGroup = 'Operations & Support';

    protected static ?int $navigationSort = 14;

    protected static string $controlCenterWidgetClass = SuperadminPlyrLabControlCenterWidget::class;

    public static function getNavigationBadge(): ?string
    {
        $count = (int) (SuperadminControlCenterWidget::navigationMetrics()['plyrlab'] ?? 0);

        return $count > 0 ? number_format($count) : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'gray';
    }
}
