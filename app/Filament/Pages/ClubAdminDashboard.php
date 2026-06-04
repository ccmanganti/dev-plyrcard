<?php

namespace App\Filament\Pages;

use App\Models\Club;
use App\Models\ClubReferral;
use App\Models\User;
use App\Support\ClubManagerAccess;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use UnitEnum;

class ClubAdminDashboard extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedHome;

    protected static string | UnitEnum | null $navigationGroup = 'Club Admin';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.club-admin-dashboard';

    public static function shouldRegisterNavigation(): bool
    {
        return ClubManagerAccess::canAccessClubArea(auth()->user());
    }

    public function getTitle(): string | Htmlable
    {
        return 'Club Dashboard';
    }

    public function getHeading(): string | Htmlable
    {
        return 'Club Dashboard';
    }

    public function getAssignedClubProperty(): ?Club
    {
        $clubIds = ClubManagerAccess::clubAdminClubIds(auth()->user());

        if (empty($clubIds)) {
            return null;
        }

        return Club::query()
            ->whereKey($clubIds[0])
            ->first();
    }

    public function getAgeGroupsProperty(): Collection
    {
        $configured = config('plyrcard.age_groups', [
            'u13' => 'U13',
            'u14' => 'U14',
            'u15' => 'U15',
            'u16' => 'U16',
            'u17' => 'U17',
            'u18' => 'U18',
            'u19' => 'U19',
        ]);

        $club = $this->assignedClub;

        return collect($configured)
            ->map(fn ($label) => (string) $label)
            ->values()
            ->map(function (string $label) use ($club): array {
                $playerCount = $club
                    ? User::query()
                        ->where('club_id', $club->id)
                        ->where('team_name', $label)
                        ->count()
                    : 0;

                return [
                    'name' => $label,
                    'player_count' => $playerCount,
                ];
            });
    }

    public function getPlayersProperty(): EloquentCollection
    {
        $club = $this->assignedClub;

        return User::query()
            ->with(['club', 'league'])
            ->when($club, fn ($query) => $query->where('club_id', $club->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->latest()
            ->limit(12)
            ->get();
    }

    public function getInvitesProperty(): EloquentCollection
    {
        $club = $this->assignedClub;

        return ClubReferral::query()
            ->with(['league', 'registeredUser'])
            ->when($club, fn ($query) => $query->where('club_id', $club->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->latest()
            ->limit(8)
            ->get();
    }

    public function getStatsProperty(): array
    {
        $club = $this->assignedClub;

        if (! $club) {
            return [
                'teams' => 0,
                'players' => 0,
                'registered_this_month' => 0,
                'invites' => 0,
                'invite_clicks' => 0,
                'invite_conversions' => 0,
            ];
        }

        $playersQuery = User::query()->where('club_id', $club->id);
        $invitesQuery = ClubReferral::query()->where('club_id', $club->id);

        return [
            'teams' => $this->ageGroups->count(),
            'players' => (clone $playersQuery)->count(),
            'registered_this_month' => (clone $playersQuery)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'invites' => (clone $invitesQuery)->count(),
            'invite_clicks' => (clone $invitesQuery)->whereNotNull('clicked_at')->count(),
            'invite_conversions' => (clone $invitesQuery)->whereNotNull('registered_at')->count(),
        ];
    }
}