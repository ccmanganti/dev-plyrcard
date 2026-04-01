<?php

namespace App\Filament\Resources\NationalTeams\Pages;

use App\Filament\Resources\NationalTeams\NationalTeamResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditNationalTeam extends EditRecord
{
    protected static string $resource = NationalTeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
