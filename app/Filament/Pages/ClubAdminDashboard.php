<?php

namespace App\Filament\Pages;

use App\Models\Club;
use App\Models\ClubLeague;
use App\Models\ClubReferral;
use App\Models\Schedule;
use App\Models\TeamManagerAssignment;
use App\Models\User;
use App\Support\ClubManagerAccess;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Builder;
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
     * The dashboard selects a visible league/program group first, then a gender.
     *
     * This value is not club_leagues.id. It is a normalized league group key:
     * club_id + normalized league name + normalized sport.
     */
    public ?string $selectedLeagueKey = null;

    /**
     * male | female | coed
     */
    public ?string $selectedGender = null;

    public ?string $selectedTeam = null;

    public ?int $selectedPlayerId = null;

    public string $activePanel = 'players';

    public ?string $playerSearch = null;

    public ?string $playerPositionFilter = null;

    public ?string $gameSearch = null;

    public ?string $gameStatusFilter = null;

    public ?string $inviteLeagueKey = null;

    public ?string $inviteGender = null;

    public ?string $inviteTeamName = null;

    public ?string $inviteName = null;

    public ?string $inviteEmail = null;

    public ?string $scheduleLeagueKey = null;

    public ?string $scheduleGender = null;

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


    protected function isTeamManagerDashboard(): bool
    {
        return ClubManagerAccess::isTeamManager(auth()->user());
    }

    protected function managerAssignmentRows(): Collection
    {
        return ClubManagerAccess::teamManagerAssignments(auth()->user());
    }

    protected function managerAllowedTeamNames(): array
    {
        return ClubManagerAccess::allowedTeamNames(auth()->user());
    }

    protected function managerAllowedClubLeagueIds(): array
    {
        return ClubManagerAccess::allowedClubLeagueIds(auth()->user());
    }

    protected function managerAllowedLeagueIds(): array
    {
        return ClubManagerAccess::allowedLeagueIds(auth()->user());
    }

    protected function managerCanUseProgram(array $program): bool
    {
        if (! $this->isTeamManagerDashboard()) {
            return true;
        }

        $allowedClubLeagueIds = $this->managerAllowedClubLeagueIds();
        $allowedLeagueIds = $this->managerAllowedLeagueIds();

        $programClubLeagueIds = collect($program['club_league_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
        $programLeagueIds = collect($program['league_ids'] ?? [])->map(fn ($id) => (int) $id)->all();

        return ! empty(array_intersect($allowedClubLeagueIds, $programClubLeagueIds))
            || ! empty(array_intersect($allowedLeagueIds, $programLeagueIds));
    }

    protected function managerCanUseTeam(?string $teamName): bool
    {
        if (! $this->isTeamManagerDashboard()) {
            return true;
        }

        $teamName = strtoupper(trim((string) $teamName));

        return $teamName !== ''
            && in_array($teamName, $this->managerAllowedTeamNames(), true);
    }

    public function mount(): void
    {
        $defaultLeagueKey = $this->defaultLeagueKey();
        $defaultGender = $this->defaultGenderForLeague($defaultLeagueKey);
        $defaultTeam = $this->defaultTeamName();

        $this->selectedLeagueKey ??= $defaultLeagueKey;
        $this->selectedGender ??= $defaultGender;
        $this->selectedTeam ??= $this->firstAvailableTeamName() ?: $defaultTeam;

        $this->inviteLeagueKey ??= $this->selectedLeagueKey;
        $this->inviteGender ??= $this->selectedGender;
        $this->inviteTeamName ??= $this->selectedTeam;

        $this->scheduleLeagueKey ??= $this->selectedLeagueKey;
        $this->scheduleGender ??= $this->selectedGender;
        $this->scheduleTeamName ??= $this->selectedTeam;

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

    protected function defaultLeagueKey(): ?string
    {
        return $this->leagueOptions->first()['key'] ?? null;
    }

    protected function defaultGenderForLeague(?string $leagueKey): ?string
    {
        $league = $this->resolveLeagueOption($leagueKey);

        return collect($league['genders'] ?? [])->first() ?: 'male';
    }

    protected function firstAvailableTeamName(): ?string
    {
        return $this->ageGroups->first()['name'] ?? null;
    }

    public function getAssignedClubProperty(): ?Club
    {
        return ClubManagerAccess::assignedClub(auth()->user());
    }

    /**
     * Selectable league buttons for the current club.
     *
     * Duplicate-safe grouping:
     * We do not key by league_id because the database restructure can leave
     * duplicate League rows with the same visible name/sport/logo.
     */
    public function getLeagueOptionsProperty(): Collection
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
            ->groupBy(fn (ClubLeague $clubLeague): string => $this->leagueGroupKey($clubLeague))
            ->map(function (Collection $group) use ($club): array {
                /** @var ClubLeague $primary */
                $primary = $group->first();

                $leagueName = trim((string) ($primary->league?->name ?: 'League'));
                $sport = trim((string) ($primary->sport ?: $primary->league?->sport ?: ''));
                $logo = $primary->league?->logo;

                $genders = $group
                    ->flatMap(function (ClubLeague $clubLeague) {
                        $values = collect($clubLeague->genders ?? []);

                        if ($values->isEmpty() && $clubLeague->league) {
                            $values = collect($clubLeague->league->genders ?? [$clubLeague->league->gender ?? null]);
                        }

                        return $values;
                    })
                    ->map(fn ($value) => $this->normalizeGender($value))
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values();

                if ($genders->contains('coed')) {
                    $genders = collect(['male', 'female']);
                }

                if ($genders->isEmpty()) {
                    $genders = collect(['male']);
                }

                return [
                    'key' => $this->leagueGroupKey($primary),
                    'label' => $leagueName,
                    'sport' => $sport,
                    'logo' => $logo,
                    'genders' => $genders->values()->all(),
                    'club_id' => (int) $club->id,
                    'primary_club_league_id' => (int) $primary->id,
                    'primary_league_id' => (int) $primary->league_id,
                    'club_league_ids' => $group->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all(),
                    'league_ids' => $group->pluck('league_id')->map(fn ($id) => (int) $id)->unique()->values()->all(),
                ];
            })
            ->sortBy('label')
            ->filter(fn (array $program): bool => $this->managerCanUseProgram($program))
            ->values();
    }

    protected function leagueGroupKey(ClubLeague $clubLeague): string
    {
        $leagueName = Str::of((string) ($clubLeague->league?->name ?: 'league'))
            ->lower()
            ->squish()
            ->toString();

        $sport = Str::of((string) ($clubLeague->sport ?: $clubLeague->league?->sport ?: ''))
            ->lower()
            ->squish()
            ->toString();

        return implode('|', [
            (int) $clubLeague->club_id,
            $leagueName,
            $sport,
        ]);
    }

    protected function normalizeGender(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return null;
        }

        if (str_contains($value, 'female') || str_contains($value, 'girl') || str_contains($value, 'women') || str_contains($value, 'woman')) {
            return 'female';
        }

        if (str_contains($value, 'male') || str_contains($value, 'boy') || str_contains($value, 'men') || str_contains($value, 'man')) {
            return 'male';
        }

        if (str_contains($value, 'coed') || str_contains($value, 'mixed')) {
            return 'coed';
        }

        return in_array($value, ['male', 'female', 'coed'], true) ? $value : null;
    }

    protected function genderLabel(?string $gender): string
    {
        return match ($gender) {
            'female' => 'Girls',
            'male' => 'Boys',
            'coed' => 'Coed',
            default => 'All',
        };
    }

    protected function resolveLeagueOption(?string $leagueKey): ?array
    {
        if (blank($leagueKey)) {
            return null;
        }

        return $this->leagueOptions->firstWhere('key', $leagueKey);
    }

    protected function resolveProgram(?string $leagueKey, ?string $gender = null): ?array
    {
        $league = $this->resolveLeagueOption($leagueKey);

        if (! $league) {
            return null;
        }

        $gender = $this->normalizeGender($gender) ?: collect($league['genders'])->first();

        if ($gender && ! in_array($gender, $league['genders'], true)) {
            $gender = collect($league['genders'])->first();
        }

        return [
            ...$league,
            'gender' => $gender,
            'gender_label' => $this->genderLabel($gender),
            'label' => trim(collect([
                $league['label'] ?? null,
                $league['sport'] ?? null,
                $this->genderLabel($gender),
            ])->filter()->implode(' • ')),
        ];
    }

    public function getSelectedLeagueProperty(): ?array
    {
        return $this->resolveLeagueOption($this->selectedLeagueKey);
    }

    public function getSelectedProgramProperty(): ?array
    {
        return $this->resolveProgram($this->selectedLeagueKey, $this->selectedGender);
    }

    public function getAvailableGenderOptionsProperty(): Collection
    {
        $league = $this->selectedLeague;

        return collect($league['genders'] ?? [])
            ->map(fn ($gender) => [
                'value' => $gender,
                'label' => $this->genderLabel($gender),
            ])
            ->values();
    }

    public function getAllAgeGroupsProperty(): Collection
    {
        return collect(config('plyrcard.age_groups', [
            'u13' => 'U13',
            'u14' => 'U14',
            'u15' => 'U15',
            'u16' => 'U16',
            'u17' => 'U17',
            'u18' => 'U18',
            'u19' => 'U19',
        ]))
            ->map(fn ($label) => (string) $label)
            ->values();
    }

    /**
     * Only age groups that actually exist for the selected league + gender are shown.
     */
    public function getAgeGroupsProperty(): Collection
    {
        $club = $this->assignedClub;
        $program = $this->selectedProgram;

        if (! $club || ! $program) {
            return collect();
        }

        return $this->allAgeGroups
            ->map(function (string $label) use ($club, $program): array {
                $players = $this->playersForProgramGenderAndTeam($club, $program, $label);

                return [
                    'name' => $label,
                    'player_count' => (clone $players)->count(),
                    'game_count' => $this->countTeamGames($label),
                ];
            })
            ->filter(fn (array $ageGroup): bool => $this->managerCanUseTeam($ageGroup['name']))
            ->filter(fn (array $ageGroup): bool => ((int) $ageGroup['player_count'] > 0) || ((int) $ageGroup['game_count'] > 0))
            ->values();
    }

    protected function usersForProgram(Club $club, array $program)
    {
        $query = $this->scopedPlayersForCurrentManager(User::query())

            ->where('club_id', $club->id);

        if (SchemaFacade::hasColumn('users', 'club_league_id')) {
            $query->where(function ($query) use ($program): void {
                $query
                    ->whereIn('club_league_id', $program['club_league_ids'])
                    ->orWhere(function ($query) use ($program): void {
                        $query
                            ->whereNull('club_league_id')
                            ->whereIn('league_id', $program['league_ids']);
                    });
            });
        } else {
            $query->whereIn('league_id', $program['league_ids']);
        }

        $gender = $this->normalizeGender($program['gender'] ?? null);

        if ($gender && $gender !== 'coed') {
            $query->where(function ($query) use ($gender): void {
                $query
                    ->where('gender', $gender)
                    ->orWhere('gender', $this->genderLabel($gender))
                    ->orWhere('gender', strtolower($this->genderLabel($gender)))
                    ->orWhere('gender', $gender === 'female' ? 'girls' : 'boys')
                    ->orWhere('gender', $gender === 'female' ? 'Girl' : 'Boy')
                    ->orWhere('gender', $gender === 'female' ? 'Female' : 'Male');
            });
        }

        return $query;
    }

    protected function playersForProgramGenderAndTeam(Club $club, array $program, string $teamName)
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

        $query = $this->usersForProgram($club, $program);

        if ($this->isTeamManagerDashboard()) {
            $allowedTeamNames = $this->managerAllowedTeamNames();

            if (empty($allowedTeamNames)) {
                return collect();
            }

            $query->whereIn('team_name', $allowedTeamNames);
        }

        return $query->pluck('id');
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


    protected function scopedPlayersForCurrentManager(Builder $query): Builder
    {
        return ClubManagerAccess::scopePlayers($query, auth()->user());
    }

    public function getStatsProperty(): array
    {
        $club = $this->assignedClub;
        $program = $this->selectedProgram;

        if (! $club || ! $program) {
            return [
                'teams' => 0,
                'players' => 0,
                'games' => 0,
                'upcoming_games' => 0,
            ];
        }

        $allowedTeamNames = collect($this->ageGroups)
            ->pluck('name')
            ->filter(fn ($teamName): bool => $this->managerCanUseTeam((string) $teamName))
            ->map(fn ($teamName): string => strtoupper(trim((string) $teamName)))
            ->unique()
            ->values();

        if ($this->isTeamManagerDashboard() && $allowedTeamNames->isEmpty()) {
            return [
                'teams' => 0,
                'players' => 0,
                'games' => 0,
                'upcoming_games' => 0,
            ];
        }

        $playersQuery = $this->usersForProgram($club, $program);

        if ($allowedTeamNames->isNotEmpty()) {
            $playersQuery->whereIn('team_name', $allowedTeamNames->all());
        }

        $playerIds = (clone $playersQuery)->pluck('id');

        $gamesQuery = Schedule::query()
            ->whereHas('users', fn (Builder $query): Builder => $query->whereIn('users.id', $playerIds));

        return [
            'teams' => $allowedTeamNames->count(),
            'players' => (clone $playersQuery)->count(),
            'games' => (clone $gamesQuery)->count(),
            'upcoming_games' => (clone $gamesQuery)
                ->whereDate('game_date', '>=', now()->toDateString())
                ->count(),
        ];
    }

    public function setSelectedLeague(string $leagueKey): void
    {
        $league = $this->resolveLeagueOption($leagueKey);

        if (! $league) {
            return;
        }

        $this->selectedLeagueKey = $league['key'];
        $this->selectedGender = $this->defaultGenderForLeague($league['key']);

        $this->scheduleLeagueKey = $this->selectedLeagueKey;
        $this->scheduleGender = $this->selectedGender;

        $this->inviteLeagueKey = $this->selectedLeagueKey;
        $this->inviteGender = $this->selectedGender;

        $this->selectedTeam = $this->firstAvailableTeamName() ?: $this->defaultTeamName();
        $this->scheduleTeamName = $this->selectedTeam;
        $this->inviteTeamName = $this->selectedTeam;

        $this->resetDetailState();
    }
    public function setSelectedLeagueGender(string $leagueKey, string $gender): void
    {
        $league = $this->resolveLeagueOption($leagueKey);
        $gender = $this->normalizeGender($gender);

        if (! $league || ! $gender || ! in_array($gender, $league['genders'] ?? [], true)) {
            return;
        }

        $this->selectedLeagueKey = $league['key'];
        $this->selectedGender = $gender;

        $this->scheduleLeagueKey = $this->selectedLeagueKey;
        $this->scheduleGender = $this->selectedGender;

        $this->inviteLeagueKey = $this->selectedLeagueKey;
        $this->inviteGender = $this->selectedGender;

        $this->selectedTeam = $this->firstAvailableTeamName() ?: $this->defaultTeamName();
        $this->scheduleTeamName = $this->selectedTeam;
        $this->inviteTeamName = $this->selectedTeam;

        $this->resetDetailState();
    }

    public function setSelectedGender(string $gender): void
    {
        $gender = $this->normalizeGender($gender);

        if (! $gender || ! in_array($gender, $this->selectedLeague['genders'] ?? [], true)) {
            return;
        }

        $this->selectedGender = $gender;
        $this->scheduleGender = $gender;
        $this->inviteGender = $gender;

        $this->selectedTeam = $this->firstAvailableTeamName() ?: $this->defaultTeamName();
        $this->scheduleTeamName = $this->selectedTeam;
        $this->inviteTeamName = $this->selectedTeam;

        $this->resetDetailState();
    }

    protected function resetDetailState(): void
    {
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
        /*
         * Hidden from UI for now, but kept functional so existing routes/actions do not break.
         */
        $club = $this->assignedClub;
        $program = $this->resolveProgram($this->inviteLeagueKey, $this->inviteGender);

        if (! $club || ! $program) {
            Notification::make()->title('Select a league and gender')->danger()->send();
            return;
        }

        ClubReferral::create([
            'club_manager_id' => auth()->id(),
            'club_id' => $club->id,
            'league_id' => $program['primary_league_id'],
            'club_league_id' => $program['primary_club_league_id'],
            'team_name' => $this->inviteTeamName,
            'sport' => $program['sport'],
            'gender' => $program['gender'],
            'invited_name' => $this->inviteName,
            'invited_email' => $this->inviteEmail,
            'status' => 'active',
        ]);

        $this->inviteLeagueKey = $this->selectedLeagueKey;
        $this->inviteGender = $this->selectedGender;
        $this->inviteTeamName = $this->selectedTeam ?: $this->defaultTeamName();
        $this->inviteName = null;
        $this->inviteEmail = null;

        $this->dispatch('close-modal', id: 'club-invite-modal');

        Notification::make()->title('Invite created')->success()->send();
    }

    public function createTeamGame(): void
    {
        $club = $this->assignedClub;
        $program = $this->resolveProgram($this->scheduleLeagueKey, $this->scheduleGender);

        if (! $club || ! $program) {
            Notification::make()->title('Select a league and gender')->danger()->send();
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

        $players = $this->playersForProgramGenderAndTeam($club, $program, $this->scheduleTeamName)
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

        $this->selectedLeagueKey = $program['key'];
        $this->selectedGender = $program['gender'];
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
        
        if (! $this->managerCanUseTeam($teamName)) {
            return 0;
        }

        $club = $this->assignedClub;
        $program = $this->selectedProgram;

        if (! $club || ! $program) {
            return 0;
        }

        $playerIds = $this->playersForProgramGenderAndTeam($club, $program, $team)
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