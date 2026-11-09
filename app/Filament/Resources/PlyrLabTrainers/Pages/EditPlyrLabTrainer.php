<?php

namespace App\Filament\Resources\PlyrLabTrainers\Pages;

use App\Filament\Resources\PlyrLabTrainers\PlyrLabTrainerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlyrLabTrainer extends EditRecord
{
    protected static string $resource = PlyrLabTrainerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
