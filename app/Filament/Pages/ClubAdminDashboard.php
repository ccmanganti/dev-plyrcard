<?php

namespace App\Filament\Pages;

use App\Models\Club;
use App\Models\ClubLeague;
use App\Models\ClubReferral;
use App\Models\User;
use App\Support\ClubManagerAccess;
use BackedEnum;
use Filament\Notifications\Notification;
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

    public ?string $selectedTeam = null;

    public ?string $inviteClubLeagueId = null;

    public ?string $inviteTeamName = null;

    public ?string $inviteName = null;

    public ?string $inviteEmail = null;

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

    public function mount(): void
    {
        $this->inviteTeamName = $this->inviteTeamName ?: 'U13';
    }

    public function getAssignedClubProperty(): ?Club
    {
        return ClubManagerAccess::assignedClub(auth()->user());
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
                $players = $club
                    ? User::query()
                        ->where('club_id', $club->id)
                        ->where('team_name', $label)
                    : User::query()->whereRaw('1 = 0');

                return [
                    'name' => $label,
                    'player_count' => (clone $players)->count(),
                    'recent_count' => (clone $players)->where('created_at', '>=', now()->subDays(30))->count(),
                ];
            });
    }

    public function getProgramsProperty(): EloquentCollection
    {
        $club = $this->assignedClub;

        return ClubLeague::query()
            ->with('league')
            ->when($club, fn ($query) => $query->where('club_id', $club->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function getSelectedTeamPlayersProperty(): EloquentCollection
    {
        $club = $this->assignedClub;

        return User::query()
            ->with(['club', 'league', 'websites'])
            ->when($club, fn ($query) => $query->where('club_id', $club->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->when($this->selectedTeam, fn ($query) => $query->where('team_name', $this->selectedTeam), fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('last_name')
            ->orderBy('first_name')
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

    public function selectTeam(string $team): void
    {
        $this->selectedTeam = $team;
    }

    public function clearSelectedTeam(): void
    {
        $this->selectedTeam = null;
    }

    public function createInvite(): void
    {
        $club = $this->assignedClub;

        if (! $club) {
            Notification::make()
                ->title('No club assigned')
                ->body('Assign one club before creating invites.')
                ->danger()
                ->send();

            return;
        }

        $program = filled($this->inviteClubLeagueId)
            ? ClubLeague::query()->with('league')->where('club_id', $club->id)->find($this->inviteClubLeagueId)
            : null;

        if (! $program) {
            Notification::make()
                ->title('Select a program')
                ->body('Choose a league/program before creating the invite.')
                ->danger()
                ->send();

            return;
        }

        $referral = ClubReferral::create([
            'club_manager_id' => auth()->id(),
            'club_id' => $club->id,
            'league_id' => $program->league_id,
            'club_league_id' => $program->id,
            'team_name' => $this->inviteTeamName,
            'sport' => $program->sport ?: $program->league?->sport,
            'gender' => collect($program->genders ?? [])->first(),
            'invited_name' => $this->inviteName,
            'invited_email' => $this->inviteEmail,
            'status' => 'active',
        ]);

        $this->inviteClubLeagueId = null;
        $this->inviteTeamName = 'U13';
        $this->inviteName = null;
        $this->inviteEmail = null;

        $this->dispatch('close-modal', id: 'club-invite-modal');

        Notification::make()
            ->title('Invite created')
            ->body('Invite URL copied from the Invites section when needed.')
            ->success()
            ->send();
    }
}