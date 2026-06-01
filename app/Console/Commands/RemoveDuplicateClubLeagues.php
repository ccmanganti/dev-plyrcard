<?php

namespace App\Console\Commands;

use App\Models\ClubLeague;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemoveDuplicateClubLeagues extends Command
{
    protected $signature = 'plyrcard:remove-duplicate-club-leagues
        {--dry-run : Show duplicates without changing data}
        {--soft-delete : Soft delete duplicates instead of permanently deleting them}';

    protected $description = 'Remove duplicate club_leagues rows created during league/club consolidation.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $softDelete = (bool) $this->option('soft-delete');

        $programs = ClubLeague::query()
            ->withTrashed()
            ->with('league')
            ->orderBy('club_id')
            ->orderBy('league_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (ClubLeague $program): string => $this->dedupeKey($program));

        $duplicateGroups = $programs
            ->filter(fn ($group): bool => $group->count() > 1);

        if ($duplicateGroups->isEmpty()) {
            $this->info('No duplicate club_leagues found.');

            return self::SUCCESS;
        }

        $this->warn('Duplicate club_leagues groups found: ' . $duplicateGroups->count());

        $duplicatesToRemove = collect();

        foreach ($duplicateGroups as $key => $group) {
            $active = $group
                ->filter(fn (ClubLeague $program): bool => blank($program->deleted_at))
                ->sortBy([
                    fn (ClubLeague $program) => $program->sort_order ?? 0,
                    fn (ClubLeague $program) => $program->id,
                ])
                ->values();

            $sorted = ($active->isNotEmpty() ? $active : $group)
                ->sortBy([
                    fn (ClubLeague $program) => $program->sort_order ?? 0,
                    fn (ClubLeague $program) => $program->id,
                ])
                ->values();

            $keep = $sorted->first();
            $remove = $group
                ->where('id', '!=', $keep->id)
                ->values();

            $duplicatesToRemove = $duplicatesToRemove->merge($remove);

            $this->line('');
            $this->line('Key: ' . $key);
            $this->line('Keeping ID: ' . $keep->id . ' (' . ($keep->league?->name ?? 'League #' . $keep->league_id) . ')');
            $this->line('Removing IDs: ' . $remove->pluck('id')->implode(', '));
        }

        if ($dryRun) {
            $this->warn('Dry run only. No rows were changed.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($duplicateGroups, $softDelete): void {
            foreach ($duplicateGroups as $group) {
                $active = $group
                    ->filter(fn (ClubLeague $program): bool => blank($program->deleted_at))
                    ->sortBy([
                        fn (ClubLeague $program) => $program->sort_order ?? 0,
                        fn (ClubLeague $program) => $program->id,
                    ])
                    ->values();

                $sorted = ($active->isNotEmpty() ? $active : $group)
                    ->sortBy([
                        fn (ClubLeague $program) => $program->sort_order ?? 0,
                        fn (ClubLeague $program) => $program->id,
                    ])
                    ->values();

                $keep = $sorted->first();

                $remove = $group
                    ->where('id', '!=', $keep->id)
                    ->values();

                foreach ($remove as $duplicate) {
                    User::query()
                        ->where('club_league_id', $duplicate->id)
                        ->update([
                            'club_league_id' => $keep->id,
                            'league_id' => $keep->league_id,
                            'club_id' => $keep->club_id,
                        ]);

                    if ($softDelete) {
                        $duplicate->delete();
                    } else {
                        $duplicate->forceDelete();
                    }
                }
            }
        });

        $this->info(($softDelete ? 'Soft deleted ' : 'Permanently deleted ') . $duplicatesToRemove->count() . ' duplicate club_leagues rows.');

        return self::SUCCESS;
    }

    protected function dedupeKey(ClubLeague $program): string
    {
        $genders = collect($program->genders ?? [])
            ->map(fn ($gender) => $this->normalizeGender($gender))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->implode(',');

        return implode('|', [
            (int) $program->club_id,
            (int) $program->league_id,
            strtolower(trim((string) $program->sport)),
            $genders,
        ]);
    }

    protected function normalizeGender(?string $value): ?string
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
}
