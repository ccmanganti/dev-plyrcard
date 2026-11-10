<?php

namespace App\Filament\Resources\PlyrLabTrainers\Pages;

use App\Filament\Resources\PlyrLabTrainers\PlyrLabTrainerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Role;
use Filament\Notifications\Notification;
use App\Services\PlyomatService;
use Throwable;

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

        if ($this->record->wasChanged('plyomat_api_key') && $this->record->hasPlyomatApiKey()) {
            try {
                app(PlyomatService::class)->verifyConnection($this->record);
            } catch (Throwable $exception) {
                $this->record->forceFill([
                    'plyomat_base_url' => null,
                    'plyomat_stats_path' => null,
                    'plyomat_auth_header' => null,
                    'plyomat_auth_prefix' => null,
                ])->saveQuietly();

                Notification::make()
                    ->title('Plyomat token saved but not verified')
                    ->body($exception->getMessage())
                    ->warning()
                    ->send();
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
