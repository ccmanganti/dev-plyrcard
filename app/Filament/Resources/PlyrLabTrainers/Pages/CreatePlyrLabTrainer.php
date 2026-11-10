<?php

namespace App\Filament\Resources\PlyrLabTrainers\Pages;

use App\Filament\Resources\PlyrLabTrainers\PlyrLabTrainerResource;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\Models\Role;

class CreatePlyrLabTrainer extends CreateRecord
{
    protected static string $resource = PlyrLabTrainerResource::class;

    protected function afterCreate(): void
    {
        $user = $this->record->user;

        if (! $user) {
            return;
        }

        Role::findOrCreate('PLYRLab Trainer', 'web');
        $user->assignRole('PLYRLab Trainer');
    }
}
