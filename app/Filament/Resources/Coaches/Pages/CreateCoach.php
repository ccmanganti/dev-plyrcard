<?php

namespace App\Filament\Resources\Coaches\Pages;

use App\Filament\Resources\Coaches\CoachResource;
use App\Services\CoachGhlSyncPlanner;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCoach extends CreateRecord
{
    protected static string $resource = CoachResource::class;

    protected string $view = 'filament.resources.coaches.pages.create-coach';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['source'] ??= 'manual';
        $data['ghl_sync_status'] = 'pending';

        return $data;
    }

    protected function afterCreate(): void
    {
        $summary = app(CoachGhlSyncPlanner::class)->planForCoach($this->record);

        Notification::make()
            ->title('Coach saved locally')
            ->body($summary['credential_groups'] > 0
                ? sprintf(
                    'Prepared synchronization checks for %d unique GHL subaccount credential group(s). Actual GHL contact creation will be enabled in the next synchronization update.',
                    $summary['credential_groups'],
                )
                : 'No user accounts with both a GHL API key and location ID were found. The coach remains pending locally.')
            ->success()
            ->persistent()
            ->send();
    }
}