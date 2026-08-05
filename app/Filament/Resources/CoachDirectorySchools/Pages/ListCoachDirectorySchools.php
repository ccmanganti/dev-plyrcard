<?php

namespace App\Filament\Resources\CoachDirectorySchools\Pages;

use App\Filament\Resources\CoachDirectorySchools\CoachDirectorySchoolResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCoachDirectorySchools extends ListRecords
{
    protected static string $resource = CoachDirectorySchoolResource::class;

    protected string $view = 'filament.resources.coach-directory-schools.pages.list-coach-directory-schools';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New School'),
        ];
    }
}