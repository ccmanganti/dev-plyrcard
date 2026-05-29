<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\ClubLeague;
use App\Models\League;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BackfillClubLeagueStructure extends Command
{
    protected $signature = 'plyrcard:backfill-club-league-structure
        {--dry-run : Preview changes without saving}
        {--club-id= : Only process the duplicate club group containing this club ID}
        {--sync-users : Remap users to canonical club_id and club_league_id}
        {--disable-duplicates : Disable duplicate club landing pages after linking them to the canonical club}';

    protected $description = 'Safely backfill the new ClubLeague structure from duplicate club/league rows without deleting data.';

    public function handle(): int
    {
        $missing = $this->missingRequiredColumns();

        if ($missing !== []) {
            foreach ($missing as $column) {
                $this->error("Missing required column/table: {$column}");
            }

            $this->error('Run the new migration first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $syncUsers = (bool) $this->option('sync-users');
        $disableDuplicates = (bool) $this->option('disable-duplicates');

        $this->info($dryRun ? 'DRY RUN: no changes will be saved.' : 'Applying club-league backfill.');

        $clubs = $this->targetClubs();

        if ($clubs->isEmpty()) {
            $this->warn('No clubs found.');
            return self::SUCCESS;
        }

        $groups = $clubs->groupBy(fn (Club $club) => $this->normalizedClubName($club->name));

        $processedGroups = 0;
        $clubLeaguesTouched = 0;
        $duplicateClubsLinked = 0;
        $usersSynced = 0;

        foreach ($groups as $normalizedName => $group) {
            if ($group->isEmpty()) {
                continue;
            }

            $canonical = $this->chooseCanonicalClub($group);
            $duplicates = $group->where('id', '!=', $canonical->id)->values();
            $allClubIds = $group->pluck('id')->values();

            $this->line('');
            $this->info("Club group: {$normalizedName}");
            $this->line("Canonical: #{$canonical->id} {$canonical->name}");
            $this->line('Club IDs: ' . $allClubIds->implode(', '));

            DB::transaction(function () use (
                $dryRun,
                $syncUsers,
                $disableDuplicates,
                $canonical,
                $duplicates,
                $group,
                &$clubLeaguesTouched,
                &$duplicateClubsLinked,
                &$usersSynced
            ) {
                if (! $dryRun) {
                    $canonical->forceFill([
                        'canonical_club_id' => null,
                        'has_landing_page' => $canonical->has_landing_page ?: true,
                        'landing_page_is_published' => $canonical->landing_page_is_published ?: true,
                        'landing_page_slug' => $canonical->landing_page_slug ?: Club::uniqueLandingPageSlug($canonical->name, $canonical),
                    ])->save();
                }

                foreach ($duplicates as $duplicate) {
                    $updates = ['canonical_club_id' => $canonical->id];

                    if ($disableDuplicates) {
                        $updates['legacy_landing_page_slug'] = $duplicate->legacy_landing_page_slug ?: $duplicate->landing_page_slug;
                        $updates['landing_page_slug'] = null;
                        $updates['has_landing_page'] = false;
                        $updates['landing_page_is_published'] = false;
                    }

                    $this->line("Duplicate linked: #{$duplicate->id} {$duplicate->name} -> #{$canonical->id}");

                    if (! $dryRun) {
                        $duplicate->forceFill($updates)->save();
                    }

                    $duplicateClubsLinked++;
                }

                $byLeague = $group
                    ->filter(fn (Club $club) => filled($club->league_id))
                    ->groupBy(fn (Club $club) => (int) $club->league_id);

                foreach ($byLeague as $leagueId => $clubsForLeague) {
                    /** @var League|null $league */
                    $league = League::query()->find((int) $leagueId);

                    if (! $league) {
                        $this->warn("League #{$leagueId} was not found. Skipping.");
                        continue;
                    }

                    $genders = $this->gendersForLeagueAndClubs($league, $clubsForLeague);
                    $legacyClubIds = $clubsForLeague->pluck('id')->unique()->values()->all();

                    $this->line("ClubLeague: #{$canonical->id} {$canonical->name} + #{$league->id} {$league->name}");
                    $this->line('  Genders: ' . implode(', ', $genders));
                    $this->line('  Legacy club IDs: ' . implode(', ', $legacyClubIds));

                    $clubLeague = ClubLeague::query()
                        ->where('club_id', $canonical->id)
                        ->where('league_id', $league->id)
                        ->first();

                    $mergedLegacyClubIds = collect($clubLeague?->legacy_club_ids ?? [])
                        ->merge($legacyClubIds)
                        ->filter()
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values()
                        ->all();

                    $mergedGenders = collect($clubLeague?->genders ?? [])
                        ->merge($genders)
                        ->map(fn ($gender) => ClubLeague::normalizeGender($gender))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();

                    if (! $dryRun) {
                        $clubLeague = ClubLeague::query()->updateOrCreate(
                            [
                                'club_id' => $canonical->id,
                                'league_id' => $league->id,
                            ],
                            [
                                'genders' => $mergedGenders,
                                'sport' => $league->sport,
                                'is_active' => true,
                                'legacy_club_ids' => $mergedLegacyClubIds,
                            ]
                        );

                        $league->forceFill([
                            'genders' => collect($league->genders ?? [])
                                ->merge($mergedGenders)
                                ->map(fn ($gender) => ClubLeague::normalizeGender($gender))
                                ->filter()
                                ->unique()
                                ->values()
                                ->all(),
                        ])->save();
                    }

                    $clubLeaguesTouched++;

                    if ($syncUsers) {
                        $users = User::query()
                            ->whereIn('club_id', $legacyClubIds)
                            ->where(function ($query) use ($league) {
                                $query->where('league_id', $league->id)
                                    ->orWhereNull('league_id');
                            })
                            ->orderBy('id')
                            ->get();

                        $this->line("  Users to sync: {$users->count()}");

                        foreach ($users as $user) {
                            $changes = [
                                'legacy_club_id' => $user->legacy_club_id ?: $user->club_id,
                                'legacy_league_id' => $user->legacy_league_id ?: $user->league_id,
                                'legacy_team_name' => $user->legacy_team_name ?: $user->team_name,
                                'club_id' => $canonical->id,
                                'league_id' => $league->id,
                                'club_league_id' => $clubLeague?->id,
                            ];

                            $this->line('    User #' . $user->id . ' ' . trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) . ' -> ' . json_encode($changes));

                            if (! $dryRun) {
                                $user->forceFill($changes)->save();
                            }

                            $usersSynced++;
                        }
                    }
                }

                if ($dryRun) {
                    DB::rollBack();
                }
            });

            $processedGroups++;
        }

        $this->line('');
        $this->info($dryRun ? 'Dry run complete.' : 'Backfill complete.');
        $this->line("Club groups processed: {$processedGroups}");
        $this->line("ClubLeague records touched: {$clubLeaguesTouched}");
        $this->line("Duplicate clubs linked: {$duplicateClubsLinked}");
        $this->line("Users synced: {$usersSynced}");

        return self::SUCCESS;
    }

    protected function missingRequiredColumns(): array
    {
        $required = [
            'club_leagues table' => Schema::hasTable('club_leagues'),
            'leagues.genders' => Schema::hasColumn('leagues', 'genders'),
            'clubs.canonical_club_id' => Schema::hasColumn('clubs', 'canonical_club_id'),
            'clubs.legacy_landing_page_slug' => Schema::hasColumn('clubs', 'legacy_landing_page_slug'),
            'users.club_league_id' => Schema::hasColumn('users', 'club_league_id'),
            'users.legacy_club_id' => Schema::hasColumn('users', 'legacy_club_id'),
            'users.legacy_league_id' => Schema::hasColumn('users', 'legacy_league_id'),
            'users.legacy_team_name' => Schema::hasColumn('users', 'legacy_team_name'),
        ];

        return collect($required)
            ->filter(fn (bool $exists) => ! $exists)
            ->keys()
            ->values()
            ->all();
    }

    protected function targetClubs(): EloquentCollection
    {
        $query = Club::query()->with('league')->orderBy('name')->orderBy('id');

        if ($this->option('club-id')) {
            $seed = Club::query()->findOrFail((int) $this->option('club-id'));
            $normalized = $this->normalizedClubName($seed->name);

            return Club::query()
                ->with('league')
                ->get()
                ->filter(fn (Club $club) => $this->normalizedClubName($club->name) === $normalized)
                ->values();
        }

        return $query->get();
    }

    protected function chooseCanonicalClub(EloquentCollection|\Illuminate\Support\Collection $group): Club
    {
        return $group
            ->sortByDesc(function (Club $club) {
                return ($club->landing_page_slug && ! preg_match('/-\d+$/', (string) $club->landing_page_slug) ? 100 : 0)
                    + ($club->has_landing_page ? 20 : 0)
                    + ($club->landing_page_is_published ? 20 : 0)
                    + (blank($club->canonical_club_id) ? 5 : 0)
                    - (int) $club->id / 1000000;
            })
            ->first();
    }

    protected function gendersForLeagueAndClubs(League $league, \Illuminate\Support\Collection $clubs): array
    {
        $fromLeague = collect($league->genders ?? [])
            ->push($league->gender)
            ->map(fn ($gender) => ClubLeague::normalizeGender($gender))
            ->filter();

        $fromClubNames = $clubs
            ->map(fn (Club $club) => ClubLeague::normalizeGender($club->name . ' ' . ($club->league?->name ?? '')))
            ->filter();

        $genders = $fromLeague
            ->merge($fromClubNames)
            ->unique()
            ->values();

        return $genders->isNotEmpty() ? $genders->all() : ['coed'];
    }

    protected function normalizedClubName(?string $name): string
    {
        return Str::of((string) $name)
            ->lower()
            ->replace(['.', ',', '&'], ['', '', 'and'])
            ->replaceMatches('/\b(fc|sc|soccer club|football club|club)\b/', '')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }
}
