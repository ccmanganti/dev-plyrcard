<?php

namespace App\Filament\Pages;

use App\Filament\Resources\SupportTickets\SupportTicketResource;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class SystemSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'System Settings';
    protected static string|UnitEnum|null $navigationGroup = 'Administration';
    protected static ?string $title = 'System Settings';
    protected static ?string $slug = 'system-settings';
    protected static ?int $navigationSort = 99;
    protected string $view = 'filament.pages.system-settings';

    public function mount(): void
    {
        abort_unless(SupportTicketResource::canAccess(), 403);
        $this->redirect(SupportTicketResource::getUrl('settings'), navigate: true);
    }

    public static function shouldRegisterNavigation(): bool { return false; }
    public static function canAccess(): bool { return SupportTicketResource::canAccess(); }
}