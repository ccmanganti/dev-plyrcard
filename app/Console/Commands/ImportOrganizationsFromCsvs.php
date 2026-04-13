<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\Conference;
use App\Models\League;
use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportOrganizationsFromCsvs extends Command
{
    protected $signature = 'import:organizations';
    protected $description = 'Import leagues, conferences, clubs, and teams from multiple CSV files';

    protected array $logoLookup = [];

    public function handle(): int
    {
        $basePath = storage_path('app/imports');

        $files = [
            'ecnl' => $basePath . '/ECNL_ECNLRL_Teams.csv',
            'mls' => $basePath . '/MLS_Next_Logo_Map.csv',
            'girls_academy' => $basePath . '/Girls_Academy_Logo_Map.csv',
            'aspire' => $basePath . '/Aspire_Logo_Map.csv',
        ];

        foreach ($files as $key => $file) {
            if (! file_exists($file)) {
                $this->error("Missing file for {$key}: {$file}");
                return self::FAILURE;
            }
        }

        $this->buildLogoLookup();

        $teamNames = $this->extractTeamNamesFromEcnl($files['ecnl']);

        if (empty($teamNames)) {
            $this->error('No team names found in ECNL CSV.');
            return self::FAILURE;
        }

        $this->info('Team names from ECNL: ' . implode(', ', $teamNames));

        $leagueCount = 0;
        $conferenceCount = 0;
        $clubCount = 0;
        $teamCount = 0;
        $rowCount = 0;
        $missingLogoFiles = [];

        foreach ($files as $source => $file) {
            $this->info("Processing {$source}: {$file}");

            $handle = fopen($file, 'r');

            if (! $handle) {
                $this->error("Unable to open file: {$file}");
                return self::FAILURE;
            }

            $header = fgetcsv($handle);

            if (! $header) {
                fclose($handle);
                $this->error("CSV is empty: {$file}");
                return self::FAILURE;
            }

            $header = array_map('trim', $header);

            while (($row = fgetcsv($handle)) !== false) {
                $rowCount++;

                $data = $this->mapRowFromHeaders($header, $row);

                if (! $data || blank($data['league']) || blank($data['gender']) || blank($data['club_name'])) {
                    continue;
                }

                $league = League::firstOrCreate(
                    [
                        'name' => $data['league'],
                        'gender' => $data['gender'],
                    ],
                    [
                        'logo' => null,
                    ]
                );

                if ($league->wasRecentlyCreated) {
                    $leagueCount++;
                }

                $conference = null;

                if (filled($data['conference_name'])) {
                    $conference = Conference::firstOrCreate([
                        'name' => $data['conference_name'],
                    ]);

                    if ($conference->wasRecentlyCreated) {
                        $conferenceCount++;
                    }
                }

                $logoPath = null;

                if (filled($data['logo_filename'])) {
                    $logoPath = $this->resolveLogoPath($data['logo_filename']);

                    if (! $logoPath) {
                        $missingLogoFiles[$data['logo_filename']] = true;
                    }
                }

                $club = Club::firstOrCreate(
                    [
                        'league_id' => $league->id,
                        'conference_id' => $conference?->id,
                        'name' => $data['club_name'],
                    ],
                    [
                        'logo' => $logoPath,
                        'city' => filled($data['city']) ? $data['city'] : null,
                        'state' => filled($data['state']) ? $data['state'] : null,
                    ]
                );

                $clubUpdates = [];

                if (filled($logoPath)) {
                    $clubUpdates['logo'] = $logoPath;
                }

                if (blank($club->city) && filled($data['city'])) {
                    $clubUpdates['city'] = $data['city'];
                }

                if (blank($club->state) && filled($data['state'])) {
                    $clubUpdates['state'] = $data['state'];
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

                foreach ($teamNames as $teamName) {
                    $team = Team::firstOrCreate(
                        [
                            'club_id' => $club->id,
                            'name' => $teamName,
                        ],
                        [
                            'logo' => $club->logo,
                        ]
                    );

                    if (filled($club->logo)) {
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
        }

        $this->info("Processed rows: {$rowCount}");
        $this->info("Leagues created: {$leagueCount}");
        $this->info("Conferences created: {$conferenceCount}");
        $this->info("Clubs created: {$clubCount}");
        $this->info("Teams created: {$teamCount}");

        if (! empty($missingLogoFiles)) {
            $this->warn('Missing logo files detected: ' . count($missingLogoFiles));

            foreach (array_slice(array_keys($missingLogoFiles), 0, 50) as $missingLogo) {
                $this->line("Missing: {$missingLogo}");
            }

            if (count($missingLogoFiles) > 50) {
                $this->line('...and more');
            }
        }

        return self::SUCCESS;
    }

    protected function buildLogoLookup(): void
    {
        $files = Storage::disk('public')->files('club-logos');

        $lookup = [];

        foreach ($files as $path) {
            $basename = basename($path);

            $keys = [
                $this->normalizeLogoKey($basename),
                $this->normalizeLogoKey(pathinfo($basename, PATHINFO_FILENAME)),
            ];

            foreach ($keys as $key) {
                if ($key !== '' && ! isset($lookup[$key])) {
                    $lookup[$key] = $path;
                }
            }
        }

        $this->logoLookup = $lookup;
    }

    protected function extractTeamNamesFromEcnl(string $file): array
    {
        $handle = fopen($file, 'r');

        if (! $handle) {
            return [];
        }

        $header = fgetcsv($handle);

        if (! $header) {
            fclose($handle);
            return [];
        }

        $header = array_map('trim', $header);
        $teamNames = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowData = array_combine($header, array_pad($row, count($header), null));

            if (! $rowData) {
                continue;
            }

            $teamName = trim((string) ($rowData['Team Age Group'] ?? ''));

            if ($teamName !== '') {
                $teamNames[$teamName] = true;
            }
        }

        fclose($handle);

        $teamNames = array_keys($teamNames);
        sort($teamNames);

        return $teamNames;
    }

    protected function mapRowFromHeaders(array $header, array $row): ?array
    {
        $row = array_pad($row, count($header), null);
        $rowData = array_combine($header, $row);

        if (! $rowData) {
            return null;
        }

        $trim = fn (string $key): string => trim((string) ($rowData[$key] ?? ''));

        return [
            'league' => $trim('League'),
            'gender' => $trim('Gender'),
            'conference_name' => $trim('Conference/Region'),
            'club_name' => $trim('Club Name'),
            'city' => $trim('City'),
            'state' => $trim('State'),
            'logo_filename' => $trim('Logo Filename'),
        ];
    }

    protected function resolveLogoPath(?string $filename): ?string
    {
        $filename = $this->sanitizeFilename($filename);

        if ($filename === '') {
            return null;
        }

        $keys = [
            $this->normalizeLogoKey($filename),
            $this->normalizeLogoKey(pathinfo($filename, PATHINFO_FILENAME)),
        ];

        foreach ($keys as $key) {
            if ($key !== '' && isset($this->logoLookup[$key])) {
                return $this->logoLookup[$key];
            }
        }

        return null;
    }

    protected function sanitizeFilename(?string $filename): string
    {
        $filename = (string) $filename;
        $filename = preg_replace('/^\xEF\xBB\xBF/', '', $filename) ?? $filename;
        $filename = str_replace("\xC2\xA0", ' ', $filename);

        return trim($filename);
    }

    protected function normalizeLogoKey(string $value): string
    {
        $value = $this->sanitizeFilename($value);
        $value = strtolower($value);
        $value = pathinfo($value, PATHINFO_FILENAME);
        $value = str_replace(['&', '@'], ['and', 'at'], $value);
        $value = preg_replace('/[^a-z0-9]+/', '', $value) ?? $value;

        return $value;
    }
}