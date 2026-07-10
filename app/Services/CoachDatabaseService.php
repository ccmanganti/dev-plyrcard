<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CoachDatabaseService
{
    public function __construct(
        protected GoHighLevelService $goHighLevelService,
    ) {}

    public function canAccess(User $user): bool
    {
        if (method_exists($user, 'isSuperadminOrImpersonating') && $user->isSuperadminOrImpersonating()) {
            return true;
        }

        return method_exists($user, 'hasRole')
            && $user->hasRole('My Journey');
    }

    public function hasGhlConnection(User $user): bool
    {
        return filled($user->ghl_location_id ?? null)
            && filled($user->ghl_api_key ?? null);
    }


    public function getInitialState(User $user): array
    {
        if (! config('ghl.coach_database.enabled', true)) {
            return $this->locked('Recruiting Center is currently disabled.');
        }

        if (! $this->canAccess($user)) {
            return $this->locked('Coach Database is available on paid plans.');
        }

        if (! $this->hasGhlConnection($user)) {
            return [
                'allowed' => false,
                'locked' => false,
                'reason' => 'Missing recruiting data connection.',
                'coaches' => [],
                'schools' => [],
                'lists' => $this->emptyLists(),
                'stats' => $this->emptyStats(),
                'top_schools' => [],
            ];
        }

        return [
            'allowed' => true,
            'locked' => false,
            'reason' => null,
            'coaches' => [],
            'schools' => [],
            'lists' => $this->emptyLists(),
            'stats' => $this->emptyStats(),
            'top_schools' => [],
        ];
    }

    public function getCoachContactsPageForUser(
        User $user,
        ?int $startAfter = null,
        ?string $startAfterId = null,
        int $limit = 100,
    ): array {
        $result = $this->goHighLevelService->getCoachContactsPageForUser(
            user: $user,
            startAfter: $startAfter,
            startAfterId: $startAfterId,
            limit: $limit,
        );

        $coaches = collect($result['contacts'] ?? [])
            ->filter(fn ($coach): bool => is_array($coach))
            ->map(fn (array $coach): array => $this->slimCoach($coach))
            ->filter(fn (array $coach): bool => $this->coachHasSchoolReference($coach))
            ->unique(fn (array $coach): string => $this->coachUniqueKey($coach))
            ->values();

        return [
            'success' => (bool) ($result['success'] ?? false),
            'contacts' => $coaches->all(),
            'count' => $coaches->count(),
            'total' => $result['total'] ?? null,
            'next_start_after' => $result['next_start_after'] ?? null,
            'next_start_after_id' => $result['next_start_after_id'] ?? null,
            'has_more' => (bool) ($result['has_more'] ?? false),
            'error' => $result['error'] ?? null,
        ];
    }

    public function getRemoteRecruitingCounts(User $user): array
    {
        return $this->goHighLevelService->getRecruitingRemoteCountsForUser($user);
    }


    /**
     * Build the same cache key used by the Livewire Recruiting Center page.
     *
     * This lets the hourly command refresh the dashboard snapshot in the background
     * without needing a local analytics database. Recruiting Center remains the source of truth;
     * this cache is only the fast dashboard read model.
     */
    public function recruitingSnapshotCacheKey(User $user): string
    {
        return 'coach-database:v10:' . ($user->id ?: 'guest') . ':' . Str::slug((string) ($user->ghl_location_id ?: 'default'));
    }

    public function cachedRecruitingSnapshotForUser(User $user): ?array
    {
        $snapshot = Cache::get($this->recruitingSnapshotCacheKey($user));

        return is_array($snapshot) ? $snapshot : null;
    }

    /**
     * Sync all contact-level recruiting custom field values from Recruiting Center in one paged pass,
     * aggregate them, and store the result in Laravel cache for the dashboard.
     *
     * This avoids live dashboard math across many API requests. Tracking routes still
     * write to Recruiting Center custom fields, while the dashboard reads this hourly/manual cache.
     */
    public function syncRecruitingStatsForUser(User $user, bool $force = false): array
    {
        if (! config('ghl.coach_database.enabled', true)) {
            return $this->locked('Recruiting Center is currently disabled.');
        }

        if (! $this->canAccess($user)) {
            return $this->locked('Coach Database is available on paid plans.');
        }

        if (! $this->hasGhlConnection($user)) {
            return [
                'success' => false,
                'allowed' => false,
                'locked' => false,
                'reason' => 'Missing recruiting data connection.',
                'coaches' => [],
                'schools' => [],
                'lists' => $this->emptyLists($user),
                'stats' => $this->emptyStats(),
                'top_schools' => [],
                'error' => 'Missing recruiting data connection.',
            ];
        }

        $cacheKey = $this->recruitingSnapshotCacheKey($user);
        $existingSnapshot = Cache::get($cacheKey, []);
        $existingSnapshot = is_array($existingSnapshot) ? $existingSnapshot : [];

        $exportPath = $this->recruitingStatsCsvPath($user);
        $result = $this->goHighLevelService->exportRecruitingContactsCsvForUser($user, $exportPath);

        if (! ($result['success'] ?? false)) {
            $existingSnapshot['stats_sync_failed_at'] = now()->toDateTimeString();
            $existingSnapshot['stats_sync_error'] = $result['error'] ?? 'Unable to export recruiting contacts from Recruiting Center.';
            Cache::put($cacheKey, $existingSnapshot, now()->addHours((int) config('ghl.coach_database.cache_hours', 12)));

            return array_merge($existingSnapshot, [
                'success' => false,
                'error' => $existingSnapshot['stats_sync_error'],
            ]);
        }

        $csvRows = $this->readRecruitingStatsCsv($exportPath);

        $coaches = collect($csvRows)
            ->filter(fn ($coach): bool => is_array($coach))
            ->map(fn (array $coach): array => $this->slimCoach($coach))
            ->filter(fn (array $coach): bool => $this->coachHasSchoolReference($coach))
            ->unique(fn (array $coach): string => $this->coachUniqueKey($coach))
            ->values()
            ->all();

        $customListTags = is_array($existingSnapshot['custom_list_tags'] ?? null)
            ? $existingSnapshot['custom_list_tags']
            : [];

        $existingSchools = is_array($existingSnapshot['schools'] ?? null)
            ? $existingSnapshot['schools']
            : [];

        $dashboard = $this->rebuildFromSchoolCompanySnapshot(
            schools: $existingSchools,
            coaches: $coaches,
            user: $user,
            customListTags: $customListTags,
        );

        $snapshot = array_merge($existingSnapshot, $dashboard, [
            'success' => true,
            'allowed' => true,
            'locked' => false,
            'reason' => null,
            'error' => null,
            'custom_list_tags' => $customListTags,
            'loaded_schools_count' => count($dashboard['schools'] ?? []),
            'loaded_contacts_count' => count($dashboard['coaches'] ?? []),
            'remote_total_contacts' => $result['total'] ?? $result['count'] ?? count($coaches),
            'contacts_have_more' => false,
            'has_more_data' => false,
            'stats_synced_at' => now()->toDateTimeString(),
            'stats_sync_mode' => 'csv_export',
            'stats_csv_path' => $result['path'] ?? $exportPath,
            'stats_csv_contact_count' => $result['count'] ?? count($coaches),
            'cached_at' => now()->toDateTimeString(),
            'stats_sync_error' => null,
        ]);

        Cache::put($cacheKey, $snapshot, now()->addHours((int) config('ghl.coach_database.cache_hours', 12)));

        return $snapshot;
    }


    protected function recruitingStatsCsvPath(User $user): string
    {
        return storage_path('app/recruiting/stats/user_' . $user->id . '/contacts.csv');
    }

    protected function readRecruitingStatsCsv(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $handle = fopen($path, 'r');
        if (! $handle) {
            return [];
        }

        $headers = fgetcsv($handle) ?: [];
        $headers = array_map(fn ($header): string => trim((string) $header), $headers);
        $rows = [];

        while (($values = fgetcsv($handle)) !== false) {
            $row = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $value = $values[$index] ?? null;
                if (in_array($header, ['tags', 'tags_json', 'list_keys', 'custom_fields_json'], true)) {
                    $decoded = json_decode((string) $value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $row[$header === 'tags_json' ? 'tags' : $header] = $decoded;
                        continue;
                    }
                }

                $row[$header] = $value;
            }

            if (! isset($row['tags']) && isset($row['tags_json']) && is_array($row['tags_json'])) {
                $row['tags'] = $row['tags_json'];
            }

            if (isset($row['custom_fields_json']) && is_array($row['custom_fields_json'])) {
                $row = array_merge($row, $this->flattenRecruitingCustomFieldsFromDecodedJson($row['custom_fields_json']));
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    public function actionTags(?User $user = null, array $customListTags = []): array
    {
        $defaults = [
            $this->savedSchoolTag(),
            $this->favoriteSchoolTag(),
            $this->savedCoachTag(),
            $this->favoriteCoachTag(),
        ];

        $listTags = collect($this->listDefinitions($user, null, $customListTags))
            ->pluck('tag')
            ->filter()
            ->values()
            ->all();

        return collect(array_merge($defaults, $listTags, $customListTags))
            ->map(fn ($tag): string => trim((string) $tag))
            ->filter()
            ->unique(fn (string $tag): string => strtolower($tag))
            ->values()
            ->all();
    }

    public function getContactsByTagsForUser(User $user, array $tags): array
    {
        $result = $this->goHighLevelService->getContactsByTagsForUser($user, $tags);

        $coaches = collect($result['contacts'] ?? [])
            ->filter(fn ($coach): bool => is_array($coach))
            ->map(fn (array $coach): array => $this->slimCoach($coach))
            ->filter(fn (array $coach): bool => filled($coach['id'] ?? null) || filled($coach['email'] ?? null) || filled($coach['name'] ?? null))
            ->unique(fn (array $coach): string => $this->coachUniqueKey($coach))
            ->values();

        return [
            'success' => (bool) ($result['success'] ?? false),
            'contacts' => $coaches->all(),
            'count' => $coaches->count(),
            'by_tag' => $result['by_tag'] ?? [],
            'error' => $result['error'] ?? null,
            'debug' => $result['debug'] ?? [],
        ];
    }

    public function buildDashboardFromCoaches(array $coachRows, ?User $user = null, array $customListTags = []): array
    {
        $coaches = collect($coachRows)
            ->filter(fn ($coach): bool => is_array($coach))
            ->map(fn (array $coach): array => $this->slimCoach($coach))
            ->filter(fn (array $coach): bool => $this->coachHasSchoolReference($coach))
            ->unique(fn (array $coach): string => $this->coachUniqueKey($coach))
            ->values();

        $customListTags = $this->normalizeCustomListTags($customListTags ?? []);
        $schools = $this->buildSchools($coaches, $user, $customListTags);

        return [
            'coaches' => $coaches->all(),
            'schools' => $schools->all(),
            'lists' => $this->buildLists($schools, $user, $coaches, $customListTags),
            'stats' => $this->buildStats($coaches, $schools),
            'top_schools' => $this->topEngagedSchools($schools),
        ];
    }

    public function updateContactsWithTag(User $user, array $contactIds, string $tag, string $type): array
    {
        $contactIds = collect($contactIds)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($contactIds)) {
            return ['success' => false, 'error' => 'No contacts found for this school.'];
        }

        return $this->goHighLevelService->updateContactTagsForUser(
            user: $user,
            contactIds: $contactIds,
            tags: [$tag],
            type: $type,
        );
    }

    public function savedSchoolTag(): string
    {
        return config('ghl.coach_database.tags.saved_school', 'saved school');
    }

    public function favoriteSchoolTag(): string
    {
        return config('ghl.coach_database.tags.favorite_school', 'favorite school');
    }

    public function listTagForKey(string $listKey, ?User $user = null): ?string
    {
        return $this->listTag($listKey, $user);
    }

    public function getDashboard(User $user): array
    {
        if (! config('ghl.coach_database.enabled', true)) {
            return $this->locked('Recruiting Center is currently disabled.');
        }

        if (! $this->canAccess($user)) {
            return $this->locked('Coach Database is available on paid plans.');
        }

        if (! $this->hasGhlConnection($user)) {
            return [
                'allowed' => false,
                'locked' => false,
                'reason' => 'Missing recruiting data connection.',
                'coaches' => [],
                'schools' => [],
                'lists' => $this->emptyLists(),
                'stats' => $this->emptyStats(),
                'top_schools' => [],
            ];
        }

        $result = $this->goHighLevelService->getCoachContactsForUser($user);

        $coaches = collect($result['contacts'] ?? [])
            ->filter(fn ($coach): bool => is_array($coach))
            ->map(fn (array $coach): array => $this->slimCoach($coach))
            ->filter(fn (array $coach): bool => $this->coachHasSchoolReference($coach))
            ->unique(fn (array $coach): string => $this->coachUniqueKey($coach))
            ->values();

        $schools = $this->buildSchools($coaches, $user);

        return [
            'allowed' => true,
            'locked' => false,
            'reason' => null,
            'coaches' => $coaches->values()->all(),
            'schools' => $schools->values()->all(),
            'lists' => $this->buildLists($schools, $user, $coaches),
            'stats' => $this->buildStats($coaches, $schools),
            'top_schools' => $this->topEngagedSchools($schools),
            'error' => $result['error'] ?? null,
        ];
    }

    public function saveCoach(User $user, string $contactId): array
    {
        return $this->goHighLevelService->addTagsToContactForUser($user, $contactId, [
            config('ghl.coach_database.tags.saved_coach', 'saved coach'),
        ]);
    }

    public function unsaveCoach(User $user, string $contactId): array
    {
        return $this->goHighLevelService->removeTagsFromContactForUser($user, $contactId, [
            config('ghl.coach_database.tags.saved_coach', 'saved coach'),
        ]);
    }

    public function favoriteCoach(User $user, string $contactId): array
    {
        return $this->goHighLevelService->addTagsToContactForUser($user, $contactId, [
            config('ghl.coach_database.tags.favorite_coach', 'favorite coach'),
        ]);
    }

    public function unfavoriteCoach(User $user, string $contactId): array
    {
        return $this->goHighLevelService->removeTagsFromContactForUser($user, $contactId, [
            config('ghl.coach_database.tags.favorite_coach', 'favorite coach'),
        ]);
    }

    public function saveSchool(User $user, string $school): array
    {
        return $this->updateSchoolTag($user, $school, config('ghl.coach_database.tags.saved_school', 'saved school'), 'add');
    }

    public function unsaveSchool(User $user, string $school): array
    {
        return $this->updateSchoolTag($user, $school, config('ghl.coach_database.tags.saved_school', 'saved school'), 'remove');
    }

    public function favoriteSchool(User $user, string $school): array
    {
        return $this->updateSchoolTag($user, $school, config('ghl.coach_database.tags.favorite_school', 'favorite school'), 'add');
    }

    public function unfavoriteSchool(User $user, string $school): array
    {
        return $this->updateSchoolTag($user, $school, config('ghl.coach_database.tags.favorite_school', 'favorite school'), 'remove');
    }

    public function addSchoolToList(User $user, string $school, string $listKey): array
    {
        $tag = $this->listTag($listKey, $user);

        if (! $tag) {
            return ['success' => false, 'error' => 'Unknown list.'];
        }

        return $this->updateSchoolTag($user, $school, $tag, 'add');
    }

    public function removeSchoolFromList(User $user, string $school, string $listKey): array
    {
        $tag = $this->listTag($listKey, $user);

        if (! $tag) {
            return ['success' => false, 'error' => 'Unknown list.'];
        }

        return $this->updateSchoolTag($user, $school, $tag, 'remove');
    }

    protected function updateSchoolTag(User $user, string $school, string $tag, string $type): array
    {
        $dashboard = $this->getDashboard($user);

        $contactIds = collect($dashboard['coaches'] ?? [])
            ->filter(fn (array $coach): bool => strtolower(trim((string) ($coach['school'] ?? ''))) === strtolower(trim($school)))
            ->pluck('id')
            ->filter()
            ->values()
            ->all();

        if (empty($contactIds)) {
            return ['success' => false, 'error' => 'No contacts found for this school.'];
        }

        return $this->goHighLevelService->updateContactTagsForUser(
            user: $user,
            contactIds: $contactIds,
            tags: [$tag],
            type: $type,
        );
    }

    protected function coachUniqueKey(array $coach): string
    {
        foreach (['id', 'contact_id', 'contactId', 'email'] as $key) {
            $value = trim((string) ($coach[$key] ?? ''));
            if ($value !== '') {
                return strtolower($key . ':' . $value);
            }
        }

        $name = strtolower(trim((string) ($coach['name'] ?? collect([$coach['first_name'] ?? null, $coach['last_name'] ?? null])->filter()->implode(' '))));
        $school = $this->normalizeSchoolKey((string) ($coach['school'] ?? $coach['school_name'] ?? $coach['company_name'] ?? ''));
        $title = strtolower(trim((string) ($coach['title'] ?? $coach['position'] ?? '')));

        return 'fallback:' . md5($name . '|' . $school . '|' . $title . '|' . json_encode($coach));
    }

    protected function normalizeSchoolKey(string $value): string
    {
        $value = strtolower(trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $value = preg_replace('/\b(the|university|college|school|athletics|athletic|department|of|at)\b/i', ' ', $value) ?: $value;
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?: $value;
        $value = preg_replace('/\s+/', ' ', $value) ?: $value;

        return trim($value);
    }

    /**
     * Keep contacts that can be joined to a school by either side of the Recruiting Center model:
     * an official Business/Company association ID or a populated school/business name.
     */
    protected function coachHasSchoolReference(array $coach): bool
    {
        $hasIdentity = filled($coach['id'] ?? $coach['contact_id'] ?? $coach['email'] ?? $coach['name'] ?? null);
        $hasBusinessId = filled($coach['business_id'] ?? $coach['company_id'] ?? $coach['ghl_business_id'] ?? $coach['school_id'] ?? null);
        $hasSchoolName = collect([
            $coach['school'] ?? null,
            $coach['school_name'] ?? null,
            $coach['company_name'] ?? null,
            $coach['business_name'] ?? null,
            $coach['school_or_company'] ?? null,
        ])->merge(is_array($coach['school_aliases'] ?? null) ? $coach['school_aliases'] : [])
            ->contains(fn ($value): bool => filled($value));

        return $hasIdentity && ($hasBusinessId || $hasSchoolName);
    }


    protected function buildSchools(Collection $coaches, ?User $user = null, array $customListTags = []): Collection
    {
        $listConfigs = $this->listDefinitions($user, $coaches, $customListTags);

        return $coaches
            ->filter(fn (array $coach): bool => $this->coachHasSchoolReference($coach))
            ->groupBy(function (array $coach): string {
                $businessId = trim((string) ($coach['business_id'] ?? $coach['company_id'] ?? $coach['ghl_business_id'] ?? $coach['school_id'] ?? ''));
                $schoolName = trim((string) ($coach['school'] ?? $coach['school_name'] ?? $coach['company_name'] ?? ''));
                $schoolKey = $this->normalizeSchoolKey($schoolName);

                // Prefer normalized school name so duplicate Recruiting Center business IDs for the
                // same visible school do not split Discover into one-coach rows.
                if ($schoolKey !== '') {
                    return 'school:' . $schoolKey;
                }

                return $businessId !== '' ? 'business:' . strtolower($businessId) : 'unknown:' . md5(json_encode($coach));
            })
            ->map(function (Collection $schoolCoaches) use ($listConfigs): array {
                $schoolCoaches = $schoolCoaches
                    ->filter(fn ($coach): bool => is_array($coach))
                    ->unique(fn (array $coach): string => $this->coachUniqueKey($coach))
                    ->values();

                $first = $schoolCoaches->first() ?: [];
                $school = trim((string) ($first['school'] ?? $first['school_name'] ?? $first['company_name'] ?? 'Unnamed School'));
                $businessId = trim((string) ($first['business_id'] ?? $first['company_id'] ?? $first['school_id'] ?? ''));

                $headCoach = $schoolCoaches->first(function (array $coach): bool {
                    return str_contains(strtolower((string) ($coach['title'] ?? $coach['position'] ?? '')), 'head');
                }) ?: $first;

                $listKeys = collect($listConfigs)
                    ->filter(function (array $config) use ($schoolCoaches): bool {
                        $tag = strtolower(trim((string) ($config['tag'] ?? '')));

                        if (! $tag) {
                            return false;
                        }

                        return $schoolCoaches->contains(fn (array $coach): bool => $this->hasTag($coach['tags'] ?? [], $tag));
                    })
                    ->keys()
                    ->values()
                    ->all();

                $score = $this->schoolEngagementScore($schoolCoaches);
                $logoUrl = $schoolCoaches
                    ->map(fn (array $coach): ?string => $coach['school_logo_url'] ?? $coach['business_logo_url'] ?? $coach['logo_url'] ?? null)
                    ->filter(fn (?string $url): bool => filled($url))
                    ->first();

                return [
                    'id' => $businessId !== '' ? $businessId : str($school)->slug()->toString(),
                    'business_id' => $businessId !== '' ? $businessId : null,
                    'name' => $school,
                    'logo_url' => $logoUrl,
                    'school_logo_url' => $logoUrl,
                    'business_logo_url' => $logoUrl,
                    'conference' => $first['conference'] ?? null,
                    'division' => $first['division'] ?? null,
                    'state' => $first['state'] ?? null,
                    'city' => $first['city'] ?? null,
                    'coach_count' => $schoolCoaches->count(),
                    'coaches_count' => $schoolCoaches->count(),
                    'coach_keys' => $schoolCoaches
                        ->map(fn (array $coach): string => $this->coachUniqueKey($coach))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                    'coach_ids' => $schoolCoaches
                        ->map(fn (array $coach): string => trim((string) ($coach['id'] ?? $coach['contact_id'] ?? '')))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                    'coach_emails' => $schoolCoaches
                        ->map(fn (array $coach): string => strtolower(trim((string) ($coach['email'] ?? ''))))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                    'coaches_preview' => $schoolCoaches->take(3)->map(fn (array $coach): array => $this->slimHeadCoach($coach))->values()->all(),
                    'head_coach' => $this->slimHeadCoach($headCoach),
                    'is_saved' => $schoolCoaches->contains(fn (array $coach): bool => $this->coachHasSavedSchoolFlag($coach)),
                    'is_favorite' => $schoolCoaches->contains(fn (array $coach): bool => $this->coachHasFavoriteSchoolFlag($coach)),
                    // Keep school totals consistent with the exact contact rows used by
                    // the Profile Views and Coach Engagement drawers. Aggregate custom
                    // fields such as school_click_count are not added again per coach.
                    'profile_views' => $schoolCoaches->sum(function (array $coach): int {
                        $platformTotal = (int) ($coach['view_profile_website'] ?? 0)
                            + (int) ($coach['view_profile_instagram'] ?? 0)
                            + (int) ($coach['view_profile_youtube'] ?? 0)
                            + (int) ($coach['view_profile_x'] ?? 0)
                            + (int) ($coach['view_profile_email_link'] ?? 0)
                            + (int) ($coach['view_profile_qr'] ?? 0);

                        return max(
                            (int) ($coach['profile_view_count'] ?? 0),
                            (int) ($coach['view_profile_total'] ?? 0),
                            $platformTotal,
                            (bool) ($coach['viewed_profile'] ?? false) ? 1 : 0,
                        );
                    }),
                    'profile_view_unique_contacts' => $schoolCoaches
                        ->filter(function (array $coach): bool {
                            $platformTotal = (int) ($coach['view_profile_website'] ?? 0)
                                + (int) ($coach['view_profile_instagram'] ?? 0)
                                + (int) ($coach['view_profile_youtube'] ?? 0)
                                + (int) ($coach['view_profile_x'] ?? 0)
                                + (int) ($coach['view_profile_email_link'] ?? 0)
                                + (int) ($coach['view_profile_qr'] ?? 0);

                            return max(
                                (int) ($coach['profile_view_count'] ?? 0),
                                (int) ($coach['view_profile_total'] ?? 0),
                                $platformTotal,
                                (bool) ($coach['viewed_profile'] ?? false) ? 1 : 0,
                            ) > 0;
                        })
                        ->unique(fn (array $coach): string => $this->coachUniqueKey($coach))
                        ->count(),
                    'profile_view_school_clicks' => $schoolCoaches->sum(function (array $coach): int {
                        $platformTotal = (int) ($coach['view_profile_website'] ?? 0)
                            + (int) ($coach['view_profile_instagram'] ?? 0)
                            + (int) ($coach['view_profile_youtube'] ?? 0)
                            + (int) ($coach['view_profile_x'] ?? 0)
                            + (int) ($coach['view_profile_email_link'] ?? 0)
                            + (int) ($coach['view_profile_qr'] ?? 0);

                        return max(
                            (int) ($coach['profile_view_count'] ?? 0),
                            (int) ($coach['view_profile_total'] ?? 0),
                            $platformTotal,
                            (bool) ($coach['viewed_profile'] ?? false) ? 1 : 0,
                        );
                    }),
                    'highlight_views' => $schoolCoaches->sum(fn (array $coach): int => max((int) ($coach['highlight_view_count'] ?? 0), (bool) ($coach['viewed_highlights'] ?? false) ? 1 : 0)),
                    'replies' => $schoolCoaches->sum(fn (array $coach): int => max((int) ($coach['coach_reply_count'] ?? 0), (bool) ($coach['replied'] ?? false) ? 1 : 0)),
                    'trigger_link_clicks' => $schoolCoaches->sum(function (array $coach): int {
                        $platformTotal = (int) ($coach['email_click_count'] ?? 0)
                            + (int) ($coach['website_click_count'] ?? 0)
                            + (int) ($coach['instagram_click_count'] ?? 0)
                            + (int) ($coach['youtube_click_count'] ?? 0)
                            + (int) ($coach['x_click_count'] ?? 0);

                        return $platformTotal > 0
                            ? $platformTotal
                            : max((int) ($coach['trigger_link_click_count'] ?? 0), (bool) ($coach['trigger_link_clicked'] ?? false) ? 1 : 0);
                    }),
                    'engagement_score' => $score,
                    'list_keys' => $listKeys,
                ];
            })
            ->sortBy('name')
            ->values();
    }

    protected function buildLists(Collection $schools, ?User $user = null, ?Collection $coaches = null, array $customListTags = []): array
    {
        return collect($this->listDefinitions($user, $coaches, $customListTags))
            ->map(function (array $config, string $key) use ($schools): array {
                $items = $schools
                    ->filter(fn (array $school): bool => in_array($key, $school['list_keys'] ?? [], true))
                    ->values();

                return [
                    'key' => $key,
                    'label' => $config['label'] ?? str($key)->headline()->toString(),
                    'description' => $config['description'] ?? null,
                    'tag' => $config['tag'] ?? null,
                    'custom' => (bool) ($config['custom'] ?? false),
                    'color' => $config['color'] ?? '#ff6338',
                    'schools_count' => $items->count(),
                    'coaches_count' => $items->sum('coach_count'),
                    'schools' => $items
                        ->map(fn (array $school): array => [
                            'id' => $school['id'] ?? null,
                            'name' => $school['name'] ?? null,
                            'logo_url' => $school['logo_url'] ?? $school['school_logo_url'] ?? $school['business_logo_url'] ?? null,
                            'conference' => $school['conference'] ?? null,
                            'division' => $school['division'] ?? null,
                            'coach_count' => $school['coach_count'] ?? 0,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    protected function buildStats(Collection $coaches, Collection $schools): array
    {
        $coachKey = fn (array $coach): string => $this->coachUniqueKey($coach);
        $schoolKey = function (array $coach): string {
            $businessId = strtolower(trim((string) ($coach['business_id'] ?? $coach['company_id'] ?? $coach['school_id'] ?? '')));
            if ($businessId !== '') {
                return 'business:' . $businessId;
            }

            $name = $this->normalizeSchoolKey((string) ($coach['school'] ?? $coach['school_name'] ?? $coach['company_name'] ?? ''));
            return $name !== '' ? 'school:' . $name : '';
        };

        $profileTotalFor = function (array $coach): int {
            $platformTotal = (int) ($coach['view_profile_website'] ?? 0)
                + (int) ($coach['view_profile_instagram'] ?? 0)
                + (int) ($coach['view_profile_youtube'] ?? 0)
                + (int) ($coach['view_profile_x'] ?? 0)
                + (int) ($coach['view_profile_email_link'] ?? 0)
                + (int) ($coach['view_profile_qr'] ?? 0);

            return max(
                (int) ($coach['view_profile_total'] ?? 0),
                (int) ($coach['profile_view_count'] ?? 0),
                $platformTotal,
                (bool) ($coach['viewed_profile'] ?? false) ? 1 : 0,
            );
        };

        $linkTotalFor = function (array $coach): int {
            $platformTotal = (int) ($coach['email_click_count'] ?? 0)
                + (int) ($coach['website_click_count'] ?? 0)
                + (int) ($coach['instagram_click_count'] ?? 0)
                + (int) ($coach['youtube_click_count'] ?? 0)
                + (int) ($coach['x_click_count'] ?? 0);

            // trigger_link_click_count is frequently a derived maximum rather than an
            // additional event bucket. Only use it when no platform counters exist.
            if ($platformTotal > 0) {
                return $platformTotal;
            }

            return max(
                (int) ($coach['trigger_link_click_count'] ?? 0),
                (bool) ($coach['trigger_link_clicked'] ?? false) ? 1 : 0,
            );
        };

        $coaches = $coaches
            ->filter(fn ($coach): bool => is_array($coach))
            ->unique($coachKey)
            ->values();

        $profileRows = $coaches->map(fn (array $coach): array => [
            'coach_key' => $coachKey($coach),
            'school_key' => $schoolKey($coach),
            'views' => $profileTotalFor($coach),
        ]);

        $linkRows = $coaches->map(fn (array $coach): array => [
            'coach_key' => $coachKey($coach),
            'school_key' => $schoolKey($coach),
            'clicks' => $linkTotalFor($coach),
        ]);

        $websiteViews = $coaches->sum(fn (array $coach): int => (int) ($coach['view_profile_website'] ?? 0));
        $instagramViews = $coaches->sum(fn (array $coach): int => (int) ($coach['view_profile_instagram'] ?? 0));
        $youtubeViews = $coaches->sum(fn (array $coach): int => (int) ($coach['view_profile_youtube'] ?? 0));
        $xViews = $coaches->sum(fn (array $coach): int => (int) ($coach['view_profile_x'] ?? 0));
        $emailProfileClicks = $coaches->sum(fn (array $coach): int => (int) ($coach['view_profile_email_link'] ?? 0));
        $qrProfileViews = $coaches->sum(fn (array $coach): int => (int) ($coach['view_profile_qr'] ?? 0));
        $profileViews = $profileRows->sum('views');

        $emailClicks = $coaches->sum(fn (array $coach): int => (int) ($coach['email_click_count'] ?? 0));
        $websiteClicks = $coaches->sum(fn (array $coach): int => (int) ($coach['website_click_count'] ?? 0));
        $instagramClicks = $coaches->sum(fn (array $coach): int => (int) ($coach['instagram_click_count'] ?? 0));
        $youtubeClicks = $coaches->sum(fn (array $coach): int => (int) ($coach['youtube_click_count'] ?? 0));
        $xClicks = $coaches->sum(fn (array $coach): int => (int) ($coach['x_click_count'] ?? 0));
        $linkClicks = $linkRows->sum('clicks');

        $uniqueProfileContacts = $profileRows->where('views', '>', 0)->pluck('coach_key')->unique()->count();
        $uniqueLinkContacts = $linkRows->where('clicks', '>', 0)->pluck('coach_key')->unique()->count();
        $uniqueContactClicks = $profileRows->where('views', '>', 0)->pluck('coach_key')
            ->merge($linkRows->where('clicks', '>', 0)->pluck('coach_key'))
            ->unique()
            ->count();

        $profileSchoolClicks = $profileRows
            ->filter(fn (array $row): bool => $row['school_key'] !== '' && $row['views'] > 0)
            ->sum('views');
        $linkSchoolClicks = $linkRows
            ->filter(fn (array $row): bool => $row['school_key'] !== '' && $row['clicks'] > 0)
            ->sum('clicks');
        $profileSchools = $profileRows
            ->filter(fn (array $row): bool => $row['school_key'] !== '' && $row['views'] > 0)
            ->pluck('school_key')
            ->unique()
            ->count();
        $schoolsWithClicks = $linkRows
            ->filter(fn (array $row): bool => $row['school_key'] !== '' && $row['clicks'] > 0)
            ->pluck('school_key')
            ->unique()
            ->count();

        $emailSent = $coaches->sum(fn (array $coach): int => (int) ($coach['email_sent_count'] ?? 0));
        $emailOpens = $coaches->sum(fn (array $coach): int => (int) ($coach['email_open_count'] ?? 0));
        $highlightViews = $coaches->sum(fn (array $coach): int => max((int) ($coach['highlight_view_count'] ?? 0), (bool) ($coach['viewed_highlights'] ?? false) ? 1 : 0));
        $replies = $coaches->sum(fn (array $coach): int => max((int) ($coach['coach_reply_count'] ?? 0), (bool) ($coach['replied'] ?? false) ? 1 : 0));
        $ghlContactClicks = $profileViews + $linkClicks;

        return [
            'total_schools' => $schools->count(),
            'total_coaches' => $coaches->count(),
            'saved_schools' => $schools->filter(fn (array $school): bool => $this->schoolHasSavedFlag($school))->count(),
            'favorite_schools' => $schools->filter(fn (array $school): bool => $this->schoolHasFavoriteFlag($school))->count(),
            'saved_coaches' => $coaches->filter(fn (array $coach): bool => (bool) ($coach['is_saved_coach'] ?? false))->count(),
            'favorite_coaches' => $coaches->filter(fn (array $coach): bool => (bool) ($coach['is_favorite_coach'] ?? false))->count(),
            'profile_views' => $profileViews,
            'highlight_views' => $highlightViews,
            'trigger_link_clicks' => $linkClicks,
            'coach_replies' => $replies,
            'view_profile_total' => $profileViews,
            'view_profile_website' => $websiteViews,
            'view_profile_instagram' => $instagramViews,
            'view_profile_youtube' => $youtubeViews,
            'view_profile_x' => $xViews,
            'view_profile_email_link' => $emailProfileClicks,
            'view_profile_qr' => $qrProfileViews,
            'profile_view_unique_contact_count' => $uniqueProfileContacts,
            'profile_view_unique_school_count' => $profileSchools,
            'profile_view_school_click_count' => $profileSchoolClicks,
            'profile_unique_clicks' => $uniqueProfileContacts,
            'profile_known_contact_clicks' => $profileViews,
            'profile_school_clicks_total' => $profileSchoolClicks,
            'website_clicks' => $websiteClicks,
            'website_click_count' => $websiteClicks,
            'instagram_clicks' => $instagramClicks,
            'instagram_click_count' => $instagramClicks,
            'youtube_clicks' => $youtubeClicks,
            'youtube_click_count' => $youtubeClicks,
            'x_clicks' => $xClicks,
            'x_click_count' => $xClicks,
            'twitter_clicks' => $xClicks,
            'social_clicks' => $websiteClicks + $instagramClicks + $youtubeClicks + $xClicks,
            'email_sent_count' => $emailSent,
            'email_open_count' => $emailOpens,
            'email_click_count' => $emailClicks,
            'emails_sent' => $emailSent,
            'email_opens' => $emailOpens,
            'email_clicks' => $emailClicks,
            'link_clicks' => $linkClicks,
            'unique_contact_clicks' => $uniqueContactClicks,
            'unique_profile_view_contacts' => $uniqueProfileContacts,
            'unique_link_click_contacts' => $uniqueLinkContacts,
            'ghl_contact_clicks' => $ghlContactClicks,
            'contact_clicks' => $ghlContactClicks,
            'overall_school_clicks' => $linkSchoolClicks,
            'school_clicks' => $linkSchoolClicks,
            'school_clicks_total' => $linkSchoolClicks,
            'schools_with_clicks' => $schoolsWithClicks,
            'school_profile_views' => $profileSchoolClicks,
        ];
    }

    protected function topEngagedSchools(Collection $schools): array
    {
        return $schools
            ->filter(fn (array $school): bool => (int) ($school['engagement_score'] ?? 0) > 0)
            ->sortByDesc('engagement_score')
            ->take(5)
            ->map(fn (array $school): array => [
                'id' => $school['id'] ?? null,
                'name' => $school['name'] ?? null,
                'conference' => $school['conference'] ?? null,
                'division' => $school['division'] ?? null,
                'logo_url' => $school['logo_url'] ?? $school['school_logo_url'] ?? $school['business_logo_url'] ?? null,
                'school_logo_url' => $school['school_logo_url'] ?? $school['logo_url'] ?? $school['business_logo_url'] ?? null,
                'business_logo_url' => $school['business_logo_url'] ?? $school['logo_url'] ?? $school['school_logo_url'] ?? null,
                'coach_count' => $school['coach_count'] ?? 0,
                'profile_views' => $school['profile_views'] ?? 0,
                'profile_view_unique_contacts' => $school['profile_view_unique_contacts'] ?? 0,
                'profile_view_school_clicks' => $school['profile_view_school_clicks'] ?? ($school['profile_views'] ?? 0),
                'highlight_views' => $school['highlight_views'] ?? 0,
                'trigger_link_clicks' => $school['trigger_link_clicks'] ?? 0,
                'replies' => $school['replies'] ?? 0,
                'lead_score' => $school['engagement_score'] ?? 0,
                'engagement_score' => $school['engagement_score'] ?? 0,
            ])
            ->values()
            ->all();
    }

    protected function schoolHasFavoriteFlag(array $school): bool
    {
        if ((bool) ($school['is_favorite'] ?? false) || (bool) ($school['is_favorite_school'] ?? false)) {
            return true;
        }

        return collect($school['list_keys'] ?? [])
            ->map(fn ($key): string => strtolower(trim((string) $key)))
            ->contains(fn (string $key): bool => str_contains($key, 'favorite'));
    }

    protected function schoolHasSavedFlag(array $school): bool
    {
        if ((bool) ($school['is_saved'] ?? false) || (bool) ($school['is_saved_school'] ?? false)) {
            return true;
        }

        return collect($school['list_keys'] ?? [])
            ->map(fn ($key): string => strtolower(trim((string) $key)))
            ->contains(fn (string $key): bool => str_contains($key, 'saved'));
    }

    protected function coachHasFavoriteSchoolFlag(array $coach): bool
    {
        if ((bool) ($coach['is_favorite_school'] ?? false)) {
            return true;
        }

        return $this->hasTag($coach['tags'] ?? [], $this->favoriteSchoolTag())
            || collect($coach['tags'] ?? [])
                ->map(fn ($tag): string => strtolower(trim((string) (is_array($tag) ? ($tag['tag'] ?? $tag['name'] ?? $tag['value'] ?? '') : $tag))))
                ->contains(fn (string $tag): bool => str_contains($tag, 'favorite school'));
    }

    protected function coachHasSavedSchoolFlag(array $coach): bool
    {
        if ((bool) ($coach['is_saved_school'] ?? false)) {
            return true;
        }

        return $this->hasTag($coach['tags'] ?? [], $this->savedSchoolTag())
            || collect($coach['tags'] ?? [])
                ->map(fn ($tag): string => strtolower(trim((string) (is_array($tag) ? ($tag['tag'] ?? $tag['name'] ?? $tag['value'] ?? '') : $tag))))
                ->contains(fn (string $tag): bool => str_contains($tag, 'saved school'));
    }

    protected function schoolEngagementScore(Collection $schoolCoaches): int
    {
        return $schoolCoaches->sum(function (array $coach): int {
            return
                ((int) ($coach['view_profile_total'] ?? 0) * 5)
                + ((int) ($coach['view_profile_website'] ?? 0) * 3)
                + ((int) ($coach['view_profile_instagram'] ?? 0) * 3)
                + ((int) ($coach['view_profile_youtube'] ?? 0) * 4)
                + ((int) ($coach['view_profile_x'] ?? 0) * 3)
                + ((int) ($coach['view_profile_email_link'] ?? 0) * 4)
                + ((int) ($coach['website_click_count'] ?? 0) * 4)
                + ((int) ($coach['instagram_click_count'] ?? 0) * 4)
                + ((int) ($coach['youtube_click_count'] ?? 0) * 5)
                + ((int) ($coach['x_click_count'] ?? 0) * 4)
                + ((int) ($coach['email_open_count'] ?? 0) * 2)
                + ((int) ($coach['email_click_count'] ?? 0) * 4)
                + ((bool) ($coach['viewed_profile'] ?? false) ? 5 : 0)
                + ((bool) ($coach['viewed_highlights'] ?? false) ? 4 : 0)
                + ((bool) ($coach['trigger_link_clicked'] ?? false) ? 3 : 0)
                + ((bool) ($coach['replied'] ?? false) ? 10 : 0)
                + ((bool) ($coach['engaged'] ?? false) ? 3 : 0)
                + ((bool) ($coach['is_favorite_coach'] ?? false) ? 2 : 0)
                + ((bool) ($coach['is_saved_coach'] ?? false) ? 1 : 0);
        });
    }

    protected function flattenRecruitingCustomFieldsFromDecodedJson($fields): array
    {
        if (! is_array($fields)) {
            return [];
        }

        $flattened = [];
        $isList = array_keys($fields) === range(0, count($fields) - 1);

        if ($isList) {
            foreach ($fields as $field) {
                if (! is_array($field)) {
                    continue;
                }

                $fieldKey = $field['fieldKey']
                    ?? $field['field_key']
                    ?? $field['key']
                    ?? $field['name']
                    ?? $field['label']
                    ?? $field['id']
                    ?? $field['customFieldId']
                    ?? $field['custom_field_id']
                    ?? $field['fieldId']
                    ?? $field['field_id']
                    ?? null;

                $normalizedKey = $this->normalizeRecruitingCustomFieldKey($fieldKey);
                if ($normalizedKey === '') {
                    continue;
                }

                $value = $this->recruitingScalarFromCustomFieldValue($field);
                $flattened[$normalizedKey] = $value;
                $flattened['custom_' . $normalizedKey] = $value;
                $flattened['custom_contact_' . $normalizedKey] = $value;
            }

            return $flattened;
        }

        foreach ($fields as $key => $value) {
            $normalizedKey = $this->normalizeRecruitingCustomFieldKey($key);
            if ($normalizedKey !== '') {
                $scalar = $this->recruitingScalarFromCustomFieldValue($value);
                $flattened[$normalizedKey] = $scalar;
                $flattened['custom_' . $normalizedKey] = $scalar;
                $flattened['custom_contact_' . $normalizedKey] = $scalar;
            }

            if (is_array($value)) {
                $flattened = array_merge($flattened, $this->flattenRecruitingCustomFieldsFromDecodedJson($value));
            }
        }

        return $flattened;
    }

    protected function recruitingScalarFromCustomFieldValue($value)
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach (['value', 'field_value', 'fieldValue', 'valueString', 'stringValue', 'text', 'number', 'numericValue', 'label'] as $key) {
            if (array_key_exists($key, $value) && ! is_array($value[$key])) {
                return $value[$key];
            }
        }

        foreach ($value as $child) {
            if (! is_array($child)) {
                return $child;
            }
        }

        return json_encode($value);
    }

    protected function normalizeRecruitingCustomFieldKey($key): string
    {
        if (! is_scalar($key)) {
            return '';
        }

        $key = strtolower(trim((string) $key));
        $key = trim(str_replace(['{{', '}}'], '', $key), '{} ' . "\t\n\r\0\x0B");
        $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?: '';

        return trim($key, '_');
    }

    protected function recruitingIntFromRow(array $row, array $keys): int
    {
        $max = 0;

        foreach ($keys as $key) {
            $normalized = strtolower(trim((string) $key));
            $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?: '';
            $normalized = trim($normalized, '_');

            foreach (array_unique([$key, $normalized, 'custom_' . $normalized, 'custom_contact_' . $normalized, 'custom_business_' . $normalized]) as $candidateKey) {
                if (! array_key_exists($candidateKey, $row)) {
                    continue;
                }

                $value = $row[$candidateKey];
                if (is_numeric($value)) {
                    $max = max($max, (int) $value);
                    continue;
                }

                if (is_string($value) && trim($value) !== '') {
                    $numbers = preg_replace('/[^0-9.-]/', '', $value);
                    if ($numbers !== '' && is_numeric($numbers)) {
                        $max = max($max, (int) $numbers);
                    }
                }
            }
        }

        if (isset($row['custom_fields_json']) && is_array($row['custom_fields_json'])) {
            $flattened = $this->flattenRecruitingCustomFieldsFromDecodedJson($row['custom_fields_json']);

            if (! empty($flattened)) {
                $max = max($max, $this->recruitingIntFromRow($flattened, $keys));
            }
        }

        return $max;
    }

    protected function slimCoach(array $coach): array
    {
        $normalizeKey = function (mixed $key): string {
            $key = strtolower(trim((string) $key));
            $key = trim(str_replace(['{{', '}}'], '', $key), '{} ' . "\t\n\r\0\x0B");
            $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?: $key;
            return trim($key, '_');
        };

        $fieldValue = function (mixed $field): mixed {
            if (is_array($field)) {
                foreach (['value', 'field_value', 'valueString', 'value_string', 'stringValue', 'text', 'name', 'label'] as $valueKey) {
                    if (array_key_exists($valueKey, $field) && filled($field[$valueKey])) {
                        return $field[$valueKey];
                    }
                }
            }

            return $field;
        };

        $decodeMaybeJson = function (mixed $value): mixed {
            if (! is_string($value)) {
                return $value;
            }

            $trimmed = trim($value);
            if ($trimmed === '' || ! in_array($trimmed[0], ['{', '['], true)) {
                return $value;
            }

            $decoded = json_decode($trimmed, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        };

        $valueFor = function (array $row, array $keys) use ($normalizeKey, $fieldValue, $decodeMaybeJson): mixed {
            $normalizedKeys = collect($keys)
                ->map(fn ($key): string => $normalizeKey($key))
                ->filter()
                ->unique()
                ->values()
                ->all();

            foreach ($keys as $key) {
                $value = data_get($row, $key);
                if (filled($value) && ! is_array($value)) {
                    return $value;
                }
            }

            $containers = [$row];

            foreach (['custom_fields_json', 'customFieldsJson', 'custom_fields', 'customFields', 'customFieldValues', 'custom_values', 'customValues', 'raw_contact', 'contact', 'business', 'company'] as $containerKey) {
                $container = data_get($row, $containerKey);
                $container = $decodeMaybeJson($container);

                if (is_array($container)) {
                    $containers[] = $container;
                }
            }

            foreach ($containers as $container) {
                foreach ($container as $rawKey => $rawValue) {
                    $identifiers = [$rawKey];

                    if (is_array($rawValue)) {
                        foreach (['id', '_id', 'key', 'name', 'label', 'fieldKey', 'field_key', 'customFieldId', 'custom_field_id', 'fieldId', 'field_id', 'slug', 'placeholder'] as $identifierKey) {
                            $identifiers[] = $rawValue[$identifierKey] ?? null;
                        }
                    }

                    $matches = collect($identifiers)
                        ->filter(fn ($identifier): bool => filled($identifier))
                        ->map(fn ($identifier): string => $normalizeKey($identifier))
                        ->intersect($normalizedKeys)
                        ->isNotEmpty();

                    if (! $matches) {
                        continue;
                    }

                    $value = $fieldValue($rawValue);
                    if (filled($value) && ! is_array($value)) {
                        return $value;
                    }
                }
            }

            return null;
        };

        $tagsRaw = $coach['tags'] ?? $valueFor($coach, ['tags', 'contact_tags', 'tag_list']) ?? [];
        if (is_string($tagsRaw)) {
            $decodedTags = json_decode($tagsRaw, true);
            $tagsRaw = json_last_error() === JSON_ERROR_NONE && is_array($decodedTags)
                ? $decodedTags
                : preg_split('/[,|]+/', $tagsRaw);
        }
        $tags = collect(is_array($tagsRaw) ? $tagsRaw : [])
            ->map(fn ($tag): string => trim((string) $tag))
            ->filter()
            ->unique(fn (string $tag): string => strtolower($tag))
            ->values()
            ->all();

        $explicitSchool = $valueFor($coach, [
            'custom_school_name', 'contact_school', 'school_custom_field', 'school_name', 'schoolName',
            'School Name', 'School', 'college', 'college_name', 'College Name', 'University',
            'school', 'school_or_company',
        ]);
        $companyName = $valueFor($coach, [
            'company_name', 'companyName', 'company.name', 'raw_contact.companyName', 'raw_contact.company_name',
            'organization', 'organization_name', 'Company Name',
        ]);
        $businessName = $valueFor($coach, [
            'business_name', 'businessName', 'business.name', 'raw_contact.businessName', 'raw_contact.business_name',
            'Business Name',
        ]);
        $school = $explicitSchool ?: $businessName ?: $companyName;

        $businessId = $valueFor($coach, [
            'business_id', 'businessId', 'business_id.id', 'company_id', 'companyId', 'ghl_business_id', 'school_id', 'schoolId',
            'business.id', 'company.id', 'raw_contact.businessId', 'raw_contact.companyId', 'raw_contact.business.id', 'raw_contact.company.id',
            'Business ID', 'Business Id', 'Company ID', 'Company Id', 'School ID', 'School Id',
        ]);

        $conference = $valueFor($coach, ['conference', 'school_conference', 'School Conference', 'Conference']);
        $division = $valueFor($coach, ['division', 'school_division', 'School Division', 'Division']);
        $title = $valueFor($coach, ['title', 'coach_title', 'position', 'job_title', 'Coach Title', 'Position']);
        $schoolLogo = $valueFor($coach, ['school_logo_url', 'business_logo_url', 'logo_url', 'school_logo', 'business_logo', 'logo', 'School Logo', 'Business Logo']);
        $profileTotal = $this->recruitingIntFromRow($coach, ['view_profile_total', 'profile_view_total', 'profile_views', 'profile_view_count']);
        $emailClickCount = $this->recruitingIntFromRow($coach, ['email_click_count', 'email_clicks', 'click_count']);
        $websiteClickCount = $this->recruitingIntFromRow($coach, ['website_click_count', 'website_clicks']);
        $instagramClickCount = $this->recruitingIntFromRow($coach, ['instagram_click_count', 'instagram_clicks']);
        $youtubeClickCount = $this->recruitingIntFromRow($coach, ['youtube_click_count', 'youtube_clicks']);
        $xClickCount = $this->recruitingIntFromRow($coach, ['x_click_count', 'x_clicks', 'twitter_clicks']);
        $firstName = $valueFor($coach, ['first_name', 'firstName', 'First Name']);
        $lastName = $valueFor($coach, ['last_name', 'lastName', 'Last Name']);
        $name = $valueFor($coach, ['name', 'contactName', 'Contact Name']) ?: trim((string) (($firstName ?? '') . ' ' . ($lastName ?? '')));
        $schoolAliases = collect(is_array($coach['school_aliases'] ?? null) ? $coach['school_aliases'] : [])
            ->push($school)
            ->push($companyName)
            ->push($businessName)
            ->push($coach['company_name'] ?? null)
            ->push($coach['business_name'] ?? null)
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique(fn (string $value): string => strtolower($value))
            ->values()
            ->all();

        return [
            'id' => $valueFor($coach, ['id', 'contact_id', 'contactId', 'Contact ID']) ?? null,
            'name' => $this->formatPersonName($name),
            'first_name' => $this->formatPersonName($firstName),
            'last_name' => $this->formatPersonName($lastName),
            'email' => $valueFor($coach, ['email', 'Email']) ?? null,
            'phone' => $valueFor($coach, ['phone', 'Phone']) ?? null,
            'school' => $school,
            'school_name' => $school,
            'company_name' => $companyName ?: $school,
            'business_name' => $businessName ?: $school,
            'school_or_company' => $school,
            'school_aliases' => $schoolAliases,
            'school_id' => $valueFor($coach, ['school_id', 'schoolId', 'School ID']) ?? $businessId,
            'business_id' => $businessId,
            'company_id' => $businessId,
            'school_logo_url' => $schoolLogo,
            'business_logo_url' => $schoolLogo,
            'logo_url' => $schoolLogo,
            'conference' => $conference,
            'division' => $division,
            'title' => $title,
            'sport' => $valueFor($coach, ['sport', 'coach_sport', 'Sport']) ?? null,
            'state' => $valueFor($coach, ['state', 'school_state', 'School State', 'State']) ?? null,
            'city' => $valueFor($coach, ['city', 'school_city', 'School City', 'City']) ?? null,
            'external_id' => $valueFor($coach, ['external_id', 'coach_external_id', 'externalId']) ?? null,
            'tags' => $tags,
            'is_saved_school' => (bool) ($coach['is_saved_school'] ?? $this->hasTag($tags, $this->savedSchoolTag())),
            'is_favorite_school' => (bool) ($coach['is_favorite_school'] ?? $this->hasTag($tags, $this->favoriteSchoolTag())),
            'is_saved_coach' => (bool) ($coach['is_saved_coach'] ?? $this->hasTag($tags, $this->savedCoachTag())),
            'is_favorite_coach' => (bool) ($coach['is_favorite_coach'] ?? $this->hasTag($tags, $this->favoriteCoachTag())),
            'viewed_profile' => (bool) ($coach['viewed_profile'] ?? false),
            'viewed_highlights' => (bool) ($coach['viewed_highlights'] ?? false),
            'engaged' => (bool) ($coach['engaged'] ?? false),
            'replied' => (bool) ($coach['replied'] ?? false),
            'trigger_link_clicked' => (bool) ($coach['trigger_link_clicked'] ?? false),
            'view_profile_total' => $profileTotal,
            'view_profile_website' => $this->recruitingIntFromRow($coach, ['view_profile_website', 'website_profile_views', 'website_views', 'player_website_views']),
            'view_profile_instagram' => $this->recruitingIntFromRow($coach, ['view_profile_instagram', 'instagram_profile_views', 'instagram_views', 'ig_profile_views']),
            'view_profile_youtube' => $this->recruitingIntFromRow($coach, ['view_profile_youtube', 'youtube_profile_views', 'youtube_views', 'highlight_views', 'highlights_views']),
            'view_profile_x' => $this->recruitingIntFromRow($coach, ['view_profile_x', 'x_profile_views', 'twitter_profile_views', 'x_views', 'twitter_views']),
            'view_profile_email_link' => $this->recruitingIntFromRow($coach, ['view_profile_email_link', 'email_profile_views', 'email_link_profile_views', 'profile_email_clicks']),
            'view_profile_qr' => $this->recruitingIntFromRow($coach, ['view_profile_qr', 'qr_profile_views', 'qr_clicks']),
            'profile_view_unique_contact_count' => $this->recruitingIntFromRow($coach, ['profile_view_unique_contact_count', 'unique_profile_view_count', 'unique_profile_view_contacts']),
            'profile_view_unique_school_count' => $this->recruitingIntFromRow($coach, ['profile_view_unique_school_count', 'unique_profile_view_schools']),
            'profile_view_school_click_count' => $this->recruitingIntFromRow($coach, ['profile_view_school_click_count', 'school_profile_view_count', 'school_profile_views']),
            'profile_unique_clicks' => $this->recruitingIntFromRow($coach, ['profile_unique_clicks', 'profile_view_unique_contact_count', 'unique_profile_view_count']),
            'profile_known_contact_clicks' => $this->recruitingIntFromRow($coach, ['profile_known_contact_clicks', 'view_profile_total', 'profile_view_count']),
            'profile_school_clicks_total' => $this->recruitingIntFromRow($coach, ['profile_school_clicks_total', 'profile_view_school_click_count', 'school_profile_view_count']),
            'email_sent_count' => $this->recruitingIntFromRow($coach, ['email_sent_count', 'emails_sent', 'total_emails_sent']),
            'email_open_count' => $this->recruitingIntFromRow($coach, ['email_open_count', 'email_opens', 'open_count']),
            'email_click_count' => $emailClickCount,
            'website_click_count' => $websiteClickCount,
            'instagram_click_count' => $instagramClickCount,
            'youtube_click_count' => $youtubeClickCount,
            'x_click_count' => $xClickCount,
            'unique_profile_view_count' => $this->recruitingIntFromRow($coach, ['unique_profile_view_count', 'profile_view_unique_contact_count']),
            'unique_link_click_count' => $this->recruitingIntFromRow($coach, ['unique_link_click_count']),
            'unique_click_count' => $this->recruitingIntFromRow($coach, ['unique_click_count']),
            'school_profile_view_count' => $this->recruitingIntFromRow($coach, ['school_profile_view_count', 'profile_view_school_click_count']),
            'school_link_click_count' => $this->recruitingIntFromRow($coach, ['school_link_click_count']),
            'school_click_count' => $this->recruitingIntFromRow($coach, ['school_click_count']),
            'email_delivered_count' => $this->recruitingIntFromRow($coach, ['email_delivered_count']),
            'email_failed_count' => $this->recruitingIntFromRow($coach, ['email_failed_count']),
            'last_clicked_platform' => $coach['last_clicked_platform'] ?? $coach['custom_last_clicked_platform'] ?? $coach['custom_contact_last_clicked_platform'] ?? null,
            'last_clicked_url' => $coach['last_clicked_url'] ?? $coach['custom_last_clicked_url'] ?? $coach['custom_contact_last_clicked_url'] ?? null,
            'last_profile_view_at' => $coach['last_profile_view_at'] ?? $coach['custom_last_profile_view_at'] ?? $coach['custom_contact_last_profile_view_at'] ?? null,
            'profile_view_count' => (int) max((int) ($coach['profile_view_count'] ?? 0), $profileTotal),
            'trigger_link_click_count' => (int) max((int) ($coach['trigger_link_click_count'] ?? 0), $emailClickCount, $websiteClickCount, $instagramClickCount, $youtubeClickCount, $xClickCount),
            'coach_reply_count' => $this->recruitingIntFromRow($coach, ['coach_reply_count', 'reply_count', 'replies']),
        ];
    }

    protected function slimHeadCoach(array $coach): array
    {
        return [
            'id' => $coach['id'] ?? null,
            'name' => $coach['name'] ?? null,
            'email' => $coach['email'] ?? null,
            'title' => $coach['title'] ?? $coach['position'] ?? null,
            'school' => $coach['school'] ?? $coach['school_name'] ?? $coach['company_name'] ?? null,
            'company_name' => $coach['company_name'] ?? $coach['school'] ?? $coach['school_name'] ?? null,
            'business_id' => $coach['business_id'] ?? $coach['company_id'] ?? $coach['school_id'] ?? null,
            'school_logo_url' => $coach['school_logo_url'] ?? $coach['business_logo_url'] ?? $coach['logo_url'] ?? null,
            'logo_url' => $coach['logo_url'] ?? $coach['school_logo_url'] ?? $coach['business_logo_url'] ?? null,
        ];
    }


    public function createCustomList(User $user, string $name): array
    {
        $name = trim($name);

        if ($name === '') {
            return [
                'success' => false,
                'error' => 'Enter a list name.',
            ];
        }

        $slug = Str::slug($name);

        if ($slug === '') {
            return [
                'success' => false,
                'error' => 'Use a list name with letters or numbers.',
            ];
        }

        $tag = $this->customListTagPrefix() . $slug;

        return [
            'success' => true,
            'tag' => $tag,
            'key' => 'custom:' . $slug,
            'list' => $this->formatListDefinition('custom:' . $slug, [
                'label' => $this->labelFromCustomListTag($tag, $name),
                'tag' => $tag,
                'custom' => true,
            ]),
        ];
    }

    public function savedCoachTag(): string
    {
        return config('ghl.coach_database.tags.saved_coach', 'saved coach');
    }

    public function favoriteCoachTag(): string
    {
        return config('ghl.coach_database.tags.favorite_coach', 'favorite coach');
    }

    public function customListTagPrefix(): string
    {
        return 'plyrcard:list:';
    }

    protected function listDefinitions(?User $user = null, ?Collection $coaches = null, array $customListTags = []): array
    {
        $fromRoot = config('ghl.coach_database.lists', []);
        $fromTags = collect(config('ghl.coach_database.tags.lists', []))
            ->map(fn (string $tag, string $key): array => [
                'label' => Str::of($key)->replace('_', ' ')->headline()->toString(),
                'tag' => $tag,
                'custom' => false,
            ])
            ->all();

        $defaults = [
            'dream' => ['label' => 'Dream Schools', 'tag' => 'dream school', 'custom' => false, 'color' => '#ff6338'],
            'target' => ['label' => 'Target Schools', 'tag' => 'target school', 'custom' => false, 'color' => '#3b82f6'],
            'safety' => ['label' => 'Safety Schools', 'tag' => 'safety school', 'custom' => false, 'color' => '#22c55e'],
            'camp_follow_up' => ['label' => 'Camp Follow-Up', 'tag' => 'camp follow-up', 'custom' => false, 'color' => '#f59e0b'],
            'showcase_follow_up' => ['label' => 'Showcase Follow-Up', 'tag' => 'showcase follow-up', 'custom' => false, 'color' => '#8b5cf6'],
            'general_recruiting' => ['label' => 'General Recruiting', 'tag' => 'general recruiting', 'custom' => false, 'color' => '#64748b'],
        ];

        $definitions = array_replace_recursive($defaults, $fromTags, $fromRoot);

        $tagPrefix = $this->customListTagPrefix();

        $tagsFromCoaches = ($coaches ?: collect())
            ->flatMap(fn (array $coach): array => $coach['tags'] ?? [])
            ->map(fn ($tag): string => $this->stringTagFromMixed($tag))
            ->filter(fn (string $tag): bool => str_starts_with(strtolower($tag), strtolower($tagPrefix)))
            ->values()
            ->all();

        $customListMeta = collect($customListTags)
            ->filter(fn ($row): bool => is_array($row))
            ->mapWithKeys(function (array $row): array {
                $tag = $this->stringTagFromMixed($row);

                if ($tag === '') {
                    return [];
                }

                return [strtolower($tag) => [
                    'label' => $row['label'] ?? $row['name'] ?? null,
                    'color' => $row['color'] ?? null,
                ]];
            });

        collect($customListTags)
            ->merge($tagsFromCoaches)
            ->map(fn ($tag): string => $this->stringTagFromMixed($tag))
            ->filter(fn (string $tag): bool => str_starts_with(strtolower($tag), strtolower($tagPrefix)))
            ->unique(fn (string $tag): string => strtolower($tag))
            ->each(function (string $tag) use (&$definitions, $tagPrefix, $customListMeta): void {
                $slug = Str::after(strtolower($tag), strtolower($tagPrefix));

                if ($slug === '') {
                    return;
                }

                $meta = $customListMeta->get(strtolower($tag), []);

                $definitions['custom:' . $slug] = [
                    'label' => $this->labelFromCustomListTag($tag, $meta['label'] ?? null),
                    'tag' => $tag,
                    'custom' => true,
                    'color' => $meta['color'] ?? '#ff6338',
                ];
            });

        return $definitions;
    }

    protected function listTag(string $listKey, ?User $user = null): ?string
    {
        if (str_starts_with($listKey, 'custom:')) {
            $slug = Str::slug(Str::after($listKey, 'custom:'));

            return $slug !== '' ? $this->customListTagPrefix() . $slug : null;
        }

        $definitions = $this->listDefinitions($user);

        return $definitions[$listKey]['tag'] ?? null;
    }

    protected function labelFromCustomListTag(string $tag, ?string $fallback = null): string
    {
        $slug = Str::after(strtolower(trim($tag)), strtolower($this->customListTagPrefix()));

        return filled($fallback)
            ? trim((string) $fallback)
            : Str::of($slug)->replace(['-', '_'], ' ')->headline()->toString();
    }

    protected function normalizeCustomListTags(array $tags): array
    {
        return collect($tags)
            ->map(fn ($tag): string => $this->stringTagFromMixed($tag))
            ->filter(fn (string $tag): bool => $tag !== '' && str_starts_with(strtolower($tag), strtolower($this->customListTagPrefix())))
            ->unique(fn (string $tag): string => strtolower($tag))
            ->values()
            ->all();
    }

    protected function stringTagFromMixed(mixed $tag): string
    {
        if (is_array($tag)) {
            $value = $tag['tag'] ?? $tag['name'] ?? $tag['value'] ?? $tag['id'] ?? '';

            return is_scalar($value) ? trim((string) $value) : '';
        }

        return is_scalar($tag) ? trim((string) $tag) : '';
    }

    protected function formatListDefinition(string $key, array $config): array
    {
        return [
            'key' => $key,
            'label' => $config['label'] ?? str($key)->headline()->toString(),
            'description' => $config['description'] ?? null,
            'tag' => $config['tag'] ?? null,
            'custom' => (bool) ($config['custom'] ?? false),
            'color' => $config['color'] ?? '#ff6338',
            'schools_count' => 0,
            'coaches_count' => 0,
            'schools' => [],
        ];
    }

    protected function formatPersonName(?string $name): ?string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        return Str::of($name)
            ->lower()
            ->title()
            ->replace(" Mc", " Mc")
            ->replace(" Mac", " Mac")
            ->toString();
    }

    protected function hasTag(array $tags, string $tag): bool
    {
        $needle = strtolower(trim($tag));

        return collect($tags)
            ->map(fn ($coachTag): string => strtolower(trim((string) $coachTag)))
            ->contains($needle);
    }

    protected function emptyLists(?User $user = null): array
    {
        return collect($this->listDefinitions($user))
            ->map(fn (array $config, string $key): array => $this->formatListDefinition($key, $config))
            ->values()
            ->all();
    }

    protected function emptyStats(): array
    {
        return [
            'total_schools' => 0,
            'total_coaches' => 0,
            'saved_schools' => 0,
            'favorite_schools' => 0,
            'saved_coaches' => 0,
            'favorite_coaches' => 0,
            'profile_views' => 0,
            'highlight_views' => 0,
            'trigger_link_clicks' => 0,
            'coach_replies' => 0,
            'view_profile_total' => 0,
            'view_profile_website' => 0,
            'view_profile_instagram' => 0,
            'view_profile_youtube' => 0,
            'view_profile_x' => 0,
            'view_profile_email_link' => 0,
            'view_profile_qr' => 0,
            'profile_view_unique_contact_count' => 0,
            'profile_view_unique_school_count' => 0,
            'profile_view_school_click_count' => 0,
            'profile_unique_clicks' => 0,
            'profile_known_contact_clicks' => 0,
            'profile_school_clicks_total' => 0,
            'email_sent_count' => 0,
            'email_open_count' => 0,
            'email_click_count' => 0,
            'emails_sent' => 0,
            'email_opens' => 0,
            'email_clicks' => 0,
            'link_clicks' => 0,
            'unique_contact_clicks' => 0,
            'unique_profile_view_contacts' => 0,
            'ghl_contact_clicks' => 0,
            'contact_clicks' => 0,
            'overall_school_clicks' => 0,
            'school_clicks' => 0,
            'schools_with_clicks' => 0,
            'school_profile_views' => 0,
        ];
    }

    protected function locked(string $reason): array
    {
        return [
            'allowed' => false,
            'locked' => true,
            'reason' => $reason,
            'coaches' => [],
            'schools' => [],
            'lists' => $this->emptyLists(),
            'stats' => $this->emptyStats(),
            'top_schools' => [],
        ];
    }


    public function getSchoolBusinessesPageForUser(User $user, int $skip = 0, int $limit = 50): array
    {
        return $this->goHighLevelService->getSchoolBusinessesPageForUser($user, $skip, $limit);
    }

    public function getContactsForBusinessForUser(User $user, string $businessId, int $skip = 0, int $limit = 100, ?array $school = null): array
    {
        $result = $this->goHighLevelService->getContactsForBusinessForUser($user, $businessId, $skip, $limit, $school);
        $coaches = collect($result['coaches'] ?? $result['contacts'] ?? [])
            ->filter(fn ($coach): bool => is_array($coach))
            ->map(fn (array $coach): array => $this->slimCoach($coach))
            ->values()
            ->all();

        $result['coaches'] = $coaches;
        $result['contacts'] = $coaches;

        return $result;
    }

    public function getBusinessContactCountsForUser(User $user, array $schools): array
    {
        $result = $this->goHighLevelService->getBusinessContactCountsForUser($user, $schools);

        $result['sample_coaches'] = collect($result['sample_coaches'] ?? [])
            ->map(fn ($coach): array => is_array($coach) ? $this->slimCoach($coach) : [])
            ->filter(fn (array $coach): bool => filled($coach['id'] ?? $coach['email'] ?? $coach['name'] ?? null))
            ->all();

        return $result;
    }

    public function rebuildFromSchoolCompanySnapshot(array $schools, array $coaches, ?User $user, array $customListTags = []): array
    {
        $schools = collect($schools)->filter(fn ($school): bool => is_array($school))->values();

        // Promote any roster rows already embedded in dashboard/school snapshots into
        // the one canonical coach collection. Full rosters are stripped from school
        // rows afterward so Livewire does not serialize duplicate contact payloads.
        $nestedSchoolCoaches = $schools->flatMap(function (array $school): array {
            $rows = [];
            foreach (['coaches', 'staff', 'coaching_staff', 'contacts', 'coaches_preview'] as $field) {
                foreach (is_array($school[$field] ?? null) ? $school[$field] : [] as $coach) {
                    if (is_array($coach)) {
                        $rows[] = $coach;
                    }
                }
            }

            if (is_array($school['head_coach'] ?? null)) {
                $rows[] = $school['head_coach'];
            }

            $schoolName = trim((string) ($school['name'] ?? $school['school_name'] ?? $school['company_name'] ?? $school['business_name'] ?? ''));
            $businessId = trim((string) ($school['business_id'] ?? $school['company_id'] ?? $school['ghl_business_id'] ?? $school['id'] ?? ''));
            $logo = $school['logo_url'] ?? $school['school_logo_url'] ?? $school['business_logo_url'] ?? null;

            return collect($rows)
                ->filter(fn ($coach): bool => is_array($coach))
                ->map(function (array $coach) use ($schoolName, $businessId, $school, $logo): array {
                    if ($schoolName !== '') {
                        $coach['school'] = $coach['school'] ?? $schoolName;
                        $coach['school_name'] = $coach['school_name'] ?? $schoolName;
                        $coach['company_name'] = $coach['company_name'] ?? $schoolName;
                        $coach['business_name'] = $coach['business_name'] ?? $schoolName;
                    }
                    if ($businessId !== '') {
                        $coach['business_id'] = $coach['business_id'] ?? $businessId;
                        $coach['company_id'] = $coach['company_id'] ?? $businessId;
                        $coach['school_id'] = $coach['school_id'] ?? $businessId;
                    }
                    $coach['conference'] = $coach['conference'] ?? $school['conference'] ?? null;
                    $coach['division'] = $coach['division'] ?? $school['division'] ?? null;
                    $coach['school_logo_url'] = $coach['school_logo_url'] ?? $logo;
                    $coach['business_logo_url'] = $coach['business_logo_url'] ?? $logo;
                    $coach['logo_url'] = $coach['logo_url'] ?? $logo;
                    return $coach;
                })
                ->values()
                ->all();
        });

        $coaches = collect($coaches)
            ->merge($nestedSchoolCoaches)
            ->filter(fn ($coach): bool => is_array($coach))
            ->map(fn (array $coach): array => $this->slimCoach($coach))
            ->filter(fn (array $coach): bool => filled($coach['id'] ?? $coach['email'] ?? $coach['name'] ?? null))
            ->unique(fn (array $coach): string => $this->coachUniqueKey($coach))
            ->values();

        $coachNameCandidates = function (array $coach): array {
            return collect([
                $coach['school'] ?? null,
                $coach['school_name'] ?? null,
                $coach['company_name'] ?? null,
                $coach['business_name'] ?? null,
                $coach['school_or_company'] ?? null,
                $coach['organization'] ?? null,
                data_get($coach, 'company.name'),
                data_get($coach, 'business.name'),
                data_get($coach, 'raw_contact.companyName'),
                data_get($coach, 'raw_contact.company_name'),
                data_get($coach, 'raw_contact.businessName'),
                data_get($coach, 'raw_contact.business_name'),
            ])->merge(is_array($coach['school_aliases'] ?? null) ? $coach['school_aliases'] : [])
                ->map(fn ($value): string => $this->normalizeSchoolKey((string) $value))
                ->filter()
                ->unique()
                ->values()
                ->all();
        };

        $coachBusinessCandidates = function (array $coach): array {
            return collect([
                $coach['business_id'] ?? null,
                $coach['company_id'] ?? null,
                $coach['ghl_business_id'] ?? null,
                $coach['school_id'] ?? null,
                $coach['businessId'] ?? null,
                $coach['companyId'] ?? null,
                data_get($coach, 'company.id'),
                data_get($coach, 'business.id'),
                data_get($coach, 'raw_contact.businessId'),
                data_get($coach, 'raw_contact.companyId'),
            ])->map(fn ($value): string => strtolower(trim((string) $value)))
                ->filter()
                ->unique()
                ->values()
                ->all();
        };

        // Two indexes are intentionally maintained:
        // 1. exact Recruiting Center Business IDs for contacts that are formally associated;
        // 2. normalized Business/Company/School names for contacts whose Recruiting Center
        //    association is missing even though their Business Name is populated.
        // Sets are keyed by contact identity so a contact found by both checks is
        // counted only once.
        $coachIdsByBusiness = [];
        $coachIdsByName = [];
        $headCoachByIdentity = [];
        $coachRowsByIdentity = [];

        foreach ($coaches as $coach) {
            $identity = $this->coachUniqueKey($coach);
            $coachRowsByIdentity[$identity] = $coach;

            foreach ($coachBusinessCandidates($coach) as $candidateBusinessId) {
                $coachIdsByBusiness[$candidateBusinessId][$identity] = true;
            }

            foreach ($coachNameCandidates($coach) as $candidateName) {
                $coachIdsByName[$candidateName][$identity] = true;
            }

            if (str_contains(strtolower((string) ($coach['title'] ?? $coach['position'] ?? '')), 'head')) {
                $headCoachByIdentity[$identity] = $coach;
            }
        }

        $builtSchools = $this->buildSchools($coaches, $user, $customListTags);
        $schoolsByName = $builtSchools->keyBy(fn (array $school): string => $this->normalizeSchoolKey((string) ($school['name'] ?? '')));
        $schoolsByBusiness = $builtSchools
            ->filter(fn (array $school): bool => filled($school['business_id'] ?? null))
            ->keyBy(fn (array $school): string => strtolower(trim((string) ($school['business_id'] ?? ''))));

        $enrichSchool = function (array $school) use (
            $schoolsByName,
            $schoolsByBusiness,
            $coachIdsByBusiness,
            $coachIdsByName,
            $headCoachByIdentity,
            $coachRowsByIdentity
        ): array {
            $schoolNameCandidates = collect([
                $school['name'] ?? null,
                $school['school'] ?? null,
                $school['school_name'] ?? null,
                $school['company_name'] ?? null,
                $school['business_name'] ?? null,
            ])->merge(is_array($school['school_aliases'] ?? null) ? $school['school_aliases'] : [])
                ->map(fn ($value): string => $this->normalizeSchoolKey((string) $value))
                ->filter()
                ->unique()
                ->values();

            $schoolBusinessCandidates = collect([
                $school['business_id'] ?? null,
                $school['company_id'] ?? null,
                $school['ghl_business_id'] ?? null,
                $school['id'] ?? null,
            ])->map(fn ($value): string => strtolower(trim((string) $value)))
                ->filter()
                ->unique()
                ->values();

            $nameKey = (string) ($schoolNameCandidates->first() ?? '');
            $businessId = (string) ($schoolBusinessCandidates->first() ?? '');
            $built = [];

            if ($businessId !== '' && isset($schoolsByBusiness[$businessId])) {
                $built = $schoolsByBusiness[$businessId];
            }
            if (empty($built) && $nameKey !== '' && isset($schoolsByName[$nameKey])) {
                $built = $schoolsByName[$nameKey];
            }

            $associatedKnownIds = [];
            foreach ($schoolBusinessCandidates as $candidateBusinessId) {
                foreach (array_keys($coachIdsByBusiness[$candidateBusinessId] ?? []) as $identity) {
                    $associatedKnownIds[$identity] = true;
                }
            }

            $nameMatchedIds = [];
            foreach ($schoolNameCandidates as $candidateName) {
                foreach (array_keys($coachIdsByName[$candidateName] ?? []) as $identity) {
                    $nameMatchedIds[$identity] = true;
                }
            }

            // Build one roster from both sides of Recruiting Center:
            // - official Business/Company association IDs;
            // - contact Business Name / Company Name / School Name matches.
            // The visible count is based on the exact contact rows in this union, so the
            // card count and the names rendered in the school drawer cannot drift apart.
            $matchedIds = $associatedKnownIds + $nameMatchedIds;
            $nameOnlyIds = array_diff_key($nameMatchedIds, $associatedKnownIds);
            $matchedCoachRows = collect(array_keys($matchedIds))
                ->map(fn (string $identity): ?array => $coachRowsByIdentity[$identity] ?? null)
                ->filter(fn ($coach): bool => is_array($coach))
                ->unique(fn (array $coach): string => $this->coachUniqueKey($coach))
                ->values();

            $matchedCoachKeys = $matchedCoachRows
                ->map(fn (array $coach): string => $this->coachUniqueKey($coach))
                ->filter()
                ->unique()
                ->values()
                ->all();
            $matchedCoachIds = $matchedCoachRows
                ->map(fn (array $coach): string => trim((string) ($coach['id'] ?? $coach['contact_id'] ?? '')))
                ->filter()
                ->unique()
                ->values()
                ->all();
            $matchedCoachEmails = $matchedCoachRows
                ->map(fn (array $coach): string => strtolower(trim((string) ($coach['email'] ?? ''))))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $reportedAssociatedCount = max(
                (int) ($school['coach_count_api_associated'] ?? 0),
                (int) ($school['coach_count_associated'] ?? 0),
                (int) ($school['contacts_count'] ?? 0),
                (int) ($school['business_contact_count'] ?? 0),
                (int) ((($school['coach_count_source'] ?? '') === 'ghl_business_count') ? ($school['coach_count'] ?? 0) : 0)
            );
            $knownAssociatedCount = count($associatedKnownIds);
            $nameMatchedCount = count($nameMatchedIds);
            $nameOnlyCount = count($nameOnlyIds);
            $resolvedRosterCount = $matchedCoachRows->count();

            $coachCount = max(
                $resolvedRosterCount,
                (int) ($built['coach_count'] ?? 0),
                (int) ($built['coaches_count'] ?? 0)
            );

            $headCoach = $school['head_coach'] ?? ($built['head_coach'] ?? null);
            if (blank(data_get($headCoach, 'name'))) {
                foreach (array_keys($matchedIds) as $identity) {
                    if (isset($headCoachByIdentity[$identity])) {
                        $headCoach = $headCoachByIdentity[$identity];
                        break;
                    }
                }
            }
            if (blank(data_get($headCoach, 'name'))) {
                $firstIdentity = array_key_first($matchedIds);
                if ($firstIdentity !== null && isset($coachRowsByIdentity[$firstIdentity])) {
                    $headCoach = $coachRowsByIdentity[$firstIdentity];
                }
            }

            $row = array_merge($school, $built, [
                'id' => $school['id'] ?? $school['business_id'] ?? $school['company_id'] ?? ($built['id'] ?? ($businessId !== '' ? $businessId : $nameKey)),
                'business_id' => $school['business_id'] ?? $school['company_id'] ?? ($built['business_id'] ?? ($businessId !== '' ? $businessId : null)),
                'name' => $school['name'] ?? ($built['name'] ?? 'Unnamed School'),
                'logo_url' => $school['logo_url'] ?? $school['school_logo_url'] ?? $school['business_logo_url'] ?? $built['logo_url'] ?? $built['school_logo_url'] ?? $built['business_logo_url'] ?? null,
                'school_logo_url' => $school['school_logo_url'] ?? $school['logo_url'] ?? $school['business_logo_url'] ?? $built['school_logo_url'] ?? $built['logo_url'] ?? $built['business_logo_url'] ?? null,
                'business_logo_url' => $school['business_logo_url'] ?? $school['logo_url'] ?? $school['school_logo_url'] ?? $built['business_logo_url'] ?? $built['logo_url'] ?? $built['school_logo_url'] ?? null,
                'conference' => $school['conference'] ?? ($built['conference'] ?? null),
                'division' => $school['division'] ?? ($built['division'] ?? null),
                'coach_count' => $coachCount,
                'coaches_count' => $coachCount,
                'coach_keys' => $matchedCoachKeys,
                'coach_ids' => $matchedCoachIds,
                'coach_emails' => $matchedCoachEmails,
                'coaches_preview' => $matchedCoachRows
                    ->take(3)
                    ->map(fn (array $coach): array => $this->slimHeadCoach($coach))
                    ->values()
                    ->all(),
                'coach_count_api_associated' => $reportedAssociatedCount,
                'coach_count_associated' => $knownAssociatedCount,
                'coach_count_name_match' => $nameMatchedCount,
                'coach_count_name_only' => $nameOnlyCount,
                'coach_count_cross_referenced' => $resolvedRosterCount,
                'coach_count_source' => $nameOnlyCount > 0
                    ? 'contact_roster_business_id_plus_business_name'
                    : ($knownAssociatedCount > 0 ? 'contact_roster_business_id' : ($coachCount > 0 ? 'contact_roster_business_name' : 'none')),
                'head_coach' => $headCoach,
                'coaches_loaded' => (bool) ($school['coaches_loaded'] ?? false) || $coachCount > 0,
            ]);

            unset($row['coaches'], $row['staff'], $row['coaching_staff'], $row['contacts']);
            return $row;
        };

        $mergedSchools = $schools
            ->merge($builtSchools)
            ->map($enrichSchool)
            ->groupBy(function (array $school): string {
                $nameKey = $this->normalizeSchoolKey((string) ($school['name'] ?? $school['school_name'] ?? $school['company_name'] ?? ''));
                if ($nameKey !== '') {
                    return 'school:' . $nameKey;
                }

                return 'business:' . strtolower(trim((string) ($school['business_id'] ?? $school['id'] ?? '')));
            })
            ->map(function (Collection $rows): array {
                $rows = $rows->values();
                $primary = $rows->sortByDesc(fn (array $school): int =>
                    ((filled($school['business_id'] ?? null) ? 100000 : 0)
                    + ((int) ($school['coach_count'] ?? 0) * 100)
                    + (filled($school['logo_url'] ?? null) ? 10 : 0))
                )->first() ?: [];

                foreach ($rows as $row) {
                    foreach (['id', 'business_id', 'logo_url', 'school_logo_url', 'business_logo_url', 'conference', 'division', 'city', 'state'] as $field) {
                        if (blank($primary[$field] ?? null) && filled($row[$field] ?? null)) {
                            $primary[$field] = $row[$field];
                        }
                    }
                    foreach (['coach_count_api_associated', 'coach_count_associated', 'coach_count_name_match', 'coach_count_name_only', 'coach_count_cross_referenced'] as $field) {
                        $primary[$field] = max((int) ($primary[$field] ?? 0), (int) ($row[$field] ?? 0));
                    }
                    $primary['coach_keys'] = collect($primary['coach_keys'] ?? [])
                        ->merge($row['coach_keys'] ?? [])
                        ->map(fn ($key): string => trim((string) $key))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();
                    $primary['coach_ids'] = collect($primary['coach_ids'] ?? [])
                        ->merge($row['coach_ids'] ?? [])
                        ->map(fn ($id): string => trim((string) $id))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();
                    $primary['coach_emails'] = collect($primary['coach_emails'] ?? [])
                        ->merge($row['coach_emails'] ?? [])
                        ->map(fn ($email): string => strtolower(trim((string) $email)))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();
                    $primary['coaches_preview'] = collect($primary['coaches_preview'] ?? [])
                        ->merge($row['coaches_preview'] ?? [])
                        ->filter(fn ($coach): bool => is_array($coach))
                        ->unique(fn (array $coach): string => strtolower(trim((string) ($coach['id'] ?? $coach['email'] ?? $coach['name'] ?? md5(json_encode($coach))))))
                        ->take(3)
                        ->values()
                        ->all();
                    if (blank(data_get($primary, 'head_coach.name')) && filled(data_get($row, 'head_coach.name'))) {
                        $primary['head_coach'] = $row['head_coach'];
                    }
                    if (($row['coach_count_name_only'] ?? 0) > 0) {
                        $primary['coach_count_source'] = 'contact_roster_business_id_plus_business_name';
                    }
                }

                $resolvedCount = count($primary['coach_keys'] ?? []);
                if ($resolvedCount === 0) {
                    $resolvedCount = max(count($primary['coach_ids'] ?? []), count($primary['coach_emails'] ?? []));
                }
                $primary['coach_count'] = $resolvedCount > 0
                    ? $resolvedCount
                    : max((int) ($primary['coach_count'] ?? 0), (int) ($primary['coaches_count'] ?? 0));
                $primary['coaches_count'] = $primary['coach_count'];
                $primary['coach_count_cross_referenced'] = $primary['coach_count'];
                unset($primary['coaches'], $primary['staff'], $primary['coaching_staff'], $primary['contacts']);

                return $primary;
            })
            ->sortBy('name')
            ->values();

        return [
            'coaches' => $coaches->values()->all(),
            'schools' => $mergedSchools->all(),
            'lists' => $this->buildLists($mergedSchools, $user, $coaches, $customListTags),
            'stats' => $this->buildStats($coaches, $mergedSchools),
            'top_schools' => $this->topEngagedSchools($mergedSchools),
        ];
    }


    public function getConversationsForUser(User $user, array $query = []): array
    {
        return $this->goHighLevelService->getConversationsForUser($user, $query);
    }

    public function getConversationMessagesForUser(User $user, string $conversationId, ?string $lastMessageId = null, int $limit = 50): array
    {
        return $this->goHighLevelService->getConversationMessagesForUser($user, $conversationId, $lastMessageId, $limit);
    }

    public function sendEmailMessageForUser(User $user, array $payload): array
    {
        return $this->goHighLevelService->sendEmailMessageForUser($user, $payload);
    }

    public function getEmailTemplatesForUser(User $user): array
    {
        return $this->goHighLevelService->getEmailTemplatesForUser($user);
    }

    public function getEmailTemplateForUser(User $user, string $templateId): array
    {
        return $this->goHighLevelService->getEmailTemplateForUser($user, $templateId);
    }

    public function createEmailCampaignForUser(User $user, array $payload): array
    {
        return $this->goHighLevelService->createEmailCampaignForUser($user, $payload);
    }

    public function scheduleEmailCampaignForUser(User $user, string $campaignId, ?int $scheduledTimestamp = null): array
    {
        return $this->goHighLevelService->scheduleEmailCampaignForUser($user, $campaignId, $scheduledTimestamp);
    }

    public function createEmailTemplateForUser(User $user, string $name, string $subject, string $body, string $previewText = ''): array
    {
        if (trim($name) === '' || trim($subject) === '' || trim($body) === '') {
            return ['success' => false, 'error' => 'Template name, subject, and message are required.'];
        }

        return $this->goHighLevelService->createEmailTemplateForUser($user, $name, $subject, $body, $previewText);
    }

    public function updateEmailTemplateForUser(User $user, string $templateId, string $name, string $subject, string $body, string $previewText = ''): array
    {
        if (trim($templateId) === '') {
            return ['success' => false, 'error' => 'Choose a template first.'];
        }

        if (trim($name) === '' || trim($subject) === '' || trim($body) === '') {
            return ['success' => false, 'error' => 'Template name, subject, and message are required.'];
        }

        return $this->goHighLevelService->updateEmailTemplateForUser($user, $templateId, $name, $subject, $body, $previewText);
    }

    public function deleteEmailTemplateForUser(User $user, string $templateId): array
    {
        if (trim($templateId) === '') {
            return ['success' => false, 'error' => 'Choose a template first.'];
        }

        return $this->goHighLevelService->deleteEmailTemplateForUser($user, $templateId);
    }

    public function uploadMediaForUser(User $user, mixed $file): array
    {
        return $this->goHighLevelService->uploadMediaForUser($user, $file);
    }

}