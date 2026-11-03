<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SuperadminControlCenterWidget;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Page;

class SuperadminAthletes extends Page
{
    protected static ?string $slug = 'athletes';
    protected static ?string $navigationLabel = 'Athletes';
    protected static ?string $title = 'Athletes';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return SuperadminDashboard::shouldRegisterNavigation();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getHeading(): string
    {
        return '';
    }

    protected function getHeaderWidgets(): array
    {
        return [SuperadminControlCenterWidget::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
