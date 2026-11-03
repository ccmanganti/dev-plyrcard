<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SuperadminControlCenterWidget;
use BackedEnum;
use Filament\Pages\Page;

class SuperadminAthletes extends Page
{
    protected static ?string $slug = 'athletes';
    protected static ?string $navigationLabel = 'Athletes';
    protected static ?string $title = 'Athletes';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?int $navigationSort = 2;

    protected static string $controlCenterSection = 'athletes';

    public static function canAccess(): bool
    {
        return SuperadminDashboard::shouldRegisterNavigation();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        return number_format(SuperadminControlCenterWidget::navigationMetrics()['athletes'] ?? 0);
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'gray';
    }

    public function getHeading(): string
    {
        return '';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SuperadminControlCenterWidget::make([
                'initialTab' => static::$controlCenterSection,
            ]),
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
