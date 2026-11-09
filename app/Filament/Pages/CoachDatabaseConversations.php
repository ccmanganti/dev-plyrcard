<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\InteractsWithCoachDatabase;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class CoachDatabaseConversations extends Page
{
    use InteractsWithCoachDatabase;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-inbox';
    protected static ?string $navigationLabel = 'Inbox';
    protected static string | UnitEnum | null $navigationGroup = 'Recruiting Center';
    protected static ?string $title = 'Inbox';
    protected static ?string $slug = 'coach-database/conversations';
    protected static ?int $navigationSort = 20;
    protected string $view = 'filament.pages.coach-database';

    protected function coachDatabaseSection(): string
    {
        return 'conversations';
    }
}