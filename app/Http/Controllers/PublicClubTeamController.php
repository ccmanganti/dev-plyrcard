<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicClubTeamController extends Controller
{
    public function club(string $slug): View
    {
        $club = Club::query()
            ->with(['league', 'teams'])
            ->where('landing_page_slug', $slug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $teams = $club->teams()
            ->orderBy('name')
            ->get();

        return view('public.club-landing', [
            'club' => $club,
            'teams' => $teams,
        ]);
    }

    public function team(string $slug): View
    {
        $team = Team::query()
            ->with(['club.league'])
            ->where('landing_page_slug', $slug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $club = $team->club;

        /*
        |--------------------------------------------------------------------------
        | Squad Lookup
        |--------------------------------------------------------------------------
        | Your current UserResource stores the selected team name in users.team_name.
        | This keeps it compatible without requiring a team_id column on users.
        */
        $players = User::query()
            ->with(['websites' => function ($query) {
                $query
                    ->where('is_active', true)
                    ->where('is_published', true)
                    ->latest('updated_at');
            }])
            ->where(function ($query) use ($team, $club) {
                $query
                    ->where('team_name', $team->name)
                    ->when($club, fn ($q) => $q->orWhere(function ($inner) use ($team, $club) {
                        $inner
                            ->where('club_id', $club->id)
                            ->where('team_name', $team->name);
                    }));
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('public.team-landing', [
            'team' => $team,
            'club' => $club,
            'players' => $players,
        ]);
    }
}
