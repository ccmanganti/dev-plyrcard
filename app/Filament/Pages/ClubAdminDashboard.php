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
use Illuminate\Support\Str;
use UnitEnum;

class ClubAdminDashboard extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedHome;

    protected static string | UnitEnum | null $navigationGroup = 'Club Admin';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.club-admin-dashboard';

    /**
     * IMPORTANT:
     * This is NOT a club_leagues.id anymore.
     *
     * After the club/league restructure, duplicate club_leagues and even duplicate
     * leagues can exist. The dashboard uses a normalized program key instead:
     *
     * club_id + normalized league name + normalized sport + normalized genders
     */
    public ?string $selectedProgramKey = null;

    public ?string $selectedTeam = null;

    public ?int $selectedPlayerId = null;

    public string $activePanel = 'players';

    public ?string $playerSearch = null;

    public ?string $playerPositionFilter = null;

    public ?string $gameSearch = null;

    public ?string $gameStatusFilter = null;

    public ?string $inviteProgramKey = null;

    public ?string $inviteTeamName = null;

    public ?string $inviteName = null;

    public ?string $inviteEmail = null;

    public ?string $scheduleProgramKey = null;

    public ?string $scheduleTeamName = null;

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
        $defaultProgramKey = $this->defaultProgramKey();
        $defaultTeam = $this->defaultTeamName();

        $this->selectedProgramKey ??= $defaultProgramKey;
        $this->selectedTeam ??= $defaultTeam;

        $this->inviteProgramKey ??= $defaultProgramKey;
        $this->inviteTeamName ??= $defaultTeam;

        $this->scheduleProgramKey ??= $defaultProgramKey;
        $this->scheduleTeamName ??= $defaultTeam;

        $this->scheduleDate ??= now()->toDateString();
        $this->scheduleTime ??= '18:00';
    }

    protected function defaultTeamName(): string
    {
        return (string) (collect(config('plyrcard.age_groups', [
            'u13' => 'U13',
            'u14' => 'U14',
            'u15' => 'U15',
            'u16' => 'U16',
            'u17' => 'U17',
            'u18' => 'U18',
            'u19' => 'U19',
        ]))->values()->first() ?: 'U13');
    }

    protected function defaultProgramKey(): ?string
    {
        return $this->programOptions->first()['key'] ?? null;
    }

    public function getAssignedClubProperty(): ?Club
    {
        return ClubManagerAccess::assignedClub(auth()->user());
    }

    /**
     * Unique dropdown options for the current club.
     *
     * Duplicate safety:
     * We DO NOT key by league_id because the restructure can leave duplicate League
     * rows with the same name/sport/gender and duplicate ClubLeague rows pointing
     * to those duplicate League rows.
     *
     * Instead, this groups by the label users actually mean:
     * normalized league name + normalized sport + normalized genders.
     */
    public function getProgramOptionsProperty(): Collection
    {
        $club = $this->assignedClub;

        if (! $club) {
            return collect();
        }

        $clubLeagues = ClubLeague::query()
            ->with('league')
            ->where('club_id', $club->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $clubLeagues
            ->groupBy(fn (ClubLeague $clubLeague): string => $this->programGroupKey($clubLeague))
            ->map(function (Collection $group) use ($club): array {
                /** @var ClubLeague $primary */
                $primary = $group->first();

                $leagueName = trim((string) ($primary->league?->name ?: 'League'));
                $sport = trim((string) ($primary->sport ?: $primary->league?->sport ?: ''));

                $genders = $group
                    ->flatMap(fn (ClubLeague $clubLeague) => $clubLeague->genders ?? $clubLeague->league?->genders ?? [])
                    ->map(fn ($value) => ClubLeague::normalizeGender($value) ?: Str::lower(trim((string) $value)))
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values();

                if ($genders->isEmpty()) {
                    $legacyGender = ClubLeague::normalizeGender($primary->league?->gender);
                    if ($legacyGender) {
                        $genders = collect([$legacyGender]);
                    }
                }

                $labelParts = collect([
                    $leagueName,
                    $sport,
                    $genders->isNotEmpty() ? $genders->implode('/') : null,
                ])->filter();

                return [
                    'key' => $this->programGroupKey($primary),
                    'label' => $labelParts->implode(' • '),
                    'club_id' => (int) $club->id,
                    'primary_club_league_id' => (int) $primary->id,
                    'primary_league_id' => (int) $primary->league_id,
                    'club_league_ids' => $group->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all(),
                    'league_ids' => $group->pluck('league_id')->map(fn ($id) => (int) $id)->unique()->values()->all(),
                    'sport' => $sport,
                    'genders' => $genders->values()->all(),
                ];
            })
            ->sortBy('label')
            ->values();
    }

    protected function programGroupKey(ClubLeague $clubLeague): string
    {
        $leagueName = Str::of((string) ($clubLeague->league?->name ?: 'league'))
            ->lower()
            ->squish()
            ->toString();

        $sport = Str::of((string) ($clubLeague->sport ?: $clubLeague->league?->sport ?: ''))
            ->lower()
            ->squish()
            ->toString();

        $genders = collect($clubLeague->genders ?? $clubLeague->league?->genders ?? [])
            ->map(fn ($value) => ClubLeague::normalizeGender($value) ?: Str::lower(trim((string) $value)))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($genders->isEmpty()) {
            $legacyGender = ClubLeague::normalizeGender($clubLeague->league?->gender);
            if ($legacyGender) {
                $genders = collect([$legacyGender]);
            }
        }

        return implode('|', [
            (int) $clubLeague->club_id,
            $leagueName,
            $sport,
            $genders->implode(','),
        ]);
    }

    protected function resolveProgram(?string $programKey): ?array
    {
        if (blank($programKey)) {
            return null;
        }

        return $this->programOptions->firstWhere('key', $programKey);
    }

    public function getSelectedProgramProperty(): ?array
    {
        return $this->resolveProgram($this->selectedProgramKey);
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
        $program = $this->selectedProgram;

        return collect($configured)
            ->map(fn ($label) => (string) $label)
            ->values()
            ->map(function (string $label) use ($club, $program): array {
                $players = $club && $program
                    ? $this->playersForProgramAndTeam($club, $program, $label)
                    : User::query()->whereRaw('1 = 0');

                return [
                    'name' => $label,
                    'player_count' => (clone $players)->count(),
                    'game_count' => $this->countTeamGames($label),
                ];
            });
    }

    protected function usersForProgram(Club $club, array $program)
    {
        $query = User::query()->where('club_id', $club->id);

        if (SchemaFacade::hasColumn('users', 'club_league_id')) {
            $query->where(function ($query) use ($program): void {
                $query
                    ->whereIn('club_league_id', $program['club_league_ids'])
                    ->orWhere(function ($query) use ($program): void {
                        /*
                         * Legacy fallback only. If a user has already been migrated
                         * to a club_league_id, do not match it by league_id again.
                         */
                        $query
                            ->whereNull('club_league_id')
                            ->whereIn('league_id', $program['league_ids']);
                    });
            });
        } else {
            $query->whereIn('league_id', $program['league_ids']);
        }

        return $query;
    }

    protected function playersForProgramAndTeam(Club $club, array $program, string $teamName)
    {
        return $this->usersForProgram($club, $program)
            ->where('team_name', $teamName);
    }

    protected function playerIdsForSelectedProgram()
    {
        $club = $this->assignedClub;
        $program = $this->selectedProgram;

        if (! $club || ! $program) {
            return collect();
        }

        return $this->usersForProgram($club, $program)
            ->distinct()
            ->pluck('id');
    }

    public function getPositionOptionsProperty(): array
    {
        $club = $this->assignedClub;
        $program = $this->selectedProgram;

        if (! $club || ! $program || ! $this->selectedTeam) {
            return [];
        }

        return $this->playersForProgramAndTeam($club, $program, $this->selectedTeam)
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
        $program = $this->selectedProgram;

        if (! $club || ! $program || ! $this->selectedTeam) {
            return new EloquentCollection();
        }

        return $this->playersForProgramAndTeam($club, $program, $this->selectedTeam)
            ->with(['club', 'league', 'websites'])
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

        return $player && ClubManagerAccess::canViewPlayer(auth()->user(), $player) ? $player : null;
    }

    public function getSelectedTeamGamesProperty(): EloquentCollection
    {
        $club = $this->assignedClub;
        $program = $this->selectedProgram;

        if (! $club || ! $program || ! $this->selectedTeam) {
            return new EloquentCollection();
        }

        $playerIds = $this->playersForProgramAndTeam($club, $program, $this->selectedTeam)
            ->distinct()
            ->pluck('id');

        return $this->gamesForPlayerIds($playerIds, 20);
    }

    public function getUpcomingGamesProperty(): EloquentCollection
    {
        return $this->gamesForPlayerIds($this->playerIdsForSelectedProgram(), 12, true);
    }

    protected function gamesForPlayerIds($playerIds, int $limit, bool $upcomingOnly = false): EloquentCollection
    {
        if ($playerIds->isEmpty()) {
            return new EloquentCollection();
        }

        return Schedule::query()
            ->whereHas('users', fn ($query) => $query->whereIn('users.id', $playerIds))
            ->when($upcomingOnly, fn ($query) => $query->where(function ($query): void {
                $query
                    ->whereNull('game_date')
                    ->orWhereDate('game_date', '>=', now()->toDateString());
            }))
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
            ->limit($limit)
            ->get();
    }

    public function getStatsProperty(): array
    {
        $playerIds = $this->playerIdsForSelectedProgram();

        $gamesQuery = Schedule::query()
            ->whereHas('users', fn ($query) => $query->whereIn('users.id', $playerIds));

        return [
            'teams' => $this->ageGroups->count(),
            'players' => $playerIds->count(),
            'games' => $playerIds->isEmpty() ? 0 : (clone $gamesQuery)->count(),
            'upcoming_games' => $playerIds->isEmpty() ? 0 : (clone $gamesQuery)
                ->where(function ($query): void {
                    $query
                        ->whereNull('game_date')
                        ->orWhereDate('game_date', '>=', now()->toDateString());
                })
                ->count(),
        ];
    }

    public function setSelectedProgram(string $programKey): void
    {
        $program = $this->resolveProgram($programKey);

        if (! $program) {
            return;
        }

        $this->selectedProgramKey = $program['key'];
        $this->scheduleProgramKey = $program['key'];
        $this->inviteProgramKey = $program['key'];

        $this->selectedTeam = $this->selectedTeam ?: $this->defaultTeamName();
        $this->scheduleTeamName = $this->selectedTeam;
        $this->inviteTeamName = $this->selectedTeam;

        $this->selectedPlayerId = null;
        $this->playerSearch = null;
        $this->playerPositionFilter = null;
        $this->gameSearch = null;
        $this->gameStatusFilter = null;
        $this->activePanel = 'players';
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

    public function createInvite(): void
    {
        $club = $this->assignedClub;
        $program = $this->resolveProgram($this->inviteProgramKey);

        if (! $club || ! $program) {
            Notification::make()->title('Select a league')->danger()->send();
            return;
        }

        ClubReferral::create([
            'club_manager_id' => auth()->id(),
            'club_id' => $club->id,
            'league_id' => $program['primary_league_id'],
            'club_league_id' => $program['primary_club_league_id'],
            'team_name' => $this->inviteTeamName,
            'sport' => $program['sport'],
            'gender' => collect($program['genders'] ?? [])->first(),
            'invited_name' => $this->inviteName,
            'invited_email' => $this->inviteEmail,
            'status' => 'active',
        ]);

        $this->inviteProgramKey = $this->selectedProgramKey;
        $this->inviteTeamName = $this->selectedTeam ?: $this->defaultTeamName();
        $this->inviteName = null;
        $this->inviteEmail = null;

        $this->dispatch('close-modal', id: 'club-invite-modal');

        Notification::make()->title('Invite created')->success()->send();
    }

    public function createTeamGame(): void
    {
        $club = $this->assignedClub;
        $program = $this->resolveProgram($this->scheduleProgramKey);

        if (! $club || ! $program) {
            Notification::make()->title('Select a league')->danger()->send();
            return;
        }

        if (blank($this->scheduleTeamName)) {
            Notification::make()->title('Select a team / age group')->danger()->send();
            return;
        }

        if (blank($this->scheduleTitle)) {
            Notification::make()->title('Game title is required')->danger()->send();
            return;
        }

        $players = $this->playersForProgramAndTeam($club, $program, $this->scheduleTeamName)
            ->distinct()
            ->pluck('id');

        if ($players->isEmpty()) {
            Notification::make()
                ->title('No players found')
                ->body("No players match {$club->name}, {$program['label']}, and {$this->scheduleTeamName}.")
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

        $this->selectedProgramKey = $program['key'];
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
            ->body("The game was added to {$this->scheduleTeamName} players in {$program['label']}.")
            ->success()
            ->send();
    }

    protected function countTeamGames(string $team): int
    {
        $club = $this->assignedClub;
        $program = $this->selectedProgram;

        if (! $club || ! $program) {
            return 0;
        }

        $playerIds = $this->playersForProgramAndTeam($club, $program, $team)
            ->distinct()
            ->pluck('id');

        if ($playerIds->isEmpty()) {
            return 0;
        }

        return Schedule::query()
            ->whereHas('users', fn ($query) => $query->whereIn('users.id', $playerIds))
            ->count();
    }
}