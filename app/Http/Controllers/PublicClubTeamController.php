<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubLeague;
use App\Models\League;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicClubTeamController extends Controller
{
    public function club(string $clubSlug): View
    {
        $club = Club::query()
            ->with([
                'league',
                'clubLeagues' => function ($query) {
                    $query
                        ->where('is_active', true)
                        ->with('league')
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->where('landing_page_slug', $clubSlug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $this->dedupeActiveClubLeagues($club);
        $this->attachDisplayLeague($club);

        $players = User::query()
            ->with([
                'websites' => function ($query) {
                    $query
                        ->where('is_active', true)
                        ->where('is_published', true)
                        ->latest('updated_at');
                },
                'roles',
            ])
            ->where('club_id', $club->id)
            ->whereNotNull('team_name')
            ->where('team_name', '!=', '')
            ->get();

        $teams = $this->landingTeamsForClub($club, $players);

        $journeyPlayers = $players
            ->filter(function (User $player): bool {
                if (blank($player->plyrcard_image)) {
                    return false;
                }

                if (! method_exists($player, 'getRoleNames')) {
                    return false;
                }

                $roles = $player->getRoleNames()->map(fn ($role) => strtolower(trim((string) $role)));

                return $roles->contains('my journey')
                    || $roles->contains('my-journey')
                    || $roles->contains('plyr plus')
                    || $roles->contains('plyr-plus');
            })
            ->groupBy(function (User $player) use ($club): string {
                $program = $this->clubLeagueForPlayer($club, $player);

                return $this->teamKey(
                    $this->landingGenderSegmentForPlayer($player),
                    $player->team_name,
                    $program?->id,
                );
            })
            ->map(function ($group) {
                return $group->shuffle()->map(function (User $player) {
                    $website = $player->websites->first();

                    return [
                        'id' => $player->id,
                        'name' => trim(($player->first_name ?? '') . ' ' . ($player->last_name ?? '')),
                        'image' => $player->plyrcard_image,
                        'website_url' => $website
                            ? (filled($website->domain)
                                ? 'https://' . preg_replace('/^https?:\/\//', '', $website->domain)
                                : url('/' . ltrim((string) $website->slug, '/')))
                            : null,
                    ];
                })->values();
            });

        $teamJourneyCards = $teams
            ->mapWithKeys(fn ($team) => [$team->id => $journeyPlayers->get((string) $team->id, collect())->all()])
            ->all();

        return view('public.club-landing', [
            'club' => $club,
            'teams' => $teams,
            'teamJourneyCards' => $teamJourneyCards,
            'coachCheckIn' => session('coach_checkin'),
            'savedPlayers' => session('coach_saved_players', []),
        ]);
    }

    public function team(string $clubSlug, string $gender, string $teamSlug): View
    {
        $club = Club::query()
            ->with([
                'league',
                'clubLeagues' => function ($query) {
                    $query
                        ->where('is_active', true)
                        ->with('league')
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->where('landing_page_slug', $clubSlug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $this->dedupeActiveClubLeagues($club);
        $this->attachDisplayLeague($club);

        $requestedGender = $this->normalizeLandingGenderSegment($gender);

        $players = $this->playersForLandingTeam($club, $requestedGender, $teamSlug, request('program'));

        abort_if($players->isEmpty(), 404);

        $teamName = (string) $players->first()->team_name;
        $program = $this->resolveRequestedProgram($club, request('program'))
            ?: $this->clubLeagueForPlayer($club, $players->first())
            ?: $this->clubLeagueForGender($club, $requestedGender, $players->first()?->sport);

        $team = $this->buildLandingTeam($club, $requestedGender, $teamName, $players, $program);

        return view('public.team-landing', [
            'team' => $team,
            'club' => $club,
            'players' => $players,
            'coachCheckIn' => session('coach_checkin'),
            'savedPlayers' => session('coach_saved_players', []),
        ]);
    }

    public function legacyTeam(string $teamSlug): RedirectResponse
    {
        $player = User::query()
            ->with('club')
            ->whereNotNull('team_name')
            ->get()
            ->first(fn (User $player): bool => Str::slug((string) $player->team_name) === $teamSlug);

        abort_unless($player?->club?->landing_page_slug, 404);

        return redirect()->route('clubs.teams.landing', [
            'clubSlug' => $player->club->landing_page_slug,
            'gender' => $this->landingGenderSegmentForPlayer($player),
            'teamSlug' => Str::slug((string) $player->team_name),
        ], 301);
    }

    public function coachCheckIn(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::query()
            ->where('landing_page_slug', $clubSlug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $validated = $request->validate([
            'school' => ['required', 'string', 'max:160'],
            'name' => ['required', 'string', 'max:160'],
            'title' => ['nullable', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:190'],
        ]);

        session([
            'coach_checkin' => [
                'club_id' => $club->id,
                'club_slug' => $club->landing_page_slug,
                'school' => $validated['school'],
                'name' => $validated['name'],
                'title' => $validated['title'] ?? '',
                'email' => strtolower($validated['email']),
                'checked_in_at' => now()->toDateTimeString(),
            ],
        ]);

        session()->put('coach_saved_players', session('coach_saved_players', []));

        return back()->with('coach_checkin_success', 'You are checked in. You can now save players while reviewing this club.');
    }

    public function coachCheckOut(Request $request, string $clubSlug): RedirectResponse
    {
        Club::query()
            ->where('landing_page_slug', $clubSlug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $request->session()->forget([
            'coach_checkin',
            'coach_saved_players',
        ]);

        return back()->with('coach_checkin_success', 'You have been checked out.');
    }

    public function savePlayer(
        Request $request,
        string $clubSlug,
        string $gender,
        string $teamSlug,
        User $player
    ): RedirectResponse|JsonResponse {
        $club = $this->publishedClubBySlug($clubSlug);
        $requestedGender = $this->normalizeLandingGenderSegment($gender);

        abort_unless($this->playerBelongsToLandingTeam($player, $club, $requestedGender, $teamSlug), 404);

        $coachCheckIn = session('coach_checkin');

        if (! is_array($coachCheckIn) || empty($coachCheckIn['email'])) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please check in as a coach before saving players.',
                ], 422);
            }

            return back()->withErrors([
                'coach_checkin' => 'Please check in as a coach before saving players.',
            ]);
        }

        $player->loadMissing(['school', 'club.league', 'league', 'nationalTeam', 'websites']);

        $coachEmail = strtolower((string) ($coachCheckIn['email'] ?? ''));
        $playerUrl = $this->playerWebsiteUrl($player);
        $teamName = trim((string) $player->team_name);
        $teamKey = $this->teamKey($requestedGender, $teamName, $player->club_league_id);

        $savedPlayers = collect(session('coach_saved_players', []));

        $alreadySaved = $savedPlayers->contains(function ($saved) use ($player, $club, $teamKey, $coachEmail) {
            return (int) ($saved['player_id'] ?? 0) === (int) $player->id
                && (int) ($saved['club_id'] ?? 0) === (int) $club->id
                && (string) ($saved['team_key'] ?? '') === $teamKey
                && strtolower((string) ($saved['coach_email'] ?? '')) === $coachEmail;
        });

        $savedPayload = [
            'player_id' => $player->id,
            'id' => $player->id,
            'player_name' => trim(($player->first_name ?? '') . ' ' . ($player->last_name ?? '')),
            'name' => trim(($player->first_name ?? '') . ' ' . ($player->last_name ?? '')),
            'first_name' => $player->first_name,
            'last_name' => $player->last_name,
            'player_email' => $player->email,
            'player_personal_email' => $player->personal_email,
            'player_phone' => $player->phone,
            'player_url' => $playerUrl,

            'parent' => $player->parent,
            'parent_email' => $player->parent_email,
            'parent_phone' => $player->parent_phone,

            'sec_parent' => $player->sec_parent,
            'sec_parent_email' => $player->sec_parent_email,
            'sec_parent_phone' => $player->sec_parent_phone,

            'club_coach' => $player->club_coach,
            'club_coach_email' => $player->club_coach_email,
            'club_coach_phone' => $player->club_coach_phone,

            'jersey_number' => $player->jersey_number,
            'jersey' => $player->jersey_number,
            'card_image' => $this->publicAssetUrl($player->plyrcard_image),
            'plyrcard_image' => $this->publicAssetUrl($player->plyrcard_image),
            'portrait_image' => $this->publicAssetUrl($player->player_image ?: $player->action_image ?: $player->youtube_thumbnail ?: $player->mobile_hero_image),
            'player_image' => $this->publicAssetUrl($player->player_image),
            'main_image' => $this->publicAssetUrl($player->player_image ?: $player->action_image ?: $player->mobile_hero_image ?: $player->plyrcard_image),
            'position' => $this->abbreviatedPosition($player->position),
            'year' => $player->year,
            'height' => $player->height,
            'weight' => $player->weight,
            'gpa' => $player->gpa,
            'city' => $player->city,
            'state' => $player->state,
            'school' => $player->school?->name,
            'sport' => $player->sport,

            'club_id' => $club->id,
            'club_name' => $club->name,
            'team_key' => $teamKey,
            'team_slug' => $teamSlug,
            'team_name' => $teamName,
            'program_id' => $player->club_league_id,
            'gender' => $requestedGender,
            'league_name' => $player->league?->name ?? $club->league?->name,

            'coach_email' => $coachEmail,
            'coach_name' => $coachCheckIn['name'] ?? '',
            'coach_school' => $coachCheckIn['school'] ?? '',
            'coach_title' => $coachCheckIn['title'] ?? '',

            'saved_at' => now()->toDateTimeString(),
        ];

        if (! $alreadySaved) {
            $savedPlayers->push($savedPayload);
            session(['coach_saved_players' => $savedPlayers->values()->all()]);
        }

        $message = $alreadySaved
            ? 'Player already saved to your watchlist.'
            : 'Player saved to your watchlist.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'email_sent' => false,
                'saved_count' => $savedPlayers->count(),
                'player_id' => $player->id,
                'saved_player' => $savedPayload,
            ]);
        }

        return back()->with('player_save_success', $message);
    }

    public function unsavePlayer(
        Request $request,
        string $clubSlug,
        string $gender,
        string $teamSlug,
        User $player
    ): RedirectResponse|JsonResponse {
        $club = $this->publishedClubBySlug($clubSlug);
        $requestedGender = $this->normalizeLandingGenderSegment($gender);
        $teamName = trim((string) $player->team_name);
        $teamKey = $this->teamKey($requestedGender, $teamName, $player->club_league_id);

        abort_unless($this->playerBelongsToLandingTeam($player, $club, $requestedGender, $teamSlug), 404);

        $coachCheckIn = session('coach_checkin');
        $coachEmail = strtolower((string) ($coachCheckIn['email'] ?? ''));

        $savedPlayers = collect(session('coach_saved_players', []))
            ->reject(function ($saved) use ($player, $club, $teamKey, $coachEmail) {
                return (int) ($saved['player_id'] ?? 0) === (int) $player->id
                    && (int) ($saved['club_id'] ?? 0) === (int) $club->id
                    && (string) ($saved['team_key'] ?? '') === $teamKey
                    && strtolower((string) ($saved['coach_email'] ?? '')) === $coachEmail;
            })
            ->values();

        session(['coach_saved_players' => $savedPlayers->all()]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Player removed from saved list.',
                'saved_count' => $savedPlayers->count(),
                'player_id' => $player->id,
            ]);
        }

        return back()->with('player_save_success', 'Player removed from saved list.');
    }

    public function emailWatchlist(Request $request, string $clubSlug): RedirectResponse|JsonResponse
    {
        $club = Club::query()
            ->with('league')
            ->where('landing_page_slug', $clubSlug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $coachCheckIn = session('coach_checkin');

        if (! is_array($coachCheckIn) || empty($coachCheckIn['email'])) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please check in as a coach before emailing your watchlist.',
                ], 422);
            }

            return back()->withErrors([
                'coach_checkin' => 'Please check in as a coach before emailing your watchlist.',
            ]);
        }

        $coachEmail = strtolower((string) ($coachCheckIn['email'] ?? ''));

        $watchlist = collect(session('coach_saved_players', []))
            ->filter(function ($saved) use ($club, $coachEmail) {
                return (int) ($saved['club_id'] ?? 0) === (int) $club->id
                    && strtolower((string) ($saved['coach_email'] ?? '')) === $coachEmail;
            })
            ->unique(fn ($saved) => (int) ($saved['player_id'] ?? 0))
            ->values();

        if ($watchlist->isEmpty()) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your watchlist is empty. Save at least one player first.',
                ], 422);
            }

            return back()->withErrors([
                'watchlist' => 'Your watchlist is empty. Save at least one player first.',
            ]);
        }

        try {
            $sent = $this->sendCoachWatchlistEmail($coachEmail, $coachCheckIn, $watchlist->all(), $club);
        } catch (\Throwable $exception) {
            report($exception);
            $sent = false;
        }

        if (! $sent) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The watchlist was not sent. Please try again.',
                ], 500);
            }

            return back()->withErrors([
                'watchlist' => 'The watchlist was not sent. Please try again.',
            ]);
        }

        $message = 'Email sent to ' . $coachEmail . '.';

        session()->flash('watchlist_email_sent', $message);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'saved_count' => $watchlist->count(),
                'sent_to' => $coachEmail,
            ]);
        }

        return back()->with('watchlist_email_sent', $message);
    }

    protected function publishedClubBySlug(string $clubSlug): Club
    {
        $club = Club::query()
            ->with([
                'league',
                'clubLeagues' => function ($query) {
                    $query
                        ->where('is_active', true)
                        ->with('league')
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->where('landing_page_slug', $clubSlug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $this->dedupeActiveClubLeagues($club);
        $this->attachDisplayLeague($club);

        return $club;
    }

    protected function normalizeProgramGenderValue(?string $value): ?string
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

    protected function programDedupeKey(?ClubLeague $program): string
    {
        if (! $program) {
            return 'legacy';
        }

        /*
         * IMPORTANT:
         * Do not use league_id in this key.
         *
         * After the database restructure, it is possible to have duplicate
         * League rows with the same visible league name/sport/gender, and
         * duplicate ClubLeague rows pointing at those duplicate League rows.
         *
         * Public pages should show the visible program once.
         */
        $leagueName = Str::of((string) ($program->league?->name ?: 'league'))
            ->lower()
            ->squish()
            ->toString();

        $sport = Str::of((string) ($program->sport ?: $program->league?->sport ?: ''))
            ->lower()
            ->squish()
            ->toString();

        $genders = collect($program->genders ?? $program->league?->genders ?? [])
            ->map(fn ($gender) => $this->normalizeProgramGenderValue($gender))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($genders->isEmpty()) {
            $legacyGender = $this->normalizeProgramGenderValue($program->league?->gender);

            if ($legacyGender) {
                $genders = collect([$legacyGender]);
            }
        }

        return implode('|', [
            (int) ($program->club_id ?? 0),
            $leagueName,
            $sport,
            $genders->implode(','),
        ]);
    }

    protected function dedupeActiveClubLeagues(Club $club): void
    {
        if (! $club->relationLoaded('clubLeagues')) {
            return;
        }

        $deduped = $club->clubLeagues
            ->filter(fn ($program) => ($program->is_active ?? true) && blank($program->deleted_at ?? null))
            ->sortBy([
                fn ($program) => $program->sort_order ?? 0,
                fn ($program) => $program->id ?? 0,
            ])
            ->unique(fn ($program) => $this->programDedupeKey($program))
            ->values();

        $club->setRelation('clubLeagues', $deduped);
    }

    protected function allActiveClubLeaguesForProgram(Club $club, ClubLeague $program): Collection
    {
        $key = $this->programDedupeKey($program);

        return ClubLeague::query()
            ->with('league')
            ->where('club_id', $club->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (ClubLeague $candidate): bool => $this->programDedupeKey($candidate) === $key)
            ->values();
    }

    protected function programClubLeagueIds(Club $club, ?ClubLeague $program): array
    {
        if (! $program) {
            return [];
        }

        return $this->allActiveClubLeaguesForProgram($club, $program)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function programLeagueIds(Club $club, ?ClubLeague $program): array
    {
        if (! $program) {
            return [];
        }

        return $this->allActiveClubLeaguesForProgram($club, $program)
            ->pluck('league_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function canonicalProgramForClubLeague(Club $club, ?ClubLeague $program): ?ClubLeague
    {
        if (! $program) {
            return null;
        }

        $key = $this->programDedupeKey($program);

        return $club->clubLeagues
            ->first(fn (ClubLeague $candidate): bool => $this->programDedupeKey($candidate) === $key)
            ?: $program;
    }

    protected function resolveRequestedProgram(Club $club, mixed $programId): ?ClubLeague
    {
        if (blank($programId)) {
            return null;
        }

        $program = $club->clubLeagues->firstWhere('id', (int) $programId);

        if ($program) {
            return $program;
        }

        $duplicate = ClubLeague::query()
            ->with('league')
            ->where('club_id', $club->id)
            ->whereKey((int) $programId)
            ->first();

        return $this->canonicalProgramForClubLeague($club, $duplicate);
    }

    protected function clubLeagueForPlayer(Club $club, ?User $player): ?ClubLeague
    {
        if (! $player) {
            return null;
        }

        if (filled($player->club_league_id)) {
            $program = $this->resolveRequestedProgram($club, $player->club_league_id);

            if ($program) {
                return $program;
            }
        }

        $program = ClubLeague::query()
            ->with('league')
            ->where('club_id', $club->id)
            ->where('league_id', $player->league_id)
            ->where('is_active', true)
            ->when(filled($player->sport), fn ($query) => $query->where(function ($query) use ($player): void {
                $query
                    ->whereNull('sport')
                    ->orWhere('sport', '')
                    ->orWhere('sport', $player->sport);
            }))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        return $this->canonicalProgramForClubLeague($club, $program)
            ?: $this->clubLeagueForGender($club, $this->landingGenderSegmentForPlayer($player), $player->sport);
    }

    protected function playerMatchesProgram(User $player, Club $club, ?ClubLeague $program): bool
    {
        if (! $program) {
            return true;
        }

        $clubLeagueIds = $this->programClubLeagueIds($club, $program);
        $leagueIds = $this->programLeagueIds($club, $program);

        if (filled($player->club_league_id)) {
            return in_array((int) $player->club_league_id, $clubLeagueIds, true);
        }

        return filled($player->league_id)
            && in_array((int) $player->league_id, $leagueIds, true);
    }


    protected function attachDisplayLeague(Club $club): void
    {
        if ($club->relationLoaded('clubLeagues')) {
            $league = $club->clubLeagues
                ->pluck('league')
                ->filter()
                ->first();

            if ($league) {
                $club->setRelation('league', $league);
            }
        }
    }

    protected function landingTeamsForClub(Club $club, Collection $players): Collection
    {
        return $players
            ->filter(fn (User $player): bool => filled($player->team_name))
            ->groupBy(function (User $player) use ($club): string {
                $program = $this->clubLeagueForPlayer($club, $player);

                return $this->teamKey(
                    $this->landingGenderSegmentForPlayer($player),
                    $player->team_name,
                    $program?->id,
                );
            })
            ->map(function (Collection $group) use ($club) {
                $player = $group->first();
                $program = $this->clubLeagueForPlayer($club, $player)
                    ?: $this->clubLeagueForGender($club, $this->landingGenderSegmentForPlayer($player), $player?->sport);

                return $this->buildLandingTeam(
                    $club,
                    $this->landingGenderSegmentForPlayer($player),
                    (string) $player->team_name,
                    $group,
                    $program,
                );
            })
            ->sortBy([
                fn ($team) => $team->league_name ?: '',
                fn ($team) => $team->gender_segment === 'boys' ? 0 : 1,
                fn ($team) => $team->name,
            ])
            ->values();
    }

    protected function buildLandingTeam(Club $club, string $genderSegment, string $teamName, Collection $players, ?ClubLeague $program = null): Fluent
    {
        $slug = Str::slug($teamName);
        $program ??= $this->clubLeagueForGender($club, $genderSegment, $players->first()?->sport);
        $teamKey = $this->teamKey($genderSegment, $teamName, $program?->id);

        $coach = collect(is_array($club->coaching_staff ?? null) ? $club->coaching_staff : [])->first() ?? [];

        $location = trim(collect([$club->city, $club->state])->filter()->implode(', '));

        return new Fluent([
            'id' => $teamKey,
            'name' => $teamName,
            'landing_page_slug' => $slug,
            'landing_url' => route('clubs.teams.landing', [
                'clubSlug' => $club->landing_page_slug,
                'gender' => $genderSegment,
                'teamSlug' => $slug,
                'program' => $program?->id,
            ]),
            'gender_segment' => $genderSegment,
            'logo' => $club->logo,
            'league_name' => $program?->league?->name ?? $club->league?->name,
            'league_logo' => $program?->league?->logo ?? $club->league?->logo,
            'program_id' => $program?->id,
            'coach_name' => $coach['name'] ?? $coach['full_name'] ?? 'TBA',
            'coach_email' => $coach['email'] ?? null,
            'coach_phone' => $coach['phone'] ?? null,
            'background_image' => $club->background_image,
            'hero_image' => $club->hero_image ?? null,
            'branding' => $club->branding ?? [],
            'team_settings' => [
                'gender' => $genderSegment,
                'league' => $program?->league?->name ?? $club->league?->name,
                'league_logo' => $program?->league?->logo ?? $club->league?->logo,
                'location' => $location,
                'has_location' => filled($location),
            ],
            'coaching_staff' => $club->coaching_staff ?? [],
            'landing_page_content' => $club->landing_page_content,
            'club' => $club,
            'player_count' => $players->count(),
        ]);
    }

    protected function clubLeagueForGender(Club $club, string $genderSegment, ?string $sport = null): ?ClubLeague
    {
        $gender = $genderSegment === 'girls' ? 'female' : 'male';

        return $club->clubLeagues
            ->first(function (ClubLeague $clubLeague) use ($gender, $sport): bool {
                $genders = collect($clubLeague->genders ?? [])->map(fn ($value) => strtolower((string) $value));

                $genderMatches = $genders->isEmpty() || $genders->contains($gender);
                $sportMatches = blank($sport) || blank($clubLeague->sport) || $clubLeague->sport === $sport;

                return $genderMatches && $sportMatches;
            });
    }

    protected function playersForLandingTeam(Club $club, string $genderSegment, string $teamSlug, mixed $programId = null): Collection
    {
        $program = $this->resolveRequestedProgram($club, $programId);

        return User::query()
            ->with([
                'school',
                'club.league',
                'league',
                'nationalTeam',
                'roles',
                'websites' => function ($query) {
                    $query
                        ->where('is_active', true)
                        ->where('is_published', true)
                        ->latest('updated_at');
                },
            ])
            ->where('club_id', $club->id)
            ->whereNotNull('team_name')
            ->get()
            ->filter(function (User $player) use ($club, $genderSegment, $teamSlug, $program): bool {
                return $this->playerMatchesProgram($player, $club, $program)
                    && $this->landingGenderSegmentForPlayer($player) === $genderSegment
                    && Str::slug((string) $player->team_name) === $teamSlug;
            })
            ->sortBy(function (User $player) {
                $number = trim((string) ($player->jersey_number ?? ''));

                if ($number === '' || ! is_numeric($number)) {
                    return 9999;
                }

                return (int) $number;
            })
            ->values();
    }

    protected function normalizeLandingGenderSegment(string $gender): string
    {
        $gender = strtolower(trim($gender));

        return match ($gender) {
            'mens', 'men', 'boys', 'boy', 'male' => 'boys',
            'womens', 'women', 'girls', 'girl', 'female' => 'girls',
            default => $gender,
        };
    }

    protected function landingGenderSegmentForPlayer(User $player): string
    {
        $gender = strtolower((string) ($player->gender ?? ''));

        return match (true) {
            str_contains($gender, 'female'), str_contains($gender, 'girl'), str_contains($gender, 'women') => 'girls',
            default => 'boys',
        };
    }

    protected function teamKey(string $genderSegment, ?string $teamName, mixed $programId = null): string
    {
        return ($programId ?: 'program') . ':' . $this->normalizeLandingGenderSegment($genderSegment) . ':' . Str::slug((string) $teamName);
    }

    protected function playerBelongsToLandingTeam(User $player, Club $club, string $genderSegment, string $teamSlug): bool
    {
        $program = $this->resolveRequestedProgram($club, request('program'));

        return $this->playerMatchesProgram($player, $club, $program)
            && (int) ($player->club_id ?? 0) === (int) $club->id
            && $this->landingGenderSegmentForPlayer($player) === $this->normalizeLandingGenderSegment($genderSegment)
            && Str::slug((string) $player->team_name) === $teamSlug;
    }

    protected function publicAssetUrl(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (is_array($value)) {
            if (array_key_exists(0, $value)) {
                return $this->publicAssetUrl($value[0] ?? null);
            }

            return $this->publicAssetUrl($value['url'] ?? $value['path'] ?? $value['image_url'] ?? null);
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $this->publicAssetUrl($decoded);
        }

        return asset('storage/' . ltrim($value, '/'));
    }

    protected function abbreviatedPosition($value): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            }
        }

        $raw = is_array($value)
            ? collect($value)->filter()->implode(' | ')
            : trim((string) $value);

        if ($raw === '') {
            return 'PLYR';
        }

        $map = [
            'goalkeeper' => 'GK', 'keeper' => 'GK', 'defender' => 'DEF', 'center_back' => 'CB', 'centre_back' => 'CB', 'center back' => 'CB', 'centre back' => 'CB', 'left_back' => 'LB', 'left back' => 'LB', 'right_back' => 'RB', 'right back' => 'RB', 'full_back' => 'FB', 'full back' => 'FB', 'wing_back' => 'WB', 'wing back' => 'WB',
            'midfielder' => 'MID', 'defensive_midfielder' => 'CDM', 'defensive midfielder' => 'CDM', 'central_midfielder' => 'CM', 'central midfielder' => 'CM', 'attacking_midfielder' => 'CAM', 'attacking midfielder' => 'CAM', 'wide_midfielder' => 'WM', 'wide midfielder' => 'WM',
            'forward' => 'FWD', 'wide_forward' => 'WF', 'wide forward' => 'WF', 'striker' => 'ST', 'winger' => 'WG', 'left_wing' => 'LW', 'left wing' => 'LW', 'right_wing' => 'RW', 'right wing' => 'RW',
            'point_guard' => 'PG', 'point guard' => 'PG', 'shooting_guard' => 'SG', 'shooting guard' => 'SG', 'small_forward' => 'SF', 'small forward' => 'SF', 'power_forward' => 'PF', 'power forward' => 'PF', 'center' => 'C',
        ];

        return collect(preg_split('/\s*[|,\/]\s*/', $raw))
            ->filter()
            ->map(function ($item) use ($map) {
                $item = trim((string) $item);
                $key = Str::of($item)->lower()->replace('&', 'and')->replace('-', ' ')->replace('_', ' ')->squish()->toString();
                $underscored = str_replace(' ', '_', $key);

                if (isset($map[$key])) {
                    return $map[$key];
                }

                if (isset($map[$underscored])) {
                    return $map[$underscored];
                }

                $words = collect(explode(' ', $key))->filter();

                return $words->count() > 1
                    ? $words->map(fn ($word) => strtoupper(substr($word, 0, 1)))->implode('')
                    : strtoupper(substr($item, 0, 4));
            })
            ->filter()
            ->unique()
            ->implode(' / ') ?: 'PLYR';
    }

    protected function playerWebsiteUrl(User $player): ?string
    {
        $website = $player->websites
            ? ($player->websites->firstWhere('is_active', true) ?: $player->websites->first())
            : null;

        if (! $website) {
            return null;
        }

        if (filled($website->domain)) {
            return 'https://' . preg_replace('/^https?:\/\//', '', (string) $website->domain);
        }

        if (filled($website->slug)) {
            $slug = ltrim((string) $website->slug, '/');

            return Route::has('website.show-by-name')
                ? route('website.show-by-name', ['websiteName' => $slug])
                : url('/' . $slug);
        }

        if (filled($website->name)) {
            $slug = Str::slug((string) $website->name);

            return $slug
                ? (Route::has('website.show-by-name')
                    ? route('website.show-by-name', ['websiteName' => $slug])
                    : url('/' . $slug))
                : null;
        }

        return null;
    }

    protected function sendCoachWatchlistEmail(
        string $coachEmail,
        array $coachCheckIn,
        array $watchlist,
        Club $club
    ): bool {
        $coachEmail = strtolower(trim($coachEmail));

        if (! filter_var($coachEmail, FILTER_VALIDATE_EMAIL) || empty($watchlist)) {
            return false;
        }

        $fromEmail = 'support@plyrcard.com';
        $fromName = 'PlyrCard';
        $subject = 'Your PlyrCard Watchlist - ' . ($club->name ?? 'Club');

        $emailData = [
            'coach' => $coachCheckIn,
            'watchlist' => $watchlist,
            'club' => $club,
        ];

        try {
            $htmlBody = view('emails.coach-watchlist', $emailData)->render();
        } catch (\Throwable $exception) {
            report($exception);
            $htmlBody = nl2br(e($this->buildCoachWatchlistTextEmail($coachCheckIn, $watchlist, $club)));
        }

        $textBody = $this->buildCoachWatchlistTextEmail($coachCheckIn, $watchlist, $club);

        /*
        |--------------------------------------------------------------------------
        | Try Laravel Mail first, then native domain mail as a fallback.
        |--------------------------------------------------------------------------
        |
        | This makes the button work whether the app has a Laravel mail transport
        | configured or the server only allows the support@plyrcard.com native
        | domain sender. The controller still returns JSON so the drawer can show
        | the Email Sent check state immediately after a successful send.
        |
        */
        try {
            Mail::send('emails.coach-watchlist', $emailData, function ($message) use ($coachEmail, $fromEmail, $fromName, $subject) {
                $message
                    ->to($coachEmail)
                    ->from($fromEmail, $fromName)
                    ->replyTo($fromEmail, $fromName)
                    ->subject($subject);
            });

            return true;
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $this->sendNativeMultipartMail(
            to: $coachEmail,
            subject: $subject,
            textBody: $textBody,
            htmlBody: $htmlBody,
            fromEmail: $fromEmail,
            fromName: $fromName,
            replyTo: $fromEmail,
            envelopeFrom: $fromEmail
        );
    }

    protected function buildCoachWatchlistTextEmail(array $coachCheckIn, array $watchlist, Club $club): string
    {
        $lines = [
            'PlyrCard Coach Watchlist',
            '',
            'Coach:',
            'Name: ' . ($coachCheckIn['name'] ?? ''),
            'School: ' . ($coachCheckIn['school'] ?? ''),
            'Title: ' . ($coachCheckIn['title'] ?? ''),
            'Email: ' . ($coachCheckIn['email'] ?? ''),
            '',
            'Club: ' . ($club->name ?? ''),
            'League: ' . ($club->league?->name ?? ''),
            '',
            'Saved Players:',
        ];

        foreach ($watchlist as $index => $savedPlayer) {
            $lines[] = '';
            $lines[] = ($index + 1) . '. ' . (($savedPlayer['player_name'] ?? '') ?: 'Player');
            $lines[] = 'Team: ' . (($savedPlayer['team_name'] ?? '') ?: 'N/A');
            $lines[] = 'Jersey: ' . (($savedPlayer['jersey_number'] ?? '') ?: 'N/A');
            $lines[] = 'Position: ' . (($savedPlayer['position'] ?? '') ?: 'N/A');
            $lines[] = 'Class: ' . (($savedPlayer['year'] ?? '') ?: 'N/A');
            $lines[] = 'GPA: ' . (($savedPlayer['gpa'] ?? '') ?: 'N/A');
            $lines[] = 'Website: ' . (($savedPlayer['player_url'] ?? $savedPlayer['website_url'] ?? '') ?: 'No published website available.');
            $lines[] = 'Player Email: ' . (($savedPlayer['player_email'] ?? '') ?: 'N/A');
            $lines[] = 'Personal Email: ' . (($savedPlayer['player_personal_email'] ?? '') ?: 'N/A');
            $lines[] = 'Phone: ' . (($savedPlayer['player_phone'] ?? '') ?: 'N/A');
            $lines[] = 'Parent: ' . (($savedPlayer['parent'] ?? '') ?: 'N/A');
            $lines[] = 'Parent Email: ' . (($savedPlayer['parent_email'] ?? '') ?: 'N/A');
            $lines[] = 'Parent Phone: ' . (($savedPlayer['parent_phone'] ?? '') ?: 'N/A');
            $lines[] = 'Club Coach: ' . (($savedPlayer['club_coach'] ?? '') ?: 'N/A');
            $lines[] = 'Club Coach Email: ' . (($savedPlayer['club_coach_email'] ?? '') ?: 'N/A');
            $lines[] = 'Club Coach Phone: ' . (($savedPlayer['club_coach_phone'] ?? '') ?: 'N/A');
        }

        $lines[] = '';
        $lines[] = 'Sent from PlyrCard.';

        return implode("\n", $lines);
    }


    protected function sendNativeMultipartMail(
        string $to,
        string $subject,
        string $textBody,
        string $htmlBody,
        string $fromEmail = 'support@plyrcard.com',
        string $fromName = 'PlyrCard',
        string $replyTo = 'support@plyrcard.com',
        string $envelopeFrom = 'support@plyrcard.com'
    ): bool {
        $to = trim($to);
        $fromEmail = trim($fromEmail);
        $replyTo = trim($replyTo);
        $envelopeFrom = trim($envelopeFrom);

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (! filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = 'support@plyrcard.com';
        }

        if (! filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $replyTo = $fromEmail;
        }

        if (! filter_var($envelopeFrom, FILTER_VALIDATE_EMAIL)) {
            $envelopeFrom = $fromEmail;
        }

        $safeFromName = $this->sanitizeMailHeader($fromName ?: 'PlyrCard');
        $safeSubject = $this->sanitizeMailHeader($subject ?: 'Saved Player Information');
        $safeReplyTo = $this->sanitizeMailHeader($replyTo);

        $boundary = 'plyrcard_' . md5(uniqid((string) mt_rand(), true));

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'From: ' . $safeFromName . ' <' . $fromEmail . '>',
            'Reply-To: ' . $safeReplyTo,
            'X-Mailer: PHP/' . phpversion(),
        ];

        $message = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $textBody . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $htmlBody . "\r\n"
            . "--{$boundary}--";

        $headerString = implode("\r\n", $headers);

        $sent = @mail($to, $safeSubject, $message, $headerString, '-f' . $envelopeFrom);

        if (! $sent) {
            $sent = @mail($to, $safeSubject, $message, $headerString);
        }

        return (bool) $sent;
    }

    protected function sanitizeMailHeader(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }


}