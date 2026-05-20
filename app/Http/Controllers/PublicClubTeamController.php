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

        abort_unless($this->normalizeGenderSegment($team->landingGenderSegment()) === $this->normalizeGenderSegment($gender), 404);

        $players = $this->playersForTeam($team, $club);

        return view('public.team-landing', [
            'team' => $team,
            'club' => $club,
            'players' => $players,
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
            'gender' => $this->normalizeGenderSegment($team->landingGenderSegment()),
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
            'title' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:190'],
        ]);

        session([
            $this->coachSessionKey($club) => [
                'school' => $validated['school'],
                'name' => $validated['name'],
                'title' => $validated['title'],
                'email' => strtolower($validated['email']),
                'checked_in_at' => now()->toISOString(),
            ],
        ]);

        return back()->with('status', 'Coach check-in complete.');
    }

    public function savePlayerForCoach(Request $request, string $clubSlug): JsonResponse
    {
        $club = Club::query()
            ->where('landing_page_slug', $clubSlug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $coachSession = session($this->coachSessionKey($club));

        if (! $coachSession) {
            return response()->json([
                'success' => false,
                'message' => 'Please check in as a coach before saving players.',
            ], 403);
        }

        $validated = $request->validate([
            'player_id' => ['required', 'integer'],
        ]);

        $player = User::query()
            ->with(['websites' => function ($query) {
                $query
                    ->where('is_active', true)
                    ->where('is_published', true)
                    ->latest('updated_at');
            }])
            ->whereKey($validated['player_id'])
            ->where('club_id', $club->id)
            ->firstOrFail();

        $website = $player->websites->first();
        $playerUrl = $website
            ? (filled($website->domain)
                ? 'https://' . preg_replace('/^https?:\/\//', '', $website->domain)
                : url('/' . ltrim($website->slug, '/')))
            : url()->previous();

        $savedPlayersKey = $this->savedPlayersKey($club);
        $savedPlayers = collect(session($savedPlayersKey, []));

        $payload = [
            'player_id' => $player->id,
            'player_name' => trim(($player->first_name ?? '') . ' ' . ($player->last_name ?? '')) ?: 'Player',
            'player_email' => $player->email ?: $player->personal_email,
            'player_phone' => $player->phone,
            'player_url' => $playerUrl,
            'coach_email' => $coachSession['email'] ?? null,
            'saved_at' => now()->toISOString(),
        ];

        $savedPlayers = $savedPlayers
            ->reject(fn ($item) => (int) ($item['player_id'] ?? 0) === (int) $player->id)
            ->push($payload)
            ->values();

        session([$savedPlayersKey => $savedPlayers->all()]);

        /*
        |--------------------------------------------------------------------------
        | Optional future database persistence
        |--------------------------------------------------------------------------
        | If you create a coach_player_saves table later, this is where you should
        | upsert the save event using $coachSession['email'] and $player->id.
        */

        return response()->json([
            'success' => true,
            'message' => 'Player saved.',
            'saved_player' => $payload,
        ]);
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
        | Added eager loads:
        | - roles: needed for Free vs Plyr Plus/My Journey display logic.
        | - school, league, nationalTeam, club: needed for player detail card.
        | - active published websites: optional Visit Website action.
        */

        return User::query()
            ->with([
                'roles',
                'school',
                'league',
                'nationalTeam',
                'club.league',
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
            ->orderByRaw('CASE WHEN jersey_number REGEXP "^[0-9]+$" THEN 0 ELSE 1 END')
            ->orderByRaw('CAST(jersey_number AS UNSIGNED)')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    protected function normalizeGenderSegment(string $segment): string
    {
        $segment = strtolower(trim($segment));

        return match ($segment) {
            'womens', 'women', 'girls', 'girl', 'female' => 'girls',
            default => 'boys',
        };
    }

    protected function coachSessionKey(Club $club): string
    {
        return 'club_coach_checkin_' . $club->id;
    }

    protected function savedPlayersKey(Club $club): string
    {
        return 'club_saved_players_' . $club->id;
    }
}