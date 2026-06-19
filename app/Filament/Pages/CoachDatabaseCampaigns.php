<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\InteractsWithCoachDatabase;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class CoachDatabaseCampaigns extends Page
{
    use InteractsWithCoachDatabase;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Templates';
    protected static string | UnitEnum | null $navigationGroup = 'Recruiting Center';
    protected static ?string $title = 'Templates';
    protected static ?string $slug = 'coach-database/campaigns';
    protected static ?int $navigationSort = 16;
    protected string $view = 'filament.pages.coach-database';

    protected function coachDatabaseSection(): string
    {
        return 'campaigns';
    }
}