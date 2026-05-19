<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\Team;
use Illuminate\Console\Command;

class BackfillClubTeamLandingSlugs extends Command
{
    protected $signature = 'plyrcard:backfill-club-team-landing-slugs';

    protected $description = 'Backfill landing page slugs for existing clubs and teams.';

    public function handle(): int
    {
        $clubsUpdated = 0;
        $teamsUpdated = 0;

        Club::query()
            ->where(function ($query) {
                $query
                    ->whereNull('landing_page_slug')
                    ->orWhere('landing_page_slug', '');
            })
            ->orderBy('id')
            ->get()
            ->each(function (Club $club) use (&$clubsUpdated) {
                if (blank($club->name)) {
                    return;
                }

                $club->landing_page_slug = Club::uniqueLandingPageSlug($club->name, $club);
                $club->save();

                $clubsUpdated++;
            });

        Team::query()
            ->where(function ($query) {
                $query
                    ->whereNull('landing_page_slug')
                    ->orWhere('landing_page_slug', '');
            })
            ->orderBy('id')
            ->get()
            ->each(function (Team $team) use (&$teamsUpdated) {
                if (blank($team->name)) {
                    return;
                }

                $team->landing_page_slug = Team::uniqueLandingPageSlug($team->name, $team);
                $team->save();

                $teamsUpdated++;
            });

        $this->info("Clubs updated: {$clubsUpdated}");
        $this->info("Teams updated: {$teamsUpdated}");

        return self::SUCCESS;
    }
}
