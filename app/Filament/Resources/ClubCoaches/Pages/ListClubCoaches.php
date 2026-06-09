<?php

namespace App\Filament\Resources\ClubCoaches\Pages;

use App\Filament\Resources\ClubCoaches\ClubCoachResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClubCoaches extends ListRecords
{
    protected static string $resource = ClubCoachResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New Coach'),
        ];
    }
}