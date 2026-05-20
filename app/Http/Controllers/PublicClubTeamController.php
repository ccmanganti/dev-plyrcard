<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicClubTeamController extends Controller
{
    public function club(string $clubSlug): View
    {
        $club = Club::query()
            ->with(['league', 'teams.club.league'])
            ->where('landing_page_slug', $clubSlug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $teams = $club->teams()
            ->with(['club.league'])
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->orderBy('name')
            ->get();

        return view('public.club-landing', [
            'club' => $club,
            'teams' => $teams,
            'coachCheckIn' => session('coach_checkin'),
            'savedPlayers' => session('coach_saved_players', []),
        ]);
    }

    public function team(string $clubSlug, string $gender, string $teamSlug): View
    {
        $club = Club::query()
            ->with('league')
            ->where('landing_page_slug', $clubSlug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $team = Team::query()
            ->with(['club.league'])
            ->where('club_id', $club->id)
            ->where('landing_page_slug', $teamSlug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Gender Segment Guard
        |--------------------------------------------------------------------------
        |
        | New URLs use boys/girls.
        | Old URLs using mens/womens are still accepted and normalized.
        |
        */

        $requestedGender = $this->normalizeLandingGenderSegment($gender);
        $teamGender = $this->normalizeLandingGenderSegment($team->landingGenderSegment());

        abort_unless($teamGender === $requestedGender, 404);

        $players = $this->playersForTeam($team, $club);

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
        $team = Team::query()
            ->with(['club.league'])
            ->where('landing_page_slug', $teamSlug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $club = $team->club;

        abort_unless(
            $club
            && $club->has_landing_page
            && $club->landing_page_is_published
            && filled($club->landing_page_slug),
            404
        );

        return redirect()->route('clubs.teams.landing', [
            'clubSlug' => $club->landing_page_slug,
            'gender' => $this->normalizeLandingGenderSegment($team->landingGenderSegment()),
            'teamSlug' => $team->landing_page_slug,
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

        return back()->with(
            'coach_checkin_success',
            'You are checked in. You can now save players while reviewing the team.'
        );
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
        $club = Club::query()
            ->where('landing_page_slug', $clubSlug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $team = Team::query()
            ->with(['club.league'])
            ->where('club_id', $club->id)
            ->where('landing_page_slug', $teamSlug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $requestedGender = $this->normalizeLandingGenderSegment($gender);
        $teamGender = $this->normalizeLandingGenderSegment($team->landingGenderSegment());

        abort_unless($teamGender === $requestedGender, 404);
        abort_unless($this->playerBelongsToTeam($player, $team, $club), 404);

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

        $coachEmail = strtolower((string) ($coachCheckIn['email'] ?? ''));

        $savedPlayers = collect(session('coach_saved_players', []));

        $alreadySaved = $savedPlayers->contains(function ($saved) use ($player, $club, $team, $coachEmail) {
            return (int) ($saved['player_id'] ?? 0) === (int) $player->id
                && (int) ($saved['club_id'] ?? 0) === (int) $club->id
                && (int) ($saved['team_id'] ?? 0) === (int) $team->id
                && strtolower((string) ($saved['coach_email'] ?? '')) === $coachEmail;
        });

        if (! $alreadySaved) {
            $savedPlayers->push([
                'player_id' => $player->id,
                'player_name' => trim(($player->first_name ?? '') . ' ' . ($player->last_name ?? '')),
                'player_email' => $player->email,
                'player_personal_email' => $player->personal_email,
                'player_phone' => $player->phone,

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
                'position' => is_array($player->position)
                    ? implode(', ', array_filter($player->position))
                    : $player->position,
                'year' => $player->year,
                'height' => $player->height,
                'weight' => $player->weight,
                'gpa' => $player->gpa,
                'city' => $player->city,
                'state' => $player->state,
                'school' => $player->school?->name,

                'club_id' => $club->id,
                'club_name' => $club->name,
                'team_id' => $team->id,
                'team_name' => $team->name,

                'coach_email' => $coachEmail,
                'coach_name' => $coachCheckIn['name'] ?? '',
                'coach_school' => $coachCheckIn['school'] ?? '',
                'coach_title' => $coachCheckIn['title'] ?? '',

                'saved_at' => now()->toDateTimeString(),
            ]);
        }

        session(['coach_saved_players' => $savedPlayers->values()->all()]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $alreadySaved ? 'Player already saved.' : 'Player saved.',
                'saved_count' => $savedPlayers->count(),
                'player_id' => $player->id,
            ]);
        }

        return back()->with(
            'player_save_success',
            $alreadySaved ? 'Player already saved.' : 'Player saved.'
        );
    }

    public function unsavePlayer(
        Request $request,
        string $clubSlug,
        string $gender,
        string $teamSlug,
        User $player
    ): RedirectResponse|JsonResponse {
        $club = Club::query()
            ->where('landing_page_slug', $clubSlug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $team = Team::query()
            ->where('club_id', $club->id)
            ->where('landing_page_slug', $teamSlug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $requestedGender = $this->normalizeLandingGenderSegment($gender);
        $teamGender = $this->normalizeLandingGenderSegment($team->landingGenderSegment());

        abort_unless($teamGender === $requestedGender, 404);

        $coachCheckIn = session('coach_checkin');
        $coachEmail = strtolower((string) ($coachCheckIn['email'] ?? ''));

        $savedPlayers = collect(session('coach_saved_players', []))
            ->reject(function ($saved) use ($player, $club, $team, $coachEmail) {
                return (int) ($saved['player_id'] ?? 0) === (int) $player->id
                    && (int) ($saved['club_id'] ?? 0) === (int) $club->id
                    && (int) ($saved['team_id'] ?? 0) === (int) $team->id
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

    protected function playersForTeam(Team $team, Club $club)
    {
        /*
        |--------------------------------------------------------------------------
        | Squad Lookup
        |--------------------------------------------------------------------------
        |
        | Your current app stores the player's selected team in users.team_name.
        | This stays compatible without requiring a team_id column on users.
        |
        | Players are sorted by jersey number first by default.
        |
        */

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
            ->where(function ($query) use ($team, $club) {
                $query
                    ->where(function ($inner) use ($team, $club) {
                        $inner
                            ->where('club_id', $club->id)
                            ->where('team_name', $team->name);
                    })
                    ->orWhere(function ($inner) use ($team) {
                        $inner->where('team_name', $team->name);
                    });
            })
            ->get()
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

    protected function playerBelongsToTeam(User $player, Team $team, Club $club): bool
    {
        $playerTeamName = trim((string) ($player->team_name ?? ''));
        $teamName = trim((string) ($team->name ?? ''));

        if ($playerTeamName === '' || $teamName === '') {
            return false;
        }

        return (
            (int) ($player->club_id ?? 0) === (int) $club->id
            && strcasecmp($playerTeamName, $teamName) === 0
        )
        || strcasecmp($playerTeamName, $teamName) === 0;
    }
}