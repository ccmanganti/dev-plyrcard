<?php

namespace App\Services;

use App\Models\CoachDatabaseSchoolMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LocalSchoolMembershipService
{
    /** @var array<string, array<string, array<int, string>>> */
    protected static array $requestMaps = [];

    public function keysForBusiness(User $user, string $businessId): array
    {
        $businessId = trim($businessId);
        if ($businessId === '') {
            return [];
        }

        $map = $this->membershipMap($user);

        return $map[$businessId] ?? [];
    }

    public function membershipMap(User $user): array
    {
        $scope = $this->scopeKey($user);

        if (array_key_exists($scope, static::$requestMaps)) {
            return static::$requestMaps[$scope];
        }

        $rows = CoachDatabaseSchoolMembership::query()
            ->where('user_id', $user->getKey())
            ->where('ghl_location_id', $this->locationId($user))
            ->orderBy('id')
            ->get(['business_id', 'list_key']);

        return static::$requestMaps[$scope] = $rows
            ->groupBy('business_id')
            ->map(fn ($items): array => $items
                ->pluck('list_key')
                ->map(fn ($key): string => strtolower(trim((string) $key)))
                ->filter()
                ->unique()
                ->values()
                ->all())
            ->all();
    }

    public function replaceListKeys(User $user, string $businessId, array $listKeys): array
    {
        return $this->replaceMembershipKeys($user, $businessId, $listKeys);
    }

    public function replaceMembershipKeys(User $user, string $businessId, array $listKeys): array
    {
        $businessId = trim($businessId);
        if ($businessId === '') {
            return ['success' => false, 'error' => 'The school is missing a GHL business ID.'];
        }

        $keys = $this->normalizeKeys($listKeys);
        $locationId = $this->locationId($user);

        DB::transaction(function () use ($user, $locationId, $businessId, $keys): void {
            $query = CoachDatabaseSchoolMembership::query()
                ->where('user_id', $user->getKey())
                ->where('ghl_location_id', $locationId)
                ->where('business_id', $businessId);

            if (empty($keys)) {
                $query->delete();
                return;
            }

            $query->whereNotIn('list_key', $keys)->delete();

            $now = now();
            CoachDatabaseSchoolMembership::query()->insertOrIgnore(
                collect($keys)->map(fn (string $key): array => [
                    'user_id' => $user->getKey(),
                    'ghl_location_id' => $locationId,
                    'business_id' => $businessId,
                    'list_key' => $key,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all()
            );
        });

        $this->forgetRequestMap($user);

        return [
            'success' => true,
            'business_id' => $businessId,
            'list_keys' => $keys,
            'updated' => 1,
            'failed' => 0,
        ];
    }

    public function replaceListKeysBulk(User $user, array $schools): array
    {
        return $this->replaceMembershipKeysBulk($user, $schools);
    }

    public function replaceMembershipKeysBulk(User $user, array $schools): array
    {
        $normalized = collect($schools)
            ->filter(fn ($row): bool => is_array($row))
            ->mapWithKeys(function (array $row): array {
                $businessId = trim((string) (
                    $row['business_id']
                    ?? $row['company_id']
                    ?? $row['id']
                    ?? ''
                ));

                if ($businessId === '') {
                    return [];
                }

                return [$businessId => $this->normalizeKeys(
                    $row['membership_keys']
                    ?? $row['list_keys']
                    ?? $row['lists']
                    ?? []
                )];
            });

        if ($normalized->isEmpty()) {
            return ['success' => false, 'updated' => 0, 'failed' => 0, 'error' => 'No valid schools were supplied.'];
        }

        $locationId = $this->locationId($user);
        $businessIds = $normalized->keys()->all();

        DB::transaction(function () use ($user, $locationId, $businessIds, $normalized): void {
            CoachDatabaseSchoolMembership::query()
                ->where('user_id', $user->getKey())
                ->where('ghl_location_id', $locationId)
                ->whereIn('business_id', $businessIds)
                ->delete();

            $now = now();
            $rows = $normalized->flatMap(function (array $keys, string $businessId) use ($user, $locationId, $now): array {
                return collect($keys)->map(fn (string $key): array => [
                    'user_id' => $user->getKey(),
                    'ghl_location_id' => $locationId,
                    'business_id' => $businessId,
                    'list_key' => $key,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();
            })->values()->all();

            foreach (array_chunk($rows, 1000) as $chunk) {
                if (! empty($chunk)) {
                    CoachDatabaseSchoolMembership::query()->insertOrIgnore($chunk);
                }
            }
        });

        $this->forgetRequestMap($user);

        return [
            'success' => true,
            'updated' => $normalized->count(),
            'failed' => 0,
            'school_updates' => $normalized->count(),
        ];
    }

    public function removeListFromAllSchools(User $user, string $listKey): int
    {
        return $this->removeListsFromAllSchools($user, [$listKey]);
    }

    public function removeListsFromAllSchools(User $user, array $listKeys): int
    {
        $keys = $this->normalizeKeys($listKeys);

        if (empty($keys)) {
            return 0;
        }

        $deleted = CoachDatabaseSchoolMembership::query()
            ->where('user_id', $user->getKey())
            ->where('ghl_location_id', $this->locationId($user))
            ->whereIn('list_key', $keys)
            ->delete();

        $this->forgetRequestMap($user);

        return $deleted;
    }

    public function forgetRequestMap(User $user): void
    {
        unset(static::$requestMaps[$this->scopeKey($user)]);
    }

    protected function normalizeKeys(array $keys): array
    {
        return collect($keys)
            ->map(fn ($key): string => strtolower(trim((string) $key)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function locationId(User $user): string
    {
        return trim((string) ($user->ghl_location_id ?? config('ghl.location_id') ?? ''));
    }

    protected function scopeKey(User $user): string
    {
        return (string) $user->getKey() . '|' . $this->locationId($user);
    }
}