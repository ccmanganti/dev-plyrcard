<?php

namespace App\Console\Commands;

use App\Models\ClubLeague;
use App\Models\League;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ConsolidateLeagues extends Command
{
    protected $signature = 'plyrcard:consolidate-leagues
        {--dry-run : Preview changes without saving}
        {--sync-users : Update users.league_id and users.club_league_id to canonical records}
        {--league-id= : Consolidate only the group containing this league ID}';

    protected $description = 'Merge duplicate gender-specific league rows into canonical multi-gender league records safely.';

    public function handle(): int
    {
        $missing = $this->missingColumns();

        if ($missing !== []) {
            foreach ($missing as $column) {
                $this->error("Missing required column: {$column}");
            }

            $this->error('Run migrations first.');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $syncUsers = (bool) $this->option('sync-users');

        $this->info($dryRun ? 'DRY RUN: no changes will be saved.' : 'Applying league consolidation.');
        $this->line($syncUsers ? 'User sync: ON' : 'User sync: OFF');

        $leagues = $this->targetLeagues();

        if ($leagues->isEmpty()) {
            $this->warn('No leagues found.');
            return self::SUCCESS;
        }

        $groups = $leagues->groupBy(fn (League $league) => $this->leagueGroupKey($league));

        $processedGroups = 0;
        $duplicateLeagues = 0;
        $clubLeagueRowsUpdated = 0;
        $usersUpdated = 0;

        DB::transaction(function () use (
            $groups,
            $dryRun,
            $syncUsers,
            &$processedGroups,
            &$duplicateLeagues,
            &$clubLeagueRowsUpdated,
            &$usersUpdated
        ) {
            foreach ($groups as $groupKey => $group) {
                $group = $group->values();

                if ($group->isEmpty()) {
                    continue;
                }

                $canonical = $this->chooseCanonicalLeague($group);
                $duplicates = $group->where('id', '!=', $canonical->id)->values();
                $groupLeagueIds = $group->pluck('id')->values()->all();
                $mergedGenders = $this->mergedLeagueGenders($group);

                $this->line('');
                $this->info("League group: {$groupKey}");
                $this->line("Canonical league: #{$canonical->id} {$canonical->name}");
                $this->line('All league IDs: ' . implode(', ', $groupLeagueIds));
                $this->line('Merged genders: ' . json_encode($mergedGenders));

                $processedGroups++;

                if (! $dryRun) {
                    $canonical->forceFill([
                        'canonical_league_id' => null,
                        'legacy_gender' => $canonical->legacy_gender ?: $canonical->gender,
                        'genders' => $mergedGenders,
                    ])->save();
                }

                foreach ($duplicates as $duplicate) {
                    $this->line("Duplicate league: #{$duplicate->id} {$duplicate->name} / {$duplicate->gender}");

                    if (! $dryRun) {
                        $duplicate->forceFill([
                            'canonical_league_id' => $canonical->id,
                            'legacy_gender' => $duplicate->legacy_gender ?: $duplicate->gender,
                            'genders' => $this->mergedLeagueGenders(collect([$duplicate])),
                        ])->save();
                    }

                    $duplicateLeagues++;
                }

                $clubLeagueRows = ClubLeague::query()
                    ->with(['club', 'league'])
                    ->whereIn('league_id', $groupLeagueIds)
                    ->orderBy('club_id')
                    ->orderBy('id')
                    ->get();

                $clubLeagueRows
                    ->groupBy('club_id')
                    ->each(function (Collection $rows) use (
                        $canonical,
                        $groupLeagueIds,
                        $mergedGenders,
                        $dryRun,
                        $syncUsers,
                        &$clubLeagueRowsUpdated,
                        &$usersUpdated
                    ) {
                        $rows = $rows->values();

                        $canonicalRow = $this->chooseCanonicalClubLeague($rows, $canonical->id);
                        $duplicateRows = $rows->where('id', '!=', $canonicalRow->id)->values();

                        $rowGenders = $this->mergedClubLeagueGenders($rows, $mergedGenders);
                        $legacyClubIds = $this->mergeArrayValues($rows->pluck('legacy_club_ids')->all());
                        $legacyLeagueIds = collect($groupLeagueIds)
                            ->merge($this->mergeArrayValues($rows->pluck('legacy_league_ids')->all()))
                            ->unique()
                            ->values()
                            ->all();

                        $this->line("  ClubLeague canonical row #{$canonicalRow->id} for club #{$canonicalRow->club_id}");
                        $this->line('    genders: ' . json_encode($rowGenders));
                        $this->line('    legacy league ids: ' . json_encode($legacyLeagueIds));

                        if (! $dryRun) {
                            $updates = [
                                'canonical_club_league_id' => null,
                                'genders' => $rowGenders,
                                'is_active' => true,
                                'legacy_club_ids' => $legacyClubIds,
                                'legacy_league_ids' => $legacyLeagueIds,
                            ];

                            // Only change league_id if doing so will not collide with the unique
                            // club_leagues.club_id + club_leagues.league_id index.
                            if ((int) $canonicalRow->league_id !== (int) $canonical->id) {
                                $alreadyExists = ClubLeague::query()
                                    ->where('club_id', $canonicalRow->club_id)
                                    ->where('league_id', $canonical->id)
                                    ->whereKeyNot($canonicalRow->id)
                                    ->exists();

                                if (! $alreadyExists) {
                                    $updates['league_id'] = $canonical->id;
                                }
                            }

                            $canonicalRow->forceFill($updates)->save();
                        }

                        $clubLeagueRowsUpdated++;

                        foreach ($duplicateRows as $duplicateRow) {
                            $this->line("    Duplicate club_league row #{$duplicateRow->id} -> canonical #{$canonicalRow->id}");

                            if (! $dryRun) {
                                $duplicateRow->forceFill([
                                    'canonical_club_league_id' => $canonicalRow->id,
                                    'is_active' => false,
                                    'legacy_league_ids' => collect($duplicateRow->legacy_league_ids ?? [])
                                        ->merge([$duplicateRow->league_id])
                                        ->unique()
                                        ->values()
                                        ->all(),
                                ])->save();
                            }

                            $clubLeagueRowsUpdated++;
                        }

                        if ($syncUsers) {
                            $users = User::query()
                                ->where(function ($query) use ($rows, $groupLeagueIds) {
                                    $query
                                        ->whereIn('league_id', $groupLeagueIds)
                                        ->orWhereIn('club_league_id', $rows->pluck('id')->all());
                                })
                                ->get();

                            foreach ($users as $user) {
                                $gender = $this->normalizeGender($user->gender);

                                if ($gender && ! in_array($gender, $rowGenders, true)) {
                                    continue;
                                }

                                $userClubId = $user->club_id;
                                $matchesClub = (int) $userClubId === (int) $canonicalRow->club_id
                                    || in_array((int) $userClubId, array_map('intval', $legacyClubIds), true);

                                if (! $matchesClub) {
                                    continue;
                                }

                                $changes = [
                                    'legacy_league_id' => $user->legacy_league_id ?: $user->league_id,
                                    'legacy_club_league_id' => $user->legacy_club_league_id ?: $user->club_league_id,
                                    'league_id' => $canonical->id,
                                    'club_league_id' => $canonicalRow->id,
                                    'club_id' => $canonicalRow->club_id,
                                ];

                                $this->line("    User #{$user->id} -> " . json_encode($changes));

                                if (! $dryRun) {
                                    $user->forceFill($changes)->save();
                                }

                                $usersUpdated++;
                            }
                        }
                    });
            }

            if ($dryRun) {
                DB::rollBack();
            }
        });

        $this->line('');
        $this->info($dryRun ? 'Dry run complete.' : 'League consolidation complete.');
        $this->line("League groups processed: {$processedGroups}");
        $this->line("Duplicate leagues marked: {$duplicateLeagues}");
        $this->line("ClubLeague rows touched: {$clubLeagueRowsUpdated}");
        $this->line("Users updated: {$usersUpdated}");

        return self::SUCCESS;
    }

    protected function missingColumns(): array
    {
        $columns = [
            'leagues.genders' => Schema::hasColumn('leagues', 'genders'),
            'leagues.canonical_league_id' => Schema::hasColumn('leagues', 'canonical_league_id'),
            'leagues.legacy_gender' => Schema::hasColumn('leagues', 'legacy_gender'),
            'club_leagues.canonical_club_league_id' => Schema::hasColumn('club_leagues', 'canonical_club_league_id'),
            'club_leagues.legacy_league_ids' => Schema::hasColumn('club_leagues', 'legacy_league_ids'),
            'users.club_league_id' => Schema::hasColumn('users', 'club_league_id'),
            'users.legacy_league_id' => Schema::hasColumn('users', 'legacy_league_id'),
            'users.legacy_club_league_id' => Schema::hasColumn('users', 'legacy_club_league_id'),
        ];

        return collect($columns)
            ->filter(fn (bool $exists) => ! $exists)
            ->keys()
            ->values()
            ->all();
    }

    protected function targetLeagues(): Collection
    {
        if ($this->option('league-id')) {
            $seed = League::query()->findOrFail((int) $this->option('league-id'));
            $key = $this->leagueGroupKey($seed);

            return League::query()
                ->get()
                ->filter(fn (League $league) => $this->leagueGroupKey($league) === $key)
                ->values();
        }

        return League::query()->get();
    }

    protected function chooseCanonicalLeague(Collection $group): League
    {
        return $group
            ->sortBy([
                fn (League $league) => $league->canonical_league_id ? 1 : 0,
                fn (League $league) => $this->normalizeGender($league->gender) === 'female' ? 0 : 1,
                fn (League $league) => $league->id,
            ])
            ->first();
    }

    protected function chooseCanonicalClubLeague(Collection $rows, int $canonicalLeagueId): ClubLeague
{
    $existingCanonicalLeagueRow = $rows
        ->filter(fn (ClubLeague $row) => (int) $row->league_id === (int) $canonicalLeagueId)
        ->sortBy('id')
        ->first();

    if ($existingCanonicalLeagueRow) {
        return $existingCanonicalLeagueRow;
    }

    return $rows
        ->sortBy([
            fn (ClubLeague $row) => $row->canonical_club_league_id ? 1 : 0,
            fn (ClubLeague $row) => $row->id,
        ])
        ->first();
}

    protected function mergedLeagueGenders(Collection $leagues): array
    {
        return $leagues
            ->flatMap(function (League $league) {
                return collect($league->genders ?? [])
                    ->merge([$league->gender, $league->legacy_gender])
                    ->map(fn ($gender) => $this->normalizeGender($gender))
                    ->filter();
            })
            ->unique()
            ->values()
            ->all();
    }

    protected function mergedClubLeagueGenders(Collection $rows, array $fallback = []): array
    {
        return $rows
            ->flatMap(function (ClubLeague $row) {
                return collect($row->genders ?? [])
                    ->merge($row->league ? [$row->league->gender, $row->league->legacy_gender] : [])
                    ->map(fn ($gender) => $this->normalizeGender($gender))
                    ->filter();
            })
            ->merge($fallback)
            ->unique()
            ->values()
            ->all();
    }

    protected function mergeArrayValues(array $arrays): array
    {
        return collect($arrays)
            ->flatMap(function ($value) {
                if (is_array($value)) {
                    return $value;
                }

                if (is_string($value) && $value !== '') {
                    $decoded = json_decode($value, true);

                    if (is_array($decoded)) {
                        return $decoded;
                    }

                    return [$value];
                }

                return [];
            })
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => is_numeric($value) ? (int) $value : $value)
            ->unique()
            ->values()
            ->all();
    }

    protected function leagueGroupKey(League $league): string
    {
        return strtolower((string) $league->sport) . '|' . $this->normalizedLeagueName($league->name);
    }

    protected function normalizedLeagueName(?string $name): string
    {
        return Str::of((string) $name)
            ->lower()
            ->replace(['.', ',', '&'], ['', '', 'and'])
            ->replaceMatches('/\b(girls|girl|female|women|woman|boys|boy|male|men|man)\b/', '')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }

    protected function normalizeGender(?string $gender): ?string
    {
        $gender = strtolower(trim((string) $gender));

        if ($gender === '') {
            return null;
        }

        if (in_array($gender, ['female', 'girls', 'girl', 'women', 'woman', 'womens'], true)) {
            return 'female';
        }

        if (in_array($gender, ['male', 'boys', 'boy', 'men', 'man', 'mens'], true)) {
            return 'male';
        }

        return $gender;
    }
}