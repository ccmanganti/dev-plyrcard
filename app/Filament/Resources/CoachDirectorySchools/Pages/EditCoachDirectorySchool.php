<?php

namespace App\Filament\Resources\CoachDirectorySchools\Pages;

use App\Filament\Resources\CoachDirectorySchools\CoachDirectorySchoolResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCoachDirectorySchool extends EditRecord
{
    protected static string $resource = CoachDirectorySchoolResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
