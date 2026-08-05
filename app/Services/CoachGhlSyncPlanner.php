<?php

namespace App\Services;

use App\Models\Coach;
use App\Models\CoachGhlSyncTarget;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CoachGhlSyncPlanner
{
    /**
     * Prepare one pending synchronization target for every unique GHL
     * credential/location combination configured on local user accounts.
     *
     * This does not call GHL yet. It performs the efficient local credential
     * comparison and creates the queueable work records for the next update.
     */
    public function planForCoach(Coach $coach): array
    {
        return $this->planForCoaches(collect([$coach]));
    }

    /** @param Collection<int, Coach> $coaches */
    public function planForCoaches(Collection $coaches): array
    {
        $coaches = $coaches
            ->filter(fn ($coach): bool => $coach instanceof Coach && filled($coach->email))
            ->values();

        // collect([$coach]) returns an Illuminate\Support\Collection, which does
        // not provide Eloquent's loadMissing() method. Load the relation on each
        // model so this method works for both a single coach and imported batches.
        $coaches->each(
            fn (Coach $coach): Coach => $coach->loadMissing('school:id,name')
        );

        if ($coaches->isEmpty()) {
            return ['coaches' => 0, 'credential_groups' => 0, 'targets' => 0];
        }

        $credentialGroups = $this->credentialGroups();
        $targetCount = 0;

        foreach ($coaches as $coach) {
            $email = Str::lower(trim((string) $coach->email));
            $schoolName = trim((string) ($coach->school?->name ?? ''));

            foreach ($credentialGroups as $group) {
                CoachGhlSyncTarget::query()->updateOrCreate(
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

                $targetCount++;
            }

            $coach->forceFill([
                'ghl_sync_status' => $credentialGroups->isEmpty() ? 'no_credentials' : 'pending',
            ])->saveQuietly();
        }

        return [
            'coaches' => $coaches->count(),
            'credential_groups' => $credentialGroups->count(),
            'targets' => $targetCount,
        ];
    }

    /**
     * Accounts sharing the same API key and location are treated as one target.
     * The raw token is never persisted in the planning table.
     *
     * @return Collection<int, array{api_key_hash:string,location_id:string,representative_user_id:int,user_ids:array<int,int>}>
     */
    public function credentialGroups(): Collection
    {
        return User::query()
            ->select(['id', 'ghl_api_key', 'ghl_location_id'])
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
                /** @var User $representative */
                $representative = $users->first();

                return [
                    'api_key_hash' => hash('sha256', trim((string) $representative->ghl_api_key)),
                    'location_id' => trim((string) $representative->ghl_location_id),
                    'representative_user_id' => (int) $representative->getKey(),
                    'user_ids' => $users->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
                ];
            })
            ->values();
    }
}