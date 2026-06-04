<?php

namespace App\Filament\Resources\ClubLandingPages\Pages;

use App\Filament\Resources\ClubLandingPages\ClubLandingPageResource;
use App\Support\ClubManagerAccess;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditClubLandingPage extends EditRecord
{
    protected static string $resource = ClubLandingPageResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless(
            ClubManagerAccess::userCanAccessClub(auth()->user(), $this->record),
            403
        );
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless(
            ClubManagerAccess::userCanAccessClub(auth()->user(), $record),
            403
        );

        $record->update($data);

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return \App\Filament\Pages\ClubAdminDashboard::getUrl();
    }
}
