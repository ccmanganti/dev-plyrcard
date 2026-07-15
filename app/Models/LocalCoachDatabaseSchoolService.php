<?php

namespace App\Services;

use App\Models\CoachDatabaseSchool;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LocalCoachDatabaseSchoolService
{
    public function syncFromSnapshot(User $user, array $schools, ?string $sourceCachedAt = null): int
    {
        if (! Schema::hasTable('coach_database_schools')) {
            return 0;
        }

        $locationId = $this->locationId($user);
        $rows = collect($schools)
            ->filter(fn ($school): bool => is_array($school))
            ->map(fn (array $school): ?array => $this->normalizeSchool($user, $locationId, $school, $sourceCachedAt))
            ->filter()
            ->values();

        if ($rows->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($user, $locationId, $rows): void {
            foreach ($rows->chunk(300) as $chunk) {
                CoachDatabaseSchool::query()->upsert(
                    $chunk->all(),
                    ['user_id', 'ghl_location_id', 'business_id'],
                    [
                        'name', 'logo_url', 'conference', 'division', 'city', 'state',
                        'coach_count', 'head_coach_name', 'head_coach_title',
                        'head_coach_email', 'search_text', 'payload',
                        'source_cached_at', 'last_synced_at', 'updated_at',
                    ]
                );
            }

            $businessIds = $rows->pluck('business_id')->filter()->unique()->values()->all();
            if (! empty($businessIds)) {
                CoachDatabaseSchool::query()
                    ->where('user_id', $user->getKey())
                    ->where('ghl_location_id', $locationId)
                    ->whereNotIn('business_id', $businessIds)
                    ->delete();
            }
        });

        return $rows->count();
    }

    public function ensureSeeded(User $user, array $snapshot): void
    {
        if (! Schema::hasTable('coach_database_schools')) {
            return;
        }

        $query = $this->query($user);
        $sourceCachedAt = trim((string) ($snapshot['cached_at'] ?? ''));
        $currentSource = (string) ($query->max('source_cached_at') ?? '');
        $hasRows = $query->exists();

        if (! $hasRows || ($sourceCachedAt !== '' && $sourceCachedAt !== $currentSource)) {
            $this->syncFromSnapshot($user, is_array($snapshot['schools'] ?? null) ? $snapshot['schools'] : [], $sourceCachedAt);
        }
    }

    public function discoverDataset(User $user): array
    {
        if (! Schema::hasTable('coach_database_schools')) {
            return [];
        }

        return $this->query($user)
            ->orderByRaw('LOWER(name) ASC')
            ->get()
            ->map(fn (CoachDatabaseSchool $school): array => $this->toDisplayRow($school))
            ->all();
    }

    public function favoriteSchools(User $user): array
    {
        if (! Schema::hasTable('coach_database_schools') || ! Schema::hasTable('coach_database_school_memberships')) {
            return [];
        }

        return $this->query($user)
            ->whereExists(function ($query) use ($user): void {
                $query->selectRaw('1')
                    ->from('coach_database_school_memberships as memberships')
                    ->whereColumn('memberships.business_id', 'coach_database_schools.business_id')
                    ->whereColumn('memberships.ghl_location_id', 'coach_database_schools.ghl_location_id')
                    ->where('memberships.user_id', $user->getKey())
                    ->where('memberships.list_key', '__favorite__');
            })
            ->orderByRaw('LOWER(name) ASC')
            ->get()
            ->map(fn (CoachDatabaseSchool $school): array => $this->toDisplayRow($school, true))
            ->all();
    }

    public function find(User $user, string $schoolId): ?array
    {
        $schoolId = trim($schoolId);
        if ($schoolId === '' || ! Schema::hasTable('coach_database_schools')) {
            return null;
        }

        $row = $this->query($user)
            ->where(function ($query) use ($schoolId): void {
                $query->where('business_id', $schoolId)
                    ->orWhere('id', ctype_digit($schoolId) ? (int) $schoolId : -1);
            })
            ->first();

        return $row ? $this->toDisplayRow($row) : null;
    }

    protected function query(User $user)
    {
        return CoachDatabaseSchool::query()
            ->where('user_id', $user->getKey())
            ->where('ghl_location_id', $this->locationId($user));
    }

    protected function normalizeSchool(User $user, string $locationId, array $school, ?string $sourceCachedAt): ?array
    {
        $businessId = trim((string) ($school['business_id'] ?? $school['company_id'] ?? $school['id'] ?? ''));
        $name = trim((string) ($school['name'] ?? $school['school_name'] ?? $school['company_name'] ?? ''));

        if ($businessId === '' || $name === '') {
            return null;
        }

        $headCoach = is_array($school['head_coach'] ?? null) ? $school['head_coach'] : [];
        if (blank($headCoach['name'] ?? null)) {
            $headCoach = collect($school['coaches_preview'] ?? $school['coaches'] ?? [])
                ->first(fn ($coach): bool => is_array($coach) && filled($coach['name'] ?? null)) ?: [];
        }

        $conference = trim((string) ($school['conference'] ?? ''));
        $division = trim((string) ($school['division'] ?? ''));
        $coachName = trim((string) ($headCoach['name'] ?? ''));
        $coachTitle = trim((string) ($headCoach['title'] ?? ''));
        $coachEmail = trim((string) ($headCoach['email'] ?? ''));
        $coachCount = max(
            (int) ($school['coach_count'] ?? 0),
            (int) ($school['coaches_count'] ?? 0),
            is_array($school['coaches'] ?? null) ? count($school['coaches']) : 0,
            is_array($school['coaches_preview'] ?? null) ? count($school['coaches_preview']) : 0,
        );

        $logo = trim((string) ($school['logo_url'] ?? $school['school_logo_url'] ?? $school['business_logo_url'] ?? $school['logo'] ?? ''));
        $now = now();

        return [
            'user_id' => $user->getKey(),
            'ghl_location_id' => $locationId,
            'business_id' => $businessId,
            'name' => $name,
            'logo_url' => $logo !== '' ? $logo : null,
            'conference' => $conference !== '' ? $conference : null,
            'division' => $division !== '' ? $division : null,
            'city' => filled($school['city'] ?? null) ? trim((string) $school['city']) : null,
            'state' => filled($school['state'] ?? null) ? trim((string) $school['state']) : null,
            'coach_count' => $coachCount,
            'head_coach_name' => $coachName !== '' ? $coachName : null,
            'head_coach_title' => $coachTitle !== '' ? $coachTitle : null,
            'head_coach_email' => $coachEmail !== '' ? $coachEmail : null,
            'search_text' => strtolower(trim(implode(' ', array_filter([$name, $conference, $division, $coachName, $coachTitle, $coachEmail])))),
            'payload' => $this->lightPayload($school),
            'source_cached_at' => filled($sourceCachedAt) ? $sourceCachedAt : null,
            'last_synced_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    protected function toDisplayRow(CoachDatabaseSchool $school, bool $favorite = false): array
    {
        $payload = is_array($school->payload) ? $school->payload : [];

        return array_merge($payload, [
            'id' => $school->business_id,
            'business_id' => $school->business_id,
            'company_id' => $school->business_id,
            'name' => $school->name,
            'logo_url' => (string) ($school->logo_url ?? ''),
            'school_logo_url' => (string) ($school->logo_url ?? ''),
            'business_logo_url' => (string) ($school->logo_url ?? ''),
            'conference' => (string) ($school->conference ?? ''),
            'division' => (string) ($school->division ?? ''),
            'city' => (string) ($school->city ?? ''),
            'state' => (string) ($school->state ?? ''),
            'coach_count' => (int) $school->coach_count,
            'coaches_count' => (int) $school->coach_count,
            'head_coach' => [
                'name' => (string) ($school->head_coach_name ?? ''),
                'title' => (string) ($school->head_coach_title ?? ''),
                'email' => (string) ($school->head_coach_email ?? ''),
            ],
            'head_coach_name' => (string) ($school->head_coach_name ?? '—'),
            'head_coach_title' => (string) ($school->head_coach_title ?? 'Coach'),
            'head_coach_email' => (string) ($school->head_coach_email ?? ''),
            'search_text' => (string) ($school->search_text ?? ''),
            'is_favorite' => $favorite,
            'is_favorite_school' => $favorite,
        ]);
    }

    protected function lightPayload(array $school): array
    {
        return collect($school)
            ->except(['coaches', 'coaches_preview', 'raw_business', 'raw_contact'])
            ->all();
    }

    protected function locationId(User $user): string
    {
        return trim((string) ($user->ghl_location_id ?? config('ghl.location_id') ?? ''));
    }
}
