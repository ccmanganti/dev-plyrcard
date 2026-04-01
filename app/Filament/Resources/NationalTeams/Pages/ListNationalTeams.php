<?php

namespace App\Filament\Resources\NationalTeams\Pages;

use App\Filament\Resources\NationalTeams\NationalTeamResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNationalTeams extends ListRecords
{
    protected static string $resource = NationalTeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
