<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\Conference;
use App\Models\League;
use App\Models\Team;
use Illuminate\Console\Command;

class ImportCombinedLeagueClubTeamData extends Command
{
    protected $signature = 'import:combined-sports {file}';
    protected $description = 'Import leagues, conferences, clubs, and standard teams from one combined CSV';

    public function handle(): int
    {
        $file = $this->argument('file');

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $handle = fopen($file, 'r');

        if (! $handle) {
            $this->error("Unable to open file: {$file}");
            return self::FAILURE;
        }

        $header = fgetcsv($handle);

        if (! $header) {
            fclose($handle);
            $this->error('CSV appears to be empty.');
            return self::FAILURE;
        }

        $header = array_map('trim', $header);

        $expected = [
            'source',
            'league',
            'gender',
            'conference_name',
            'club_name',
            'team_name',
            'city',
            'state',
            'logo_filename',
            'import_scope',
        ];

        if ($header !== $expected) {
            fclose($handle);
            $this->error('CSV headers do not match expected format.');
            $this->line('Expected: ' . implode(', ', $expected));
            $this->line('Actual:   ' . implode(', ', $header));
            return self::FAILURE;
        }

        $defaultTeams = ['U13', 'U14', 'U15', 'U16', 'U17', 'U18', 'U19'];

        $leagueCount = 0;
        $conferenceCount = 0;
        $clubCount = 0;
        $teamCount = 0;
        $rowCount = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $rowCount++;

            [
                $source,
                $leagueName,
                $gender,
                $conferenceName,
                $clubName,
                $csvTeamName,
                $city,
                $state,
                $logoFilename,
                $importScope,
            ] = array_map(
                fn ($value) => is_string($value) ? trim($value) : $value,
                $row
            );

            $league = League::firstOrCreate(
                [
                    'name' => $leagueName,
                    'gender' => $gender,
                ],
                [
                    'logo' => null,
                ]
            );

            if ($league->wasRecentlyCreated) {
                $leagueCount++;
            }

            $conference = null;

            if (filled($conferenceName)) {
                $conference = Conference::firstOrCreate([
                    'name' => $conferenceName,
                ]);

                if ($conference->wasRecentlyCreated) {
                    $conferenceCount++;
                }
            }

            $club = Club::firstOrCreate(
                [
                    'league_id' => $league->id,
                    'conference_id' => $conference?->id,
                    'name' => $clubName,
                ],
                [
                    'logo' => filled($logoFilename) ? 'club-logos/' . $logoFilename : null,
                    'city' => filled($city) ? $city : null,
                    'state' => filled($state) ? $state : null,
                ]
            );

            $clubUpdates = [];

            if (blank($club->logo) && filled($logoFilename)) {
                $clubUpdates['logo'] = 'club-logos/' . $logoFilename;
            }

            if (blank($club->city) && filled($city)) {
                $clubUpdates['city'] = $city;
            }

            if (blank($club->state) && filled($state)) {
                $clubUpdates['state'] = $state;
            }

            if ($club->conference_id !== $conference?->id) {
                $clubUpdates['conference_id'] = $conference?->id;
            }

            if (! empty($clubUpdates)) {
                $club->update($clubUpdates);
            }

            if ($club->wasRecentlyCreated) {
                $clubCount++;
            }

            foreach ($defaultTeams as $teamName) {
                $team = Team::firstOrCreate(
                    [
                        'club_id' => $club->id,
                        'name' => $teamName,
                    ],
                    [
                        'logo' => $club->logo,
                    ]
                );

                if (! $team->wasRecentlyCreated && blank($team->logo) && filled($club->logo)) {
                    $team->update([
                        'logo' => $club->logo,
                    ]);
                }

                if ($team->wasRecentlyCreated) {
                    $teamCount++;
                }
            }
        }

        fclose($handle);

        $this->info("Processed {$rowCount} rows.");
        $this->info("Leagues created: {$leagueCount}");
        $this->info("Conferences created: {$conferenceCount}");
        $this->info("Clubs created: {$clubCount}");
        $this->info("Teams created: {$teamCount}");

        return self::SUCCESS;
    }
}
