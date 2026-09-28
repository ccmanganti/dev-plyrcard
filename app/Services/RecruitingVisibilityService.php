<?php

namespace App\Services;

use App\Models\Coach;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecruitingVisibilityService
{
    public const SCHOOL_TABLE = 'school_user_exclusivity';
    public const COACH_TABLE = 'coach_user_exclusivity';

    public function ready(): bool
    {
        return Schema::hasTable(self::SCHOOL_TABLE)
            && Schema::hasTable(self::COACH_TABLE);
    }

    /**
     * Platform operators should always be able to inspect the complete catalog.
     * When a Superadmin impersonates an athlete, auth()->user() is the athlete,
     * so the athlete's restrictions still apply exactly as they should.
     */
    public function bypassesRestrictions(User $user): bool
    {
        if (! method_exists($user, 'hasAnyRole')) {
            return false;
        }

        return $user->hasAnyRole([
            'Superadmin', 'superadmin', 'Super Admin',
            'Admin', 'admin', 'Administrator',
        ]);
    }

    public function applySchoolQuery(Builder $query, User $user): Builder
    {
        if (! $this->ready() || $this->bypassesRestrictions($user)) {
            return $query;
        }

        $userId = (int) $user->getKey();

        return $query->where(function (Builder $visibility) use ($userId): void {
            $visibility
                ->whereNotExists(function ($subquery): void {
                    $subquery
                        ->selectRaw('1')
                        ->from(self::SCHOOL_TABLE . ' as school_visibility')
                        ->whereColumn('school_visibility.school_id', 'schools.id');
                })
                ->orWhereExists(function ($subquery) use ($userId): void {
                    $subquery
                        ->selectRaw('1')
                        ->from(self::SCHOOL_TABLE . ' as school_visibility')
                        ->whereColumn('school_visibility.school_id', 'schools.id')
                        ->where('school_visibility.user_id', $userId);
                });
        });
    }

    public function applyCoachQuery(Builder $query, User $user): Builder
    {
        if (! $this->ready() || $this->bypassesRestrictions($user)) {
            return $query;
        }

        $userId = (int) $user->getKey();

        // Coach-level exclusivity: only this coach is restricted. Other coaches at
        // the same school remain public unless the school itself is also exclusive.
        $query->where(function (Builder $visibility) use ($userId): void {
            $visibility
                ->whereNotExists(function ($subquery): void {
                    $subquery
                        ->selectRaw('1')
                        ->from(self::COACH_TABLE . ' as coach_visibility')
                        ->whereColumn('coach_visibility.coach_id', 'coaches.id');
                })
                ->orWhereExists(function ($subquery) use ($userId): void {
                    $subquery
                        ->selectRaw('1')
                        ->from(self::COACH_TABLE . ' as coach_visibility')
                        ->whereColumn('coach_visibility.coach_id', 'coaches.id')
                        ->where('coach_visibility.user_id', $userId);
                });
        });

        // School-level exclusivity applies to every coach belonging to that school.
        $query->where(function (Builder $visibility) use ($userId): void {
            $visibility
                ->whereNull('coaches.school_id')
                ->orWhere(function (Builder $schoolRule) use ($userId): void {
                    $schoolRule
                        ->whereNotExists(function ($subquery): void {
                            $subquery
                                ->selectRaw('1')
                                ->from(self::SCHOOL_TABLE . ' as school_visibility')
                                ->whereColumn('school_visibility.school_id', 'coaches.school_id');
                        })
                        ->orWhereExists(function ($subquery) use ($userId): void {
                            $subquery
                                ->selectRaw('1')
                                ->from(self::SCHOOL_TABLE . ' as school_visibility')
                                ->whereColumn('school_visibility.school_id', 'coaches.school_id')
                                ->where('school_visibility.user_id', $userId);
                        });
                });
        });

        return $query;
    }

    public function syncSchool(int $schoolId, array $userIds): void
    {
        $this->syncTarget('school', $schoolId, $userIds);
    }

    public function syncCoach(int $coachId, array $userIds): void
    {
        $this->syncTarget('coach', $coachId, $userIds);
    }

    public function makeSchoolPublic(int $schoolId): void
    {
        if ($this->ready() && DB::table(self::SCHOOL_TABLE)->where('school_id', $schoolId)->delete() > 0) {
            $this->touchVersion();
        }
    }

    public function makeCoachPublic(int $coachId): void
    {
        if ($this->ready() && DB::table(self::COACH_TABLE)->where('coach_id', $coachId)->delete() > 0) {
            $this->touchVersion();
        }
    }

    public function assignedUserIds(string $type, int $targetId): array
    {
        if (! $this->ready()) {
            return [];
        }

        [$table, $column] = $this->targetTableAndColumn($type);

        return DB::table($table)
            ->where($column, $targetId)
            ->orderBy('user_id')
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    public function adminRules(): array
    {
        if (! $this->ready()) {
            return ['schools' => [], 'coaches' => []];
        }

        $schoolAssignments = DB::table(self::SCHOOL_TABLE)
            ->orderBy('school_id')
            ->orderBy('user_id')
            ->get()
            ->groupBy('school_id');

        $coachAssignments = DB::table(self::COACH_TABLE)
            ->orderBy('coach_id')
            ->orderBy('user_id')
            ->get()
            ->groupBy('coach_id');

        $userIds = $schoolAssignments->flatten(1)
            ->merge($coachAssignments->flatten(1))
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $users = User::query()
            ->whereIn('id', $userIds)
            ->get(['id', 'first_name', 'last_name', 'email'])
            ->keyBy('id');

        $schoolIds = $schoolAssignments->keys()->map(fn ($id): int => (int) $id)->values();
        $schools = School::query()
            ->withTrashed()
            ->whereIn('id', $schoolIds)
            ->get(['id', 'name', 'city', 'state', 'deleted_at'])
            ->keyBy('id');

        $coachIds = $coachAssignments->keys()->map(fn ($id): int => (int) $id)->values();
        $coaches = Coach::query()
            ->withTrashed()
            ->with('school:id,name')
            ->whereIn('id', $coachIds)
            ->get(['id', 'school_id', 'display_name', 'first_name', 'last_name', 'email', 'sport', 'deleted_at'])
            ->keyBy('id');

        $schoolRows = $schoolAssignments->map(function ($rows, $schoolId) use ($schools, $users): array {
            $school = $schools->get((int) $schoolId);

            return [
                'type' => 'school',
                'id' => (int) $schoolId,
                'label' => $school?->name ?: 'School #' . $schoolId,
                'meta' => collect([$school?->city, $school?->state])->filter()->implode(', '),
                'deleted' => (bool) ($school?->trashed() ?? false),
                'users' => $this->userRowsForAssignments($rows, $users),
            ];
        })->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();

        $coachRows = $coachAssignments->map(function ($rows, $coachId) use ($coaches, $users): array {
            $coach = $coaches->get((int) $coachId);
            $name = $coach
                ? trim((string) ($coach->display_name ?: ($coach->first_name . ' ' . $coach->last_name)))
                : 'Coach #' . $coachId;

            return [
                'type' => 'coach',
                'id' => (int) $coachId,
                'label' => $name !== '' ? $name : 'Coach #' . $coachId,
                'meta' => collect([$coach?->school?->name, $coach?->sport, $coach?->email])->filter()->implode(' · '),
                'deleted' => (bool) ($coach?->trashed() ?? false),
                'users' => $this->userRowsForAssignments($rows, $users),
            ];
        })->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();

        return ['schools' => $schoolRows, 'coaches' => $coachRows];
    }

    /**
     * A fingerprint used by the Recruiting Center catalog cache. It changes for
     * inserts, updates, and deletes without requiring broad Cache::flush() calls.
     */
    public function fingerprint(): string
    {
        if (! $this->ready()) {
            return 'visibility-disabled';
        }

        $cacheKey = 'recruiting:visibility:version';
        $version = Cache::get($cacheKey);
        if (is_string($version) && $version !== '') {
            return $version;
        }

        $school = DB::table(self::SCHOOL_TABLE)
            ->selectRaw('COUNT(*) as aggregate, MAX(updated_at) as latest')
            ->first();
        $coach = DB::table(self::COACH_TABLE)
            ->selectRaw('COUNT(*) as aggregate, MAX(updated_at) as latest')
            ->first();

        $version = sha1(implode('|', [
            (string) ($school->aggregate ?? 0),
            (string) ($school->latest ?? ''),
            (string) ($coach->aggregate ?? 0),
            (string) ($coach->latest ?? ''),
        ]));

        Cache::forever($cacheKey, $version);

        return $version;
    }

    protected function syncTarget(string $type, int $targetId, array $userIds): void
    {
        if (! $this->ready()) {
            throw new \RuntimeException('Recruiting visibility tables have not been migrated yet.');
        }

        [$table, $column] = $this->targetTableAndColumn($type);

        $targetExists = $type === 'school'
            ? School::query()->whereKey($targetId)->exists()
            : Coach::query()->whereKey($targetId)->exists();

        if (! $targetExists) {
            throw new \InvalidArgumentException(ucfirst($type) . ' could not be found.');
        }

        $validUserIds = User::query()
            ->whereIn('id', collect($userIds)
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        if ($validUserIds->isEmpty()) {
            throw new \InvalidArgumentException('Choose at least one user for an exclusive rule.');
        }

        DB::transaction(function () use ($table, $column, $targetId, $validUserIds): void {
            DB::table($table)->where($column, $targetId)->delete();

            $now = now();
            DB::table($table)->insert($validUserIds->map(fn (int $userId): array => [
                $column => $targetId,
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });

        $this->touchVersion();
    }

    protected function touchVersion(): void
    {
        Cache::forever('recruiting:visibility:version', sha1(microtime(true) . '|' . bin2hex(random_bytes(12))));
    }

    protected function targetTableAndColumn(string $type): array
    {
        return match ($type) {
            'school' => [self::SCHOOL_TABLE, 'school_id'],
            'coach' => [self::COACH_TABLE, 'coach_id'],
            default => throw new \InvalidArgumentException('Unknown recruiting visibility type.'),
        };
    }

    protected function userRowsForAssignments($rows, $users): array
    {
        return collect($rows)->map(function ($assignment) use ($users): array {
            $user = $users->get((int) $assignment->user_id);
            $name = trim((string) (($user?->first_name ?? '') . ' ' . ($user?->last_name ?? '')));

            return [
                'id' => (int) $assignment->user_id,
                'name' => $name !== '' ? $name : ($user?->email ?: 'User #' . $assignment->user_id),
                'email' => (string) ($user?->email ?? ''),
            ];
        })->values()->all();
    }
}
