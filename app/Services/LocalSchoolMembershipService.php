<?php

namespace App\Services;

use App\Models\User;

class LocalSchoolMembershipService
{
    public function __construct(protected LocalRecruitingDatabaseService $database) {}

    public function keysForBusiness(User $user, string $businessId): array
    {
        return $this->database->membershipKeys($user, $businessId);
    }

    public function keysForSchool(User $user, string|int $schoolId): array
    {
        return $this->database->membershipKeys($user, $schoolId);
    }

    public function membershipMap(User $user): array
    {
        $map = [];
        foreach ($this->database->schoolRows($user) as $school) {
            $keys = $school['list_keys'] ?? [];
            $map[(string) ($school['id'] ?? '')] = $keys;
            if (filled($school['business_id'] ?? null)) {
                $map[(string) $school['business_id']] = $keys;
            }
        }
        return $map;
    }

    public function replaceListKeys(User $user, string $schoolIdentifier, array $listKeys): array
    {
        return $this->replaceMembershipKeys($user, $schoolIdentifier, $listKeys);
    }

    public function replaceMembershipKeys(User $user, string $schoolIdentifier, array $listKeys): array
    {
        return $this->database->replaceMembershipKeys($user, $schoolIdentifier, $listKeys);
    }

    public function replaceListKeysBulk(User $user, array $schools): array
    {
        return $this->replaceMembershipKeysBulk($user, $schools);
    }

    public function replaceMembershipKeysBulk(User $user, array $schools): array
    {
        return $this->database->replaceMembershipKeysBulk($user, $schools);
    }

    public function removeListFromAllSchools(User $user, string $listKey): int
    {
        return $this->database->removeListsFromAllSchools($user, [$listKey]);
    }

    public function removeListsFromAllSchools(User $user, array $listKeys): int
    {
        return $this->database->removeListsFromAllSchools($user, $listKeys);
    }

    public function forgetRequestMap(User $user): void
    {
        // No request-static cache. Local Eloquent data is read directly.
    }
}