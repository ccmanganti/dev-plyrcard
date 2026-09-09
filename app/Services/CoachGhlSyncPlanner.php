<?php

namespace App\Services;

use App\Models\Coach;
use App\Models\CoachGhlSyncTarget;
use App\Models\SchoolGhlSyncTarget;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CoachGhlSyncPlanner
{
    /**
     * Prepare GHL targets for one coach and reconcile any old targets for that coach.
     *
     * The same school can exist in multiple subaccounts, but a coach is only eligible
     * for a subaccount when the coach's normalized sport + gender matches at least one
     * current PLYRCARD user sharing that exact GHL API key/location credential group.
     */
    public function planForCoach(Coach $coach): array
    {
        return $this->planForCoaches(collect([$coach]), false);
    }

    /**
     * @param Collection<int, Coach> $coaches
     */
    public function planForCoaches(Collection $coaches, bool $reconcileAll = false): array
    {
        $coaches = $coaches
            ->filter(fn ($coach): bool => $coach instanceof Coach && filled($coach->email))
            ->values();

        // This method is called with both Eloquent collections and collect([$coach]).
        // Load the school model per coach instead of relying on EloquentCollection::loadMissing().
        $coaches->each(fn (Coach $coach): Coach => $coach->loadMissing('school:id,name'));

        $credentialGroups = $this->credentialGroups();
        $desiredTargetIds = [];
        $desiredSchoolKeys = [];
        $eligibleCoachIds = [];
        $targetCount = 0;

        foreach ($coaches as $coach) {
            $email = Str::lower(trim((string) $coach->email));
            $schoolName = trim((string) ($coach->school?->name ?? ''));
            $coachTargetCount = 0;

            foreach ($credentialGroups as $group) {
                if (! $this->coachMatchesGroup($coach, $group)) {
                    continue;
                }

                $target = CoachGhlSyncTarget::query()->updateOrCreate(
                    [
                        'coach_id' => $coach->getKey(),
                        'api_key_hash' => $group['api_key_hash'],
                        'location_id' => $group['location_id'],
                    ],
                    [
                        'representative_user_id' => $group['representative_user_id'],
                        'account_user_ids' => $group['user_ids'],
                        'school_name_snapshot' => $schoolName !== '' ? $schoolName : null,
                        'coach_email_snapshot' => $email,
                        'status' => 'pending',
                        'matched_by' => null,
                        'last_error' => null,
                    ],
                );

                $desiredTargetIds[(int) $target->getKey()] = true;
                $targetCount++;
                $coachTargetCount++;

                if ($coach->school_id) {
                    $desiredSchoolKeys[$this->schoolTargetKey(
                        (int) $coach->school_id,
                        (string) $group['api_key_hash'],
                        (string) $group['location_id'],
                    )] = true;
                }
            }

            if ($coachTargetCount > 0) {
                $eligibleCoachIds[] = (int) $coach->getKey();
            }

            $coach->forceFill([
                'ghl_sync_status' => $coachTargetCount > 0 ? 'pending' : 'excluded_audience',
            ])->saveQuietly();
        }

        $excludedTargets = $this->reconcileCoachTargets(
            desiredTargetIds: $desiredTargetIds,
            coachIds: $coaches->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            reconcileAll: $reconcileAll,
        );

        $excludedSchools = $reconcileAll
            ? $this->reconcileSchoolTargets($desiredSchoolKeys)
            : 0;

        return [
            'coaches' => $coaches->count(),
            'eligible_coaches' => count($eligibleCoachIds),
            'credential_groups' => $credentialGroups->count(),
            'eligible_credential_groups' => $credentialGroups
                ->filter(fn (array $group): bool => ! empty($group['audiences']))
                ->count(),
            'targets' => $targetCount,
            'excluded_targets' => $excludedTargets,
            'excluded_school_targets' => $excludedSchools,
        ];
    }

    /**
     * Current credential groups, including the exact sport+gender audiences represented
     * by all PLYRCARD users sharing the same API key and GHL location.
     *
     * A group can intentionally have no audience if its current users are missing either
     * sport or gender. Such a group receives no coach targets (fail closed).
     *
     * @return Collection<int, array{
     *   api_key_hash:string,
     *   location_id:string,
     *   representative_user_id:int,
     *   user_ids:array<int,int>,
     *   audiences:array<string,array{sport:string,gender:string}>
     * }>
     */
    public function credentialGroups(): Collection
    {
        return User::query()
            ->select(['id', 'sport', 'gender', 'ghl_api_key', 'ghl_location_id'])
            ->whereNotNull('ghl_api_key')
            ->where('ghl_api_key', '!=', '')
            ->whereNotNull('ghl_location_id')
            ->where('ghl_location_id', '!=', '')
            ->get()
            ->groupBy(function (User $user): string {
                $token = trim((string) $user->ghl_api_key);
                $locationId = trim((string) $user->ghl_location_id);

                return hash('sha256', $token) . '|' . Str::lower($locationId);
            })
            ->map(function (Collection $users): array {
                $audiences = [];
                $eligibleUsers = collect();

                foreach ($users as $user) {
                    $sport = $this->normalizeSport($user->sport ?? null);
                    $gender = $this->normalizeGender($user->gender ?? null);

                    if ($sport === null || $gender === null) {
                        continue;
                    }

                    $audiences[$this->audienceKey($sport, $gender)] = [
                        'sport' => $sport,
                        'gender' => $gender,
                    ];
                    $eligibleUsers->push($user);
                }

                /** @var User $representative */
                $representative = $eligibleUsers->first() ?: $users->first();

                return [
                    'api_key_hash' => hash('sha256', trim((string) $representative->ghl_api_key)),
                    'location_id' => trim((string) $representative->ghl_location_id),
                    'representative_user_id' => (int) $representative->getKey(),
                    'user_ids' => $users->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
                    'audiences' => $audiences,
                ];
            })
            ->values();
    }

    public function coachMatchesGroup(Coach $coach, array $group): bool
    {
        $sport = $this->normalizeSport($coach->sport ?? null);
        $gender = $this->normalizeGender($coach->gender ?? null);

        if ($sport === null || $gender === null) {
            return false;
        }

        return isset(($group['audiences'] ?? [])[$this->audienceKey($sport, $gender)]);
    }

    public function credentialGroupKey(string $apiKeyHash, string $locationId): string
    {
        return strtolower(trim($apiKeyHash)) . '|' . strtolower(trim($locationId));
    }

    protected function reconcileCoachTargets(array $desiredTargetIds, array $coachIds, bool $reconcileAll): int
    {
        if (! $reconcileAll && empty($coachIds)) {
            return 0;
        }

        $query = CoachGhlSyncTarget::query()->select(['id', 'coach_id']);
        if (! $reconcileAll) {
            $query->whereIn('coach_id', $coachIds);
        }

        $excluded = 0;

        $query->orderBy('id')->chunkById(500, function ($rows) use ($desiredTargetIds, &$excluded): void {
            $ids = $rows
                ->filter(fn (CoachGhlSyncTarget $target): bool => ! isset($desiredTargetIds[(int) $target->getKey()]))
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            if (empty($ids)) {
                return;
            }

            CoachGhlSyncTarget::query()
                ->whereIn('id', $ids)
                ->update([
                    'status' => 'excluded_audience',
                    'last_error' => null,
                ]);

            $excluded += count($ids);
        }, 'id');

        return $excluded;
    }

    protected function reconcileSchoolTargets(array $desiredSchoolKeys): int
    {
        $excluded = 0;

        SchoolGhlSyncTarget::query()
            ->select(['id', 'school_id', 'api_key_hash', 'location_id'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($desiredSchoolKeys, &$excluded): void {
                $ids = $rows
                    ->filter(function (SchoolGhlSyncTarget $target) use ($desiredSchoolKeys): bool {
                        $key = $this->schoolTargetKey(
                            (int) $target->school_id,
                            (string) $target->api_key_hash,
                            (string) $target->location_id,
                        );

                        return ! isset($desiredSchoolKeys[$key]);
                    })
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();

                if (empty($ids)) {
                    return;
                }

                SchoolGhlSyncTarget::query()
                    ->whereIn('id', $ids)
                    ->update([
                        'status' => 'excluded_audience',
                        'last_error' => null,
                    ]);

                $excluded += count($ids);
            }, 'id');

        return $excluded;
    }

    protected function schoolTargetKey(int $schoolId, string $apiKeyHash, string $locationId): string
    {
        return $schoolId . '|' . $this->credentialGroupKey($apiKeyHash, $locationId);
    }

    protected function audienceKey(string $sport, string $gender): string
    {
        return $sport . '|' . $gender;
    }

    protected function normalizeGender(?string $gender): ?string
    {
        $gender = strtolower(trim((string) $gender));

        if ($gender === '') {
            return null;
        }

        return match (true) {
            str_contains($gender, 'female'),
            str_contains($gender, 'girl'),
            str_contains($gender, 'women'),
            str_contains($gender, 'woman') => 'female',
            str_contains($gender, 'male'),
            str_contains($gender, 'boy'),
            str_contains($gender, 'men'),
            str_contains($gender, 'man') => 'male',
            default => null,
        };
    }

    protected function normalizeSport(?string $sport): ?string
    {
        $sport = trim((string) $sport);
        if ($sport === '') {
            return null;
        }

        $normalized = Str::of($sport)
            ->lower()
            ->ascii()
            ->replace('&', ' and ')
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->toString();

        $aliases = [
            'track_field' => 'track_and_field',
            'track_and_field' => 'track_and_field',
            'track_n_field' => 'track_and_field',
            'tabletennis' => 'table_tennis',
            'table_tennis' => 'table_tennis',
            'beachvolleyball' => 'beach_volleyball',
            'beach_volleyball' => 'beach_volleyball',
            'icehockey' => 'ice_hockey',
            'ice_hockey' => 'ice_hockey',
            'fieldhockey' => 'field_hockey',
            'field_hockey' => 'field_hockey',
            'crosscountry' => 'cross_country',
            'cross_country' => 'cross_country',
            'waterpolo' => 'water_polo',
            'water_polo' => 'water_polo',
            'martialarts' => 'martial_arts',
            'martial_arts' => 'martial_arts',
        ];

        return $aliases[$normalized] ?? ($normalized !== '' ? $normalized : null);
    }
}