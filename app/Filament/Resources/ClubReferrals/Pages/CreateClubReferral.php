<?php

namespace App\Filament\Resources\ClubReferrals\Pages;

use App\Filament\Pages\ClubAdminDashboard;
use App\Filament\Resources\ClubReferrals\ClubReferralResource;
use App\Models\ClubLeague;
use App\Support\ClubManagerAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateClubReferral extends CreateRecord
{
    protected static string $resource = ClubReferralResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $clubIds = ClubManagerAccess::clubAdminClubIds(auth()->user());
        $assignedClubId = count($clubIds) === 1 ? $clubIds[0] : ($data['club_id'] ?? null);

        abort_unless(
            $assignedClubId && ClubManagerAccess::userCanAccessClub(auth()->user(), (int) $assignedClubId),
            403
        );

        $program = filled($data['club_league_id'] ?? null)
            ? ClubLeague::query()->with('league')->find($data['club_league_id'])
            : null;

        $data['club_manager_id'] = auth()->id();
        $data['club_id'] = $assignedClubId;
        $data['league_id'] = $program?->league_id ?: ($data['league_id'] ?? null);
        $data['sport'] = $program?->sport ?: $program?->league?->sport ?: ($data['sport'] ?? null);
        $data['gender'] = collect($program?->genders ?? [])->first() ?: ($data['gender'] ?? null);
        $data['status'] = 'active';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return ClubAdminDashboard::getUrl();
    }
}
