<?php

namespace App\Filament\Resources\ClubLandingPages\Pages;

use App\Filament\Pages\ClubAdminDashboard;
use App\Filament\Resources\ClubLandingPages\ClubLandingPageResource;
use Filament\Resources\Pages\EditRecord;

class EditClubLandingPage extends EditRecord
{
    protected static string $resource = ClubLandingPageResource::class;

    protected function authorizeAccess(): void
    {
        abort(403, 'Club managers can view the landing page, but cannot edit it.');
    }

    protected function getRedirectUrl(): string
    {
        return ClubAdminDashboard::getUrl();
    }
}