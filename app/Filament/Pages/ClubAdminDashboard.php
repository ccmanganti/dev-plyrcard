<?php

namespace App\Filament\Pages;

use App\Models\Club;
use App\Models\ClubLeague;
use App\Models\ClubReferral;
use App\Models\Schedule;
use App\Models\User;
use App\Support\ClubManagerAccess;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use UnitEnum;

class ClubAdminDashboard extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedHome;

    protected static string | UnitEnum | null $navigationGroup = 'Club Admin';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.club-admin-dashboard';

    public ?string $selectedTeam = null;

    public ?int $selectedPlayerId = null;

    public string $activePanel = 'players';

    public ?string $playerSearch = null;

    public ?string $playerPositionFilter = null;

    public ?string $gameSearch = null;

    public ?string $gameStatusFilter = null;

    public ?string $inviteClubLeagueId = null;

    public ?string $inviteTeamName = null;

    public ?string $inviteName = null;

    public ?string $inviteEmail = null;

    public ?string $scheduleTeamName = null;

    public ?string $scheduleClubLeagueId = null;

    public ?string $scheduleTitle = null;

    public ?string $scheduleOpponent = null;

    public ?string $scheduleDate = null;

    public ?string $scheduleTime = null;

    public ?string $scheduleLocation = null;

    public ?string $scheduleVenue = null;

    public ?string $scheduleStatus = 'scheduled';

    public bool $scheduleIsHome = true;

    public ?string $scheduleNotes = null;

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
        $defaultTeam = $this->defaultTeamName();

        $this->selectedTeam = $this->selectedTeam ?: $defaultTeam;
        $this->inviteTeamName = $this->inviteTeamName ?: $defaultTeam;
        $this->scheduleTeamName = $this->scheduleTeamName ?: $defaultTeam;

        $defaultClubLeagueId = $this->defaultClubLeagueId();
        $this->inviteClubLeagueId = $this->inviteClubLeagueId ?: $defaultClubLeagueId;
        $this->scheduleClubLeagueId = $this->scheduleClubLeagueId ?: $defaultClubLeagueId;

        $this->scheduleDate = $this->scheduleDate ?: now()->toDateString();
        $this->scheduleTime = $this->scheduleTime ?: '18:00';
    }

    protected function defaultTeamName(): string
    {
        return (string) collect(config('plyrcard.age_groups', [
            'u13' => 'U13',
            'u14' => 'U14',
            'u15' => 'U15',
            'u16' => 'U16',
            'u17' => 'U17',
            'u18' => 'U18',
            'u19' => 'U19',
        ]))->values()->first() ?: 'U13';
    }

    public function setScheduleTeamName(string $team): void
    {
        $validTeams = $this->ageGroups
            ->pluck('name')
            ->map(fn ($value) => (string) $value)
            ->all();

        if (! in_array($team, $validTeams, true)) {
            return;
        }

        $this->scheduleTeamName = $team;
        $this->selectedTeam = $team;
    }


    protected function defaultClubLeagueId(): ?string
    {
        return $this->programs
            ->first()
            ?->id
            ? (string) $this->programs->first()->id
            : null;
    }

    protected function resolveClubLeague(?string $clubLeagueId): ?ClubLeague
    {
        $club = $this->assignedClub;

        if (! $club || blank($clubLeagueId)) {
            return null;
        }

        return ClubLeague::query()
            ->with('league')
            ->where('club_id', $club->id)
            ->whereKey($clubLeagueId)
            ->first();
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
                    ? User::query()->where('club_id', $club->id)->where('team_name', $label)
                    : User::query()->whereRaw('1 = 0');

                return [
                    'name' => $label,
                    'player_count' => (clone $players)->count(),
                    'game_count' => $this->countTeamGames($label),
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

    public function getPositionOptionsProperty(): array
    {
        $club = $this->assignedClub;

        if (! $club || ! $this->selectedTeam) {
            return [];
        }

        return User::query()
            ->where('club_id', $club->id)
            ->where('team_name', $this->selectedTeam)
            ->whereNotNull('position')
            ->get()
            ->flatMap(fn (User $user) => collect($user->position ?? [])->flatten())
            ->filter()
            ->unique()
            ->values()
            ->mapWithKeys(fn ($position) => [(string) $position => (string) $position])
            ->all();
    }

    public function getSelectedTeamPlayersProperty(): EloquentCollection
    {
        $club = $this->assignedClub;

        return User::query()
            ->with(['club', 'league', 'websites'])
            ->when($club, fn ($query) => $query->where('club_id', $club->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->when($this->selectedTeam, fn ($query) => $query->where('team_name', $this->selectedTeam), fn ($query) => $query->whereRaw('1 = 0'))
            ->when(filled($this->playerSearch), function ($query): void {
                $search = trim((string) $this->playerSearch);

                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('personal_email', 'like', "%{$search}%");
                });
            })
            ->when(filled($this->playerPositionFilter), fn ($query) => $query->where('position', 'like', '%' . $this->playerPositionFilter . '%'))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function getSelectedPlayerProperty(): ?User
    {
        if (! $this->selectedPlayerId) {
            return null;
        }

        $player = User::query()
            ->with(['club', 'league', 'websites', 'schedules'])
            ->find($this->selectedPlayerId);

        if (! $player || ! ClubManagerAccess::canViewPlayer(auth()->user(), $player)) {
            return null;
        }

        return $player;
    }

    public function getSelectedTeamGamesProperty(): EloquentCollection
    {
        $club = $this->assignedClub;

        if (! $club || ! $this->selectedTeam) {
            return new EloquentCollection();
        }

        $playerIds = User::query()
            ->where('club_id', $club->id)
            ->where('team_name', $this->selectedTeam)
            ->pluck('id');

        if ($playerIds->isEmpty()) {
            return new EloquentCollection();
        }

        return Schedule::query()
            ->whereHas('users', fn ($query) => $query->whereIn('users.id', $playerIds))
            ->when(filled($this->gameSearch), function ($query): void {
                $search = trim((string) $this->gameSearch);

                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('opponent', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('venue', 'like', "%{$search}%");
                });
            })
            ->when(filled($this->gameStatusFilter), fn ($query) => $query->where('status', $this->gameStatusFilter))
            ->withCount('users')
            ->orderByRaw('game_date is null')
            ->orderBy('game_date')
            ->orderBy('game_time')
            ->limit(20)
            ->get();
    }

    public function getUpcomingGamesProperty(): EloquentCollection
    {
        $club = $this->assignedClub;

        if (! $club) {
            return new EloquentCollection();
        }

        $playerIds = User::query()
            ->where('club_id', $club->id)
            ->pluck('id');

        if ($playerIds->isEmpty()) {
            return new EloquentCollection();
        }

        return Schedule::query()
            ->whereHas('users', fn ($query) => $query->whereIn('users.id', $playerIds))
            ->where(function ($query): void {
                $query->whereNull('game_date')
                    ->orWhereDate('game_date', '>=', now()->toDateString());
            })
            ->when(filled($this->gameSearch), function ($query): void {
                $search = trim((string) $this->gameSearch);

                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('opponent', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('venue', 'like', "%{$search}%");
                });
            })
            ->when(filled($this->gameStatusFilter), fn ($query) => $query->where('status', $this->gameStatusFilter))
            ->withCount('users')
            ->orderByRaw('game_date is null')
            ->orderBy('game_date')
            ->orderBy('game_time')
            ->limit(12)
            ->get();
    }

    public function getStatsProperty(): array
    {
        $club = $this->assignedClub;

        if (! $club) {
            return [
                'teams' => 0,
                'players' => 0,
                'games' => 0,
                'upcoming_games' => 0,
            ];
        }

        $playerIds = User::query()
            ->where('club_id', $club->id)
            ->pluck('id');

        $gamesQuery = Schedule::query()
            ->whereHas('users', fn ($query) => $query->whereIn('users.id', $playerIds));

        return [
            'teams' => $this->ageGroups->count(),
            'players' => $playerIds->count(),
            'games' => (clone $gamesQuery)->count(),
            'upcoming_games' => (clone $gamesQuery)
                ->where(function ($query): void {
                    $query->whereNull('game_date')
                        ->orWhereDate('game_date', '>=', now()->toDateString());
                })
                ->count(),
        ];
    }

    public function setPlayerPositionFilter(?string $position): void
    {
        $this->playerPositionFilter = $position ?: null;
        $this->selectedPlayerId = null;
    }

    public function setGameStatusFilter(?string $status): void
    {
        $this->gameStatusFilter = $status ?: null;
    }

    public function updatedPlayerSearch(): void
    {
        $this->selectedPlayerId = null;
    }

    public function selectPanel(string $panel): void
    {
        $this->activePanel = $panel;

        if ($panel === 'players' && ! $this->selectedTeam) {
            $this->selectedTeam = $this->defaultTeamName();
        }

        if ($panel !== 'player') {
            $this->selectedPlayerId = null;
        }
    }

    public function selectTeam(string $team): void
    {
        $this->selectedTeam = $team;
        $this->scheduleTeamName = $team;
        $this->inviteTeamName = $team;
        $this->selectedPlayerId = null;
        $this->activePanel = 'players';
    }

    public function showTeamGames(string $team): void
    {
        $this->selectedTeam = $team;
        $this->scheduleTeamName = $team;
        $this->selectedPlayerId = null;
        $this->activePanel = 'games';
    }

    public function selectPlayer(int $playerId): void
    {
        $player = User::query()->find($playerId);

        if (! $player || ! ClubManagerAccess::canViewPlayer(auth()->user(), $player)) {
            return;
        }

        $this->selectedPlayerId = $playerId;
        $this->activePanel = 'player';
    }

    public function clearSelectedPlayer(): void
    {
        $this->selectedPlayerId = null;
        $this->activePanel = 'players';
    }

    public function clearSelectedTeam(): void
    {
        $this->selectedTeam = null;
        $this->selectedPlayerId = null;
        $this->activePanel = 'teams';
    }

    public function createInvite(): void
    {
        $club = $this->assignedClub;

        if (! $club) {
            Notification::make()->title('No club assigned')->danger()->send();
            return;
        }

        $program = filled($this->inviteClubLeagueId)
            ? ClubLeague::query()->with('league')->where('club_id', $club->id)->find($this->inviteClubLeagueId)
            : null;

        if (! $program) {
            Notification::make()->title('Select a program')->danger()->send();
            return;
        }

        ClubReferral::create([
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
        $this->inviteTeamName = $this->selectedTeam ?: $this->defaultTeamName();
        $this->inviteName = null;
        $this->inviteEmail = null;

        $this->dispatch('close-modal', id: 'club-invite-modal');

        Notification::make()->title('Invite created')->success()->send();
    }

    public function createTeamGame(): void
    {
        $club = $this->assignedClub;

        if (! $club) {
            Notification::make()->title('No club assigned')->danger()->send();
            return;
        }

        $program = $this->resolveClubLeague($this->scheduleClubLeagueId);

        if (! $program) {
            Notification::make()
                ->title('Select a league')
                ->body('Choose one of the leagues currently associated with this club.')
                ->danger()
                ->send();

            return;
        }

        $validTeams = $this->ageGroups
            ->pluck('name')
            ->map(fn ($value) => (string) $value)
            ->all();

        if (blank($this->scheduleTeamName) || ! in_array($this->scheduleTeamName, $validTeams, true)) {
            Notification::make()
                ->title('Select a valid team / age group')
                ->body('Choose one of the listed age groups for this club.')
                ->danger()
                ->send();

            return;
        }

        if (blank($this->scheduleTitle)) {
            Notification::make()->title('Game title is required')->danger()->send();
            return;
        }

        /*
         * Current club + selected league/program + selected age group.
         *
         * The restructure introduced ClubLeague, so prefer users.club_league_id
         * when the column exists. For older/player rows that only have league_id,
         * fall back to users.league_id.
         */
        $playersQuery = User::query()
            ->where('club_id', $club->id)
            ->where('team_name', $this->scheduleTeamName);

        if (SchemaFacade::hasColumn('users', 'club_league_id')) {
            $playersQuery->where(function ($query) use ($program): void {
                $query
                    ->where('club_league_id', $program->id)
                    ->orWhere('league_id', $program->league_id);
            });
        } else {
            $playersQuery->where('league_id', $program->league_id);
        }

        $players = $playersQuery->pluck('id');

        if ($players->isEmpty()) {
            Notification::make()
                ->title('No players found')
                ->body("No players match {$club->name}, {$program->league?->name}, and {$this->scheduleTeamName}.")
                ->warning()
                ->send();

            return;
        }

        $schedule = Schedule::create([
            'created_by_user_id' => auth()->id(),
            'title' => $this->scheduleTitle,
            'opponent' => $this->scheduleOpponent,
            'game_date' => $this->scheduleDate,
            'game_time' => $this->scheduleTime,
            'location' => $this->scheduleLocation,
            'venue' => $this->scheduleVenue,
            'status' => $this->scheduleStatus ?: 'scheduled',
            'is_home' => $this->scheduleIsHome,
            'notes' => $this->scheduleNotes,
        ]);

        $schedule->users()->syncWithoutDetaching($players->all());

        $this->selectedTeam = $this->scheduleTeamName;
        $this->activePanel = 'games';

        $this->scheduleTitle = null;
        $this->scheduleOpponent = null;
        $this->scheduleDate = now()->toDateString();
        $this->scheduleTime = '18:00';
        $this->scheduleLocation = null;
        $this->scheduleVenue = null;
        $this->scheduleStatus = 'scheduled';
        $this->scheduleIsHome = true;
        $this->scheduleNotes = null;

        $this->dispatch('close-modal', id: 'club-game-modal');

        Notification::make()
            ->title('Game created')
            ->body("The game was added to {$this->scheduleTeamName} players in {$program->league?->name}.")
            ->success()
            ->send();
    }

    protected function countTeamGames(string $team): int
    {
        $club = $this->assignedClub;

        if (! $club) {
            return 0;
        }

        $playerIds = User::query()
            ->where('club_id', $club->id)
            ->where('team_name', $team)
            ->pluck('id');

        if ($playerIds->isEmpty()) {
            return 0;
        }

        return Schedule::query()
            ->whereHas('users', fn ($query) => $query->whereIn('users.id', $playerIds))
            ->count();
    }
}