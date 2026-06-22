<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\InteractsWithCoachDatabase;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class CoachDatabase extends Page
{
    use InteractsWithCoachDatabase;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static string | UnitEnum | null $navigationGroup = 'Recruiting Center';

    protected static ?string $title = '';

    protected static ?string $slug = 'coach-database';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.coach-database';

    public function getTitle(): string
    {
        return '';
    }

    protected function coachDatabaseSection(): string
    {
        return 'dashboard';
    }
}