<?php

namespace App\Filament\Resources\Clubs\Pages;

use App\Filament\Resources\Clubs\ClubResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditClub extends EditRecord
{
    protected static string $resource = ClubResource::class;

    protected array $coachAccountsForSave = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['coach_accounts'] = ClubResource::coachAccountsFormState($this->record);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->coachAccountsForSave = $data['coach_accounts'] ?? [];

        unset($data['coach_accounts']);

        return $data;
    }

    protected function afterSave(): void
    {
        ClubResource::syncCoachAccountsFromForm($this->record, $this->coachAccountsForSave);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make(),
        ];
    }
}