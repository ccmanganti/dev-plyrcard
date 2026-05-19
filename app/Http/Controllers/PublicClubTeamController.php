<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
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

        /*
        |--------------------------------------------------------------------------
        | Gender Segment Guard
        |--------------------------------------------------------------------------
        |
        | This makes sure a women's team does not load from /mens/slug,
        | and a men's team does not load from /womens/slug.
        |
        */

        abort_unless($team->landingGenderSegment() === $gender, 404);

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
            'gender' => $team->landingGenderSegment(),
            'teamSlug' => $team->landing_page_slug,
        ], 301);
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
        */

        return User::query()
            ->with(['websites' => function ($query) {
                $query
                    ->where('is_active', true)
                    ->where('is_published', true)
                    ->latest('updated_at');
            }])
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
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }
}