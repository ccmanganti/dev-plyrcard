<?php

namespace App\Filament\Resources\PlyrLabTrainers\Pages;

use App\Filament\Resources\PlyrLabTrainers\PlyrLabTrainerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlyrLabTrainers extends ListRecords
{
    protected static string $resource = PlyrLabTrainerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add PLYRLab Trainer'),
        ];
    }
}
