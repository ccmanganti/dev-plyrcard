<?php

namespace App\Services;

use App\Models\User;

class LocalCoachDatabaseSchoolService
{
    public function __construct(protected LocalRecruitingDatabaseService $database) {}

    public function syncFromSnapshot(User $user, array $schools, ?string $sourceCachedAt = null): int
    {
        // The canonical schools table is populated by the coach importer.
        // GHL snapshots are no longer copied into a per-player school table.
        return 0;
    }

    public function ensureSeeded(User $user, array $snapshot): void
    {
        $this->database->ensureDefaultLists($user);
    }

    public function discoverDataset(User $user): array
    {
        // v104 compatibility wrapper: Discover is canonical local School/Coach data.
        return app(LocalRecruitingDatabaseService::class)->schoolRows($user);
    }

    public function favoriteSchools(User $user): array
    {
        // v104 compatibility wrapper: favorites are player-owned local records.
        return app(LocalRecruitingDatabaseService::class)->favoriteSchools($user);
    }

    public function find(User $user, string $schoolId): ?array
    {
        $school = $this->database->findSchool($user, $schoolId);
        if (! $school) {
            return null;
        }

        return collect($this->database->schoolRows($user))
            ->first(fn (array $row): bool => (string) ($row['id'] ?? '') === (string) $school->getKey());
    }
}