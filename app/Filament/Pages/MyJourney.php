<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use UnitEnum;
use BackEnum;
class MyJourney extends Page
{
    protected string $view = 'filament.pages.my-journey';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rocket-launch';
    protected static ?string $navigationLabel = 'Upgrade';
    protected static ?string $title = 'Unlock MyJourney';
    protected static ?string $slug = 'my-journey';
    protected static ?int $navigationSort = 6;

    protected static string|UnitEnum|null $navigationGroup = null;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function getNavigationBadge(): ?string
    {
        return 'NEW';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}