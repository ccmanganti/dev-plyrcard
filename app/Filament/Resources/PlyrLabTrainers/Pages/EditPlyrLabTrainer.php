<?php

namespace App\Filament\Resources\PlyrLabTrainers\Pages;

use App\Filament\Resources\PlyrLabTrainers\PlyrLabTrainerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Role;

class EditPlyrLabTrainer extends EditRecord
{
    protected static string $resource = PlyrLabTrainerResource::class;

    protected function afterSave(): void
    {
        $user = $this->record->user;

        if (! $user) {
            return;
        }

        Role::findOrCreate('PLYRLab Trainer', 'web');
        $user->assignRole('PLYRLab Trainer');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
