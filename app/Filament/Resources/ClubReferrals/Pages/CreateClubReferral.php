<?php

namespace App\Filament\Resources\ClubReferrals\Pages;

use App\Filament\Pages\ClubAdminDashboard;
use App\Filament\Resources\ClubReferrals\ClubReferralResource;
use Filament\Resources\Pages\CreateRecord;

class CreateClubReferral extends CreateRecord
{
    protected static string $resource = ClubReferralResource::class;

    protected function authorizeAccess(): void
    {
        abort(403, 'Use the Send Invite popup on the Club Dashboard.');
    }

    protected function getRedirectUrl(): string
    {
        return ClubAdminDashboard::getUrl();
    }
}