<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\InteractsWithCoachDatabase;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class CoachDatabaseConversations extends Page
{
    use InteractsWithCoachDatabase;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Conversations';
    protected static string | UnitEnum | null $navigationGroup = 'Recruiting Center';
    protected static ?string $title = 'Conversations';
    protected static ?string $slug = 'coach-database/conversations';
    protected static ?int $navigationSort = 15;
    protected string $view = 'filament.pages.coach-database';

    protected function coachDatabaseSection(): string
    {
        return 'conversations';
    }
}
