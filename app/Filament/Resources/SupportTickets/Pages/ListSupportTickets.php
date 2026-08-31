<?php

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Filament\Resources\SupportTickets\SupportTicketResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListSupportTickets extends ListRecords
{
    protected static string $resource = SupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('systemSettings')
                ->label('System Settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->url(SupportTicketResource::getUrl('settings')),
        ];
    }
}