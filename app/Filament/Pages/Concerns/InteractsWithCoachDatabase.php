<?php

namespace App\Filament\Pages\Concerns;

use App\Services\CoachDatabaseService;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

trait InteractsWithCoachDatabase
{
    public array $lists = [];
    public array $stats = [];
    public array $topSchools = [];
    public array $conversations = [];
    public array $messages = [];
    public array $templates = [];

    public bool $allowed = false;
    public bool $locked = false;
    public ?string $reason = null;
    public ?string $error = null;

    public string $section = 'dashboard';
    public string $search = '';
    public string $coachSearch = '';
    public string $conversationSearch = '';
    public string $divisionFilter = '';
    public string $conferenceFilter = '';
    public string $sort = 'name';
    public string $newListName = '';
    public string $selectedListKey = '';

    public int $schoolDisplayLimit = 24;
    public int $coachDisplayLimit = 40;

    public ?string $selectedSchoolId = null;
    public ?string $selectedConversationId = null;
    public ?string $selectedCoachId = null;
    public ?string $dataCacheKey = null;

    public bool $isLoadingDataset = false;
    public bool $hasMoreData = false;
    public ?int $nextBusinessSkip = 0;
    public ?int $remoteTotalSchools = null;
    public int $loadedSchoolsCount = 0;
    public int $loadedContactsCount = 0;
    public int $loadedPages = 0;
    public ?string $cachedAt = null;

    public string $emailSubject = '';
    public string $emailBody = '';
    public string $templateName = '';
    public string $templateSubject = '';
    public string $templateBody = '';
    public ?string $selectedTemplateId = null;
    public ?string $messageLastId = null;
    public bool $hasMoreMessages = false;
    public bool $isSendingEmail = false;
    public bool $isSyncingTags = false;
    public ?string $tagSyncedAt = null;

    public function mount(CoachDatabaseService $coachDatabaseService): void
    {
        $this->section = $this->coachDatabaseSection();
        $this->dataCacheKey = $this->cacheKey();
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $state = $coachDatabaseService->getInitialState($user);
        $this->allowed = (bool) ($state['allowed'] ?? false);
        $this->locked = (bool) ($state['locked'] ?? false);
        $this->reason = $state['reason'] ?? null;
        $this->error = $state['error'] ?? null;

        $cached = Cache::get($this->activeCacheKey());
        if (is_array($cached)) {
            $this->hydrateFromSnapshot($cached);
        } else {
            $this->storeSnapshot($this->emptySnapshot($state));
            $this->hydrateFromSnapshot(Cache::get($this->activeCacheKey(), []));
        }

        if (in_array($this->section, ['favorites', 'lists'], true) && $this->allowed && ! $this->locked) {
            $snapshot = Cache::get($this->activeCacheKey(), $this->emptySnapshot());

            if (blank($snapshot['tag_synced_at'] ?? null) || ! $this->snapshotHasSavedFavoriteOrListData($snapshot)) {
                $this->syncLatestContactTags(false);
            } else {
                $this->syncTagsIfStale(false);
            }
        }

        if ($this->section === 'conversations') {
            $this->loadConversations();
        }

        if ($this->section === 'campaigns') {
            $this->loadTemplates();
        }
    }

    protected function coachDatabaseSection(): string
    {
        return 'dashboard';
    }

    public function pageUrl(string $section): string
    {
        return match ($section) {
            'schools' => \App\Filament\Pages\CoachDatabaseSchools::getUrl(),
            'coaches' => \App\Filament\Pages\CoachDatabaseCoaches::getUrl(),
            'favorites' => \App\Filament\Pages\CoachDatabaseFavorites::getUrl(),
            'lists' => \App\Filament\Pages\CoachDatabaseLists::getUrl(),
            'conversations' => \App\Filament\Pages\CoachDatabaseConversations::getUrl(),
            'campaigns' => \App\Filament\Pages\CoachDatabaseCampaigns::getUrl(),
            default => \App\Filament\Pages\CoachDatabase::getUrl(),
        };
    }

    public function startBackgroundLoad(bool $force = false): void
    {
        if (! $this->allowed || $this->locked) {
            return;
        }

        if ($force) {
            Cache::forget($this->activeCacheKey());
            $this->storeSnapshot($this->emptySnapshot());
        }

        $snapshot = Cache::get($this->activeCacheKey(), $this->emptySnapshot());
        $this->hydrateFromSnapshot($snapshot);
        $this->isLoadingDataset = (bool) ($snapshot['has_more_data'] ?? true);
        $this->hasMoreData = $this->isLoadingDataset;

        if ($this->isLoadingDataset) {
            $this->dispatch('coach-database-load-next');
        }
    }

    public function loadNextBatch(): void
    {
        if (! $this->allowed || $this->locked) {
            $this->isLoadingDataset = false;
            return;
        }

        $service = app(CoachDatabaseService::class);
        $user = Auth::user();
        if (! $user) {
            $this->isLoadingDataset = false;
            return;
        }

        $snapshot = Cache::get($this->activeCacheKey(), $this->emptySnapshot());
        $maxPages = (int) config('ghl.coach_database.max_pages', 500);
        $loadedPages = (int) ($snapshot['loaded_pages'] ?? 0);

        if ($loadedPages >= $maxPages) {
            $snapshot['has_more_data'] = false;
            $this->storeSnapshot($snapshot);
            $this->hydrateFromSnapshot($snapshot);
            $this->isLoadingDataset = false;
            return;
        }

        if ((bool) ($snapshot['businesses_have_more'] ?? true)) {
            $result = $service->getSchoolBusinessesPageForUser(
                user: $user,
                skip: (int) ($snapshot['next_business_skip'] ?? 0),
                limit: (int) config('ghl.coach_database.business_page_limit', 100),
            );

            if (! ($result['success'] ?? false)) {
                $this->error = $result['error'] ?? 'Unable to load schools.';
                $this->isLoadingDataset = false;
                return;
            }

            $snapshot['schools'] = collect($snapshot['schools'] ?? [])
                ->merge($result['schools'] ?? [])
                ->filter(fn ($school): bool => is_array($school) && filled($school['id'] ?? null))
                ->unique('id')
                ->values()
                ->all();
            $snapshot['next_business_skip'] = $result['next_skip'] ?? null;
            $snapshot['businesses_have_more'] = (bool) ($result['has_more'] ?? false);
            $snapshot['remote_total_schools'] = $result['total'] ?? ($snapshot['remote_total_schools'] ?? null);
        }

        if ((bool) ($snapshot['contacts_have_more'] ?? true)) {
            $contactsResult = $service->getCoachContactsPageForUser(
                user: $user,
                startAfter: $snapshot['next_contacts_start_after'] ?? null,
                startAfterId: $snapshot['next_contacts_start_after_id'] ?? null,
                limit: (int) config('ghl.coach_database.contact_page_limit', 100),
            );

            if ($contactsResult['success'] ?? false) {
                $this->mergeContactsIntoSnapshot($snapshot, $contactsResult['contacts'] ?? []);
                $snapshot['next_contacts_start_after'] = $contactsResult['next_start_after'] ?? null;
                $snapshot['next_contacts_start_after_id'] = $contactsResult['next_start_after_id'] ?? null;
                $snapshot['contacts_have_more'] = (bool) ($contactsResult['has_more'] ?? false);
                $snapshot['remote_total_contacts'] = $contactsResult['total'] ?? ($snapshot['remote_total_contacts'] ?? null);
            } else {
                $snapshot['contacts_have_more'] = false;
            }
        }

        $schoolsToHydrate = collect($snapshot['schools'] ?? [])
            ->filter(fn (array $school): bool => empty($school['coaches_loaded']) && filled($school['business_id'] ?? null))
            ->take((int) config('ghl.coach_database.businesses_per_batch', 10))
            ->values();

        foreach ($schoolsToHydrate as $school) {
            $school = $this->loadSchoolCoachesIntoSnapshot($school, $snapshot, $service, $user);
        }

        $loadedPages++;
        $snapshot['loaded_pages'] = $loadedPages;
        $snapshot['has_more_data'] = (bool) ($snapshot['businesses_have_more'] ?? false)
            || (bool) ($snapshot['contacts_have_more'] ?? false)
            || collect($snapshot['schools'] ?? [])->contains(fn (array $school): bool => empty($school['coaches_loaded']) && filled($school['business_id'] ?? null));
        $snapshot['cached_at'] = now()->toDateTimeString();

        $this->rebuildAndStoreSnapshot($snapshot);
        $this->isLoadingDataset = (bool) ($snapshot['has_more_data'] ?? false) && $loadedPages < $maxPages;
        $this->hasMoreData = $this->isLoadingDataset;

        if ($this->isLoadingDataset) {
            $this->dispatch('coach-database-load-next');
        }
    }

    protected function mergeContactsIntoSnapshot(array &$snapshot, array $contacts): void
    {
        $contacts = collect($contacts)
            ->filter(fn ($coach): bool => is_array($coach) && filled($coach['id'] ?? null))
            ->values();

        if ($contacts->isEmpty()) {
            return;
        }

        $existingSchools = collect($snapshot['schools'] ?? []);
        $schoolNames = $existingSchools
            ->mapWithKeys(fn (array $school): array => [strtolower(trim((string) ($school['name'] ?? ''))) => true]);

        $missingSchools = $contacts
            ->filter(fn (array $coach): bool => filled($coach['school'] ?? null))
            ->groupBy(fn (array $coach): string => strtolower(trim((string) ($coach['school'] ?? ''))))
            ->map(function (Collection $group, string $key) use ($schoolNames): ?array {
                if ($key === '' || isset($schoolNames[$key])) {
                    return null;
                }

                $first = $group->first();
                $name = trim((string) ($first['school'] ?? ''));
                return [
                    'id' => 'school-' . Str::slug($name),
                    'business_id' => null,
                    'name' => $name,
                    'conference' => $first['conference'] ?? null,
                    'division' => $first['division'] ?? null,
                    'city' => $first['city'] ?? null,
                    'state' => $first['state'] ?? null,
                    'coach_count' => $group->count(),
                    'coaches_loaded' => true,
                    'source' => 'contacts',
                ];
            })
            ->filter()
            ->values();

        $snapshot['schools'] = $existingSchools
            ->merge($missingSchools)
            ->filter(fn ($school): bool => is_array($school) && filled($school['id'] ?? null))
            ->unique('id')
            ->values()
            ->all();

        $snapshot['coaches'] = $this->mergeCoachRowsById($snapshot['coaches'] ?? [], $contacts->all());
    }

    /**
     * Merge newer remote contact rows into the cached coach rows.
     *
     * Important: tagged-contact sync returns the latest tags, but the existing cache can
     * already contain the same coach without those tags. A plain merge()->unique('id')
     * keeps the older row and hides Favorites/Saved/Lists. This method keeps the richer
     * existing profile data but lets the newest tag flags and tag array win.
     */
    protected function snapshotHasSavedFavoriteOrListData(array $snapshot): bool
    {
        $customListPrefix = strtolower(app(CoachDatabaseService::class)->customListTagPrefix());

        return collect($snapshot['coaches'] ?? [])
            ->filter(fn ($coach): bool => is_array($coach))
            ->contains(function (array $coach) use ($customListPrefix): bool {
                if ((bool) ($coach['is_saved_school'] ?? false)
                    || (bool) ($coach['is_favorite_school'] ?? false)
                    || (bool) ($coach['is_saved_coach'] ?? false)
                    || (bool) ($coach['is_favorite_coach'] ?? false)) {
                    return true;
                }

                return collect($coach['tags'] ?? [])
                    ->map(fn ($tag): string => strtolower(trim((string) $tag)))
                    ->contains(function (string $tag) use ($customListPrefix): bool {
                        return in_array($tag, ['saved school', 'favorite school', 'saved coach', 'favorite coach'], true)
                            || str_starts_with($tag, $customListPrefix);
                    });
            });
    }

    protected function mergeCoachRowsById(array $existingRows, array $incomingRows): array
    {
        $rows = [];

        foreach ($existingRows as $row) {
            if (! is_array($row) || blank($row['id'] ?? null)) {
                continue;
            }

            $rows[(string) $row['id']] = $row;
        }

        foreach ($incomingRows as $incoming) {
            if (! is_array($incoming) || blank($incoming['id'] ?? null)) {
                continue;
            }

            $id = (string) $incoming['id'];
            $existing = $rows[$id] ?? [];

            $tags = collect($existing['tags'] ?? [])
                ->merge($incoming['tags'] ?? [])
                ->map(fn ($tag): string => trim((string) $tag))
                ->filter()
                ->unique(fn (string $tag): string => strtolower($tag))
                ->values()
                ->all();

            $merged = array_merge($existing, array_filter($incoming, function ($value): bool {
                return ! is_null($value) && $value !== '';
            }));

            $merged['tags'] = $tags;

            foreach (['is_saved_school', 'is_favorite_school', 'is_saved_coach', 'is_favorite_coach'] as $flag) {
                $merged[$flag] = (bool) (($existing[$flag] ?? false) || ($incoming[$flag] ?? false));
            }

            $rows[$id] = $merged;
        }

        return collect($rows)->values()->all();
    }

    protected function loadSchoolCoachesIntoSnapshot(array $school, array &$snapshot, CoachDatabaseService $service, $user): array
    {
        $businessId = (string) ($school['business_id'] ?? $school['id'] ?? '');
        if ($businessId === '') {
            return $school;
        }

        $result = $service->getContactsForBusinessForUser($user, $businessId, 0, 100, $school);
        if (! ($result['success'] ?? false)) {
            $school['coaches_loaded'] = false;
            return $school;
        }

        $coaches = collect($snapshot['coaches'] ?? [])
            ->merge($result['coaches'] ?? [])
            ->filter(fn ($coach): bool => is_array($coach) && filled($coach['id'] ?? null))
            ->unique('id')
            ->values()
            ->all();

        $snapshot['coaches'] = $coaches;
        $snapshot['schools'] = collect($snapshot['schools'] ?? [])->map(function (array $existing) use ($school, $result): array {
            if ((string) ($existing['id'] ?? '') !== (string) ($school['id'] ?? '')) {
                return $existing;
            }
            $existing['coaches_loaded'] = true;
            $existing['coach_count'] = count($result['coaches'] ?? []);
            return $existing;
        })->values()->all();

        return $school;
    }

    public function refreshData(): void
    {
        Cache::forget($this->activeCacheKey());
        $this->schoolDisplayLimit = 24;
        $this->coachDisplayLimit = 40;
        $this->selectedSchoolId = null;
        $this->startBackgroundLoad(true);

        Notification::make()->title('Recruiting Center')->body('Refreshing recruiting data.')->success()->send();
    }

    public function loadMoreSchools(): void
    {
        $this->schoolDisplayLimit += 24;
    }

    public function loadMoreCoaches(): void
    {
        $this->coachDisplayLimit += 40;
    }

    public function createCustomList(): void
    {
        $name = trim($this->newListName);
        if ($name === '') {
            Notification::make()->title('Recruiting Center')->body('Enter a list name.')->danger()->send();
            return;
        }

        $key = Str::slug($name);
        $tag = 'plyrcard:list:' . $key;
        $snapshot = Cache::get($this->activeCacheKey(), $this->emptySnapshot());
        $custom = collect($snapshot['custom_list_tags'] ?? []);
        $custom->put($key, ['key' => $key, 'label' => Str::headline($name), 'tag' => $tag, 'custom' => true]);
        $snapshot['custom_list_tags'] = $custom->all();
        $this->selectedListKey = 'custom:' . $key;
        $this->rebuildAndStoreSnapshot($snapshot);
        $this->newListName = '';
        Notification::make()->title('Recruiting Center')->body('List created. Add a school or coach to save it to recruiting contacts.')->success()->send();
    }


    public function selectList(string $listKey): void
    {
        $this->selectedListKey = $listKey;
    }

    public function clearSelectedList(): void
    {
        $this->selectedListKey = '';
    }

    public function selectSchoolById(string $schoolId): void
    {
        $this->selectedSchoolId = $schoolId;
        $this->loadSchoolCoachesById($schoolId);
    }

    public function openSchoolFromCoach(string $schoolId): void
    {
        $this->selectSchoolById($schoolId);
    }

    public function closeSchool(): void
    {
        $this->selectedSchoolId = null;
    }

    public function loadSchoolCoachesById(string $schoolId): void
    {
        $school = collect($this->allSchools())->firstWhere('id', $schoolId);
        if (! $school || ! empty($school['coaches_loaded'])) {
            return;
        }

        $snapshot = Cache::get($this->activeCacheKey(), $this->emptySnapshot());
        $service = app(CoachDatabaseService::class);
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $this->loadSchoolCoachesIntoSnapshot($school, $snapshot, $service, $user);
        $this->rebuildAndStoreSnapshot($snapshot);
    }

    public function saveSchoolById(string $schoolId): void { $this->runSchoolContactTagAction($schoolId, app(CoachDatabaseService::class)->savedSchoolTag(), 'add'); }
    public function unsaveSchoolById(string $schoolId): void { $this->runSchoolContactTagAction($schoolId, app(CoachDatabaseService::class)->savedSchoolTag(), 'remove'); }
    public function favoriteSchoolById(string $schoolId): void { $this->runSchoolContactTagAction($schoolId, app(CoachDatabaseService::class)->favoriteSchoolTag(), 'add'); }
    public function unfavoriteSchoolById(string $schoolId): void { $this->runSchoolContactTagAction($schoolId, app(CoachDatabaseService::class)->favoriteSchoolTag(), 'remove'); }
    public function saveCoach(string $contactId): void { $this->runContactTagAction([$contactId], app(CoachDatabaseService::class)->savedCoachTag(), 'add'); }
    public function unsaveCoach(string $contactId): void { $this->runContactTagAction([$contactId], app(CoachDatabaseService::class)->savedCoachTag(), 'remove'); }
    public function favoriteCoach(string $contactId): void { $this->runContactTagAction([$contactId], app(CoachDatabaseService::class)->favoriteCoachTag(), 'add'); }
    public function unfavoriteCoach(string $contactId): void { $this->runContactTagAction([$contactId], app(CoachDatabaseService::class)->favoriteCoachTag(), 'remove'); }

    public function addSchoolToListById(string $schoolId, string $listKey): void
    {
        $tag = app(CoachDatabaseService::class)->listTagForKey($listKey, Auth::user());
        if ($tag) $this->runSchoolContactTagAction($schoolId, $tag, 'add');
    }

    public function removeSchoolFromListById(string $schoolId, string $listKey): void
    {
        $tag = app(CoachDatabaseService::class)->listTagForKey($listKey, Auth::user());
        if ($tag) $this->runSchoolContactTagAction($schoolId, $tag, 'remove');
    }

    public function addCoachToList(string $contactId, string $listKey): void
    {
        $tag = app(CoachDatabaseService::class)->listTagForKey($listKey, Auth::user());
        if ($tag) $this->runContactTagAction([$contactId], $tag, 'add');
    }

    public function removeCoachFromList(string $contactId, string $listKey): void
    {
        $tag = app(CoachDatabaseService::class)->listTagForKey($listKey, Auth::user());
        if ($tag) $this->runContactTagAction([$contactId], $tag, 'remove');
    }

    protected function runSchoolContactTagAction(string $schoolId, string $tag, string $type): void
    {
        $this->loadSchoolCoachesById($schoolId);
        $ids = $this->contactIdsForSchool($schoolId);
        $this->runContactTagAction($ids, $tag, $type);
    }

    protected function runContactTagAction(array $contactIds, string $tag, string $type): void
    {
        $contactIds = collect($contactIds)->filter()->unique()->values()->all();
        if (empty($contactIds)) {
            Notification::make()->title('Recruiting Center')->body('No coaches found for this action.')->danger()->send();
            return;
        }

        $result = app(CoachDatabaseService::class)->updateContactsWithTag(Auth::user(), $contactIds, $tag, $type);
        $this->afterRemoteTagUpdate($result, $contactIds, $tag, $type);
    }

    protected function afterRemoteTagUpdate(array $result, array $requestedContactIds, string $tag, string $type): void
    {
        $failedIds = collect($result['failed_contact_ids'] ?? [])->filter()->unique()->values()->all();
        $staleIds = collect($result['stale_contact_ids'] ?? [])->filter()->unique()->values()->all();
        $updatedIds = collect($result['updated_contact_ids'] ?? [])->filter()->unique()->values()->all();

        if (empty($updatedIds) && ($result['success'] ?? false)) {
            $updatedIds = array_values(array_diff($requestedContactIds, $failedIds));
        }

        if (! empty($staleIds)) {
            $this->removeContactsFromCache($staleIds);
        }

        if (! empty($updatedIds)) {
            $this->applyTagToCachedContacts($updatedIds, $tag, $type);
        }

        if (! ($result['success'] ?? false) && ! ($result['partial_success'] ?? false)) {
            Notification::make()->title('Recruiting Center')->body($result['error'] ?? 'Action failed.')->danger()->send();
            return;
        }

        if (! empty($staleIds)) {
            $message = count($updatedIds) . ' updated. ' . count($staleIds) . ' unavailable coach record removed.';
        } elseif (! empty($failedIds)) {
            $message = count($updatedIds) . ' updated. ' . count($failedIds) . ' could not be updated.';
        } else {
            $message = 'Updated successfully.';
        }

        Notification::make()->title('Recruiting Center')->body($message)->success()->send();
    }

    public function syncTagsIfStale(bool $force = false): void
    {
        $snapshot = Cache::get($this->activeCacheKey(), $this->emptySnapshot());
        $lastSyncedAt = $snapshot['tag_synced_at'] ?? null;
        $syncEveryMinutes = (int) config('ghl.coach_database.tag_sync_minutes', 5);

        if (! $force && $lastSyncedAt && now()->diffInMinutes(\Illuminate\Support\Carbon::parse($lastSyncedAt)) < $syncEveryMinutes) {
            $this->hydrateFromSnapshot($snapshot);
            return;
        }

        $this->syncLatestContactTags($force);
    }

    public function syncLatestContactTags(bool $force = true): void
    {
        if (! $this->allowed || $this->locked || $this->isSyncingTags) {
            return;
        }

        $user = Auth::user();
        if (! $user) {
            return;
        }

        $this->isSyncingTags = true;
        $service = app(CoachDatabaseService::class);
        $snapshot = Cache::get($this->activeCacheKey(), $this->emptySnapshot());

        $customListTags = collect($snapshot['custom_list_tags'] ?? [])
            ->map(function ($item): ?string {
                if (is_array($item)) {
                    return $item['tag'] ?? null;
                }

                return is_string($item) ? $item : null;
            })
            ->filter()
            ->values()
            ->all();

        $tags = $service->actionTags($user, $customListTags);

        if (empty($tags)) {
            $this->isSyncingTags = false;
            $this->hydrateFromSnapshot($snapshot);
            return;
        }

        $result = $service->getContactsByTagsForUser($user, $tags);
        $contacts = $result['contacts'] ?? [];

        if (! ($result['success'] ?? false) && empty($contacts)) {
            $this->isSyncingTags = false;
            $this->hydrateFromSnapshot($snapshot);

            if ($force) {
                Notification::make()->title('Recruiting Center')->body($result['error'] ?? 'Unable to sync saved, favorite, and list tags.')->danger()->send();
            }

            return;
        }

        $this->mergeContactsIntoSnapshot($snapshot, $contacts);

        $snapshot['tag_synced_at'] = now()->toDateTimeString();
        $snapshot['cached_at'] = now()->toDateTimeString();
        $snapshot['tag_sync_mode'] = 'by_tag';
        $snapshot['last_tag_sync_count'] = count($contacts);
        $snapshot['last_tag_sync_debug'] = $result['by_tag'] ?? $result['debug'] ?? [];

        $this->rebuildAndStoreSnapshot($snapshot);
        $this->isSyncingTags = false;

        if ($force) {
            $byTag = collect($result['by_tag'] ?? [])
                ->map(function ($value, $tag): string {
                    $count = is_array($value)
                        ? ($value['count'] ?? $value['total'] ?? count($value['contacts'] ?? []))
                        : $value;

                    if (is_array($count)) {
                        $count = count($count);
                    }

                    return (string) $tag . ': ' . (string) $count;
                })
                ->values()
                ->implode(', ');

            $message = count($contacts) > 0
                ? count($contacts) . ' saved, favorite, and list coaches synced' . ($byTag !== '' ? ' (' . $byTag . ').' : '.')
                : 'No tagged coaches found yet.';

            Notification::make()->title('Recruiting Center')->body($message)->success()->send();
        }
    }

    public function loadConversations(): void
    {
        $result = app(CoachDatabaseService::class)->getConversationsForUser(Auth::user(), [
            'search' => $this->conversationSearch,
            'limit' => 50,
            'status' => 'all',
        ]);

        $this->conversations = $result['conversations'] ?? [];

        if (! ($result['success'] ?? false)) {
            $this->error = $result['error'] ?? 'Unable to load conversations.';
            return;
        }

        $this->error = null;

        if (! $this->selectedConversationId && count($this->conversations) === 1) {
            $this->selectConversation((string) ($this->conversations[0]['id'] ?? ''));
        }
    }

    public function updatedConversationSearch(): void
    {
        $this->loadConversations();
    }

    public function selectConversation(string $conversationId): void
    {
        $this->selectedConversationId = $conversationId;
        $this->messageLastId = null;
        $this->messages = [];
        $this->loadConversationMessages();
    }

    public function loadConversationMessages(): void
    {
        if (! $this->selectedConversationId) {
            return;
        }

        $result = app(CoachDatabaseService::class)->getConversationMessagesForUser(
            Auth::user(),
            $this->selectedConversationId,
            $this->messageLastId
        );

        if (! ($result['success'] ?? false)) {
            $this->error = $result['error'] ?? 'Unable to load messages.';
            return;
        }

        $new = $result['messages'] ?? [];
        $this->messages = collect($this->messages)
            ->merge($new)
            ->unique('id')
            ->sortBy('created_at')
            ->values()
            ->all();
        $this->messageLastId = $result['last_message_id'] ?? $this->messageLastId;
        $this->hasMoreMessages = (bool) ($result['has_more'] ?? false);
        $this->error = null;
    }

    public function composeToCoach(string $contactId): void
    {
        $coach = collect($this->allCoaches())->firstWhere('id', $contactId);
        $this->selectedCoachId = $contactId;
        $this->emailSubject = $this->emailSubject ?: '';
        $this->emailBody = '';

        if ($coach) {
            $first = trim(explode(' ', (string) ($coach['name'] ?? 'Coach'))[0]);
            $this->emailBody = "Hi {$first},\n\n";
        }
    }

    public function closeComposer(): void
    {
        $this->selectedCoachId = null;
        $this->emailSubject = '';
        $this->emailBody = '';
    }

    public function sendEmail(): void
    {
        $subject = trim($this->emailSubject);
        $body = trim($this->emailBody);

        if ($subject === '' || $body === '') {
            Notification::make()->title('Recruiting Center')->body('Subject and message are required.')->danger()->send();
            return;
        }

        $coach = $this->selectedCoachId ? collect($this->allCoaches())->firstWhere('id', $this->selectedCoachId) : null;
        $conversation = $this->selectedConversationId ? collect($this->conversations)->firstWhere('id', $this->selectedConversationId) : null;

        $contactId = $this->selectedCoachId ?: (string) ($conversation['contact_id'] ?? $conversation['contactId'] ?? '');
        $to = $coach['email'] ?? ($conversation['email'] ?? $conversation['contact_email'] ?? null);

        if (trim((string) $contactId) === '' && trim((string) $to) === '') {
            Notification::make()->title('Recruiting Center')->body('Choose a coach with an email before sending.')->danger()->send();
            return;
        }

        $payload = [
            'contact_id' => $contactId,
            'contactId' => $contactId,
            'conversation_id' => $this->selectedConversationId,
            'conversationId' => $this->selectedConversationId,
            'subject' => $subject,
            'body' => nl2br(e($body)),
            'html' => nl2br(e($body)),
            'text' => $body,
            'to' => $to,
            'emailTo' => $to,
        ];

        $this->isSendingEmail = true;
        $result = app(CoachDatabaseService::class)->sendEmailMessageForUser(Auth::user(), $payload);
        $this->isSendingEmail = false;

        if (! ($result['success'] ?? false)) {
            Notification::make()->title('Recruiting Center')->body($result['error'] ?? 'Unable to send email.')->danger()->send();
            return;
        }

        $this->emailSubject = '';
        $this->emailBody = '';

        if ($this->selectedConversationId) {
            $this->messages = [];
            $this->messageLastId = null;
            $this->loadConversationMessages();
        }

        Notification::make()->title('Recruiting Center')->body('Email sent.')->success()->send();
    }

    public function loadTemplates(): void
    {
        $result = app(CoachDatabaseService::class)->getEmailTemplatesForUser(Auth::user());
        $this->templates = $result['templates'] ?? [];

        if (! ($result['success'] ?? false)) {
            $this->error = $result['error'] ?? 'Unable to load templates.';
            return;
        }

        $this->error = null;
    }

    public function createTemplate(): void
    {
        $result = app(CoachDatabaseService::class)->createEmailTemplateForUser(Auth::user(), $this->templateName, $this->templateSubject, $this->templateBody);
        if (! ($result['success'] ?? false)) {
            Notification::make()->title('Recruiting Center')->body($result['error'] ?? 'Unable to create template.')->danger()->send();
            return;
        }
        $this->templateName = $this->templateSubject = $this->templateBody = '';
        $this->loadTemplates();
        Notification::make()->title('Recruiting Center')->body('Template created.')->success()->send();
    }

    public function useTemplate(string $templateId): void
    {
        $template = collect($this->templates)->firstWhere('id', $templateId);
        if (! $template) return;
        $this->selectedTemplateId = $templateId;
        $this->emailSubject = $template['subject'] ?? '';
        $this->emailBody = strip_tags((string) ($template['body'] ?? ''));
    }

    protected function applyTagToCachedContacts(array $contactIds, string $tag, string $type): void
    {
        $snapshot = Cache::get($this->activeCacheKey(), $this->emptySnapshot());
        if (str_starts_with(strtolower(trim($tag)), 'plyrcard:list:')) {
            $key = Str::after(strtolower(trim($tag)), 'plyrcard:list:');
            if ($key !== '') {
                $snapshot['custom_list_tags'][$key] = [
                    'key' => $key,
                    'label' => Str::of($key)->replace('-', ' ')->headline()->toString(),
                    'tag' => $tag,
                    'custom' => true,
                ];
            }
        }
        $ids = array_flip($contactIds);
        $lower = strtolower(trim($tag));
        $snapshot['coaches'] = collect($snapshot['coaches'] ?? [])->map(function (array $coach) use ($ids, $tag, $lower, $type): array {
            if (! isset($ids[(string) ($coach['id'] ?? '')])) return $coach;
            $tags = collect($coach['tags'] ?? [])->map(fn ($tag) => trim((string) $tag))->filter()->values();
            if ($type === 'add') {
                if (! $tags->contains(fn ($existing) => strtolower($existing) === $lower)) $tags->push($tag);
            } else {
                $tags = $tags->reject(fn ($existing) => strtolower($existing) === $lower)->values();
            }

            $coach['tags'] = $tags->values()->all();
            $has = $type === 'add';

            if ($lower === strtolower(app(CoachDatabaseService::class)->savedSchoolTag())) {
                $coach['is_saved_school'] = $has;
            } elseif ($lower === strtolower(app(CoachDatabaseService::class)->favoriteSchoolTag())) {
                $coach['is_favorite_school'] = $has;
            } elseif ($lower === strtolower(app(CoachDatabaseService::class)->savedCoachTag())) {
                $coach['is_saved_coach'] = $has;
            } elseif ($lower === strtolower(app(CoachDatabaseService::class)->favoriteCoachTag())) {
                $coach['is_favorite_coach'] = $has;
            }

            return $coach;
        })->values()->all();
        $this->rebuildAndStoreSnapshot($snapshot);
    }

    protected function removeContactsFromCache(array $contactIds): void
    {
        $snapshot = Cache::get($this->activeCacheKey(), $this->emptySnapshot());
        $ids = array_flip($contactIds);
        $snapshot['coaches'] = collect($snapshot['coaches'] ?? [])->reject(fn (array $coach): bool => isset($ids[(string) ($coach['id'] ?? '')]))->values()->all();
        $this->rebuildAndStoreSnapshot($snapshot);
    }

    protected function rebuildAndStoreSnapshot(array $snapshot): void
    {
        $dashboard = app(CoachDatabaseService::class)->rebuildFromSchoolCompanySnapshot($snapshot['schools'] ?? [], $snapshot['coaches'] ?? [], Auth::user(), $snapshot['custom_list_tags'] ?? []);
        $snapshot = array_merge($snapshot, $dashboard, [
            'loaded_schools_count' => count($dashboard['schools'] ?? []),
            'loaded_contacts_count' => count($dashboard['coaches'] ?? []),
            'cached_at' => now()->toDateTimeString(),
        ]);
        $this->storeSnapshot($snapshot);
        $this->hydrateFromSnapshot($snapshot);
    }

    protected function contactIdsForSchool(string $schoolId): array
    {
        $school = collect($this->allSchools())->firstWhere('id', $schoolId);
        if (! $school) return [];
        $businessId = (string) ($school['business_id'] ?? $school['id'] ?? '');
        return collect($this->allCoaches())
            ->filter(fn (array $coach): bool => (string) ($coach['business_id'] ?? '') === $businessId || trim((string) ($coach['school'] ?? '')) === trim((string) ($school['name'] ?? '')))
            ->pluck('id')->filter()->unique()->values()->all();
    }

    public function getDashboardSchoolsProperty(): array { return collect($this->allSchools())->take(8)->values()->all(); }
    public function getFilteredSchoolsProperty(): array { return $this->filteredSchoolsQuery()->take($this->schoolDisplayLimit)->values()->all(); }
    public function getFilteredSchoolsCountProperty(): int { return $this->filteredSchoolsQuery()->count(); }
    public function getCanLoadMoreSchoolsProperty(): bool { return $this->filteredSchoolsCount > count($this->filteredSchools); }
    public function getFilteredCoachesProperty(): array { return $this->filteredCoachesQuery()->take($this->coachDisplayLimit)->values()->all(); }
    public function getFilteredCoachesCountProperty(): int { return $this->filteredCoachesQuery()->count(); }
    public function getCanLoadMoreCoachesProperty(): bool { return $this->filteredCoachesCount > count($this->filteredCoaches); }
    public function getFavoriteSchoolsProperty(): array { return collect($this->allSchools())->filter(fn (array $school): bool => (bool) ($school['is_favorite'] ?? false))->values()->all(); }
    public function getFavoriteCoachesProperty(): array { return collect($this->allCoaches())->filter(fn (array $coach): bool => (bool) ($coach['is_favorite_coach'] ?? false))->take(80)->values()->all(); }


    public function getSavedSchoolsProperty(): array
    {
        return collect($this->allSchools())->filter(fn (array $school): bool => (bool) ($school['is_saved'] ?? false))->values()->all();
    }

    public function getSavedCoachesProperty(): array
    {
        return collect($this->allCoaches())->filter(fn (array $coach): bool => (bool) ($coach['is_saved_coach'] ?? false))->take(120)->values()->all();
    }

    public function getSelectedListProperty(): ?array
    {
        if ($this->selectedListKey === '') {
            return null;
        }

        return collect($this->lists)->firstWhere('key', $this->selectedListKey);
    }

    public function getSelectedListSchoolsProperty(): array
    {
        $list = $this->selectedList;
        $tag = strtolower(trim((string) ($list['tag'] ?? '')));

        if ($tag === '') {
            return [];
        }

        return collect($this->allSchools())
            ->filter(fn (array $school): bool => in_array((string) ($list['key'] ?? ''), $school['list_keys'] ?? [], true))
            ->values()
            ->all();
    }

    public function getSelectedListCoachesProperty(): array
    {
        $list = $this->selectedList;
        $tag = strtolower(trim((string) ($list['tag'] ?? '')));

        if ($tag === '') {
            return [];
        }

        return collect($this->allCoaches())
            ->filter(function (array $coach) use ($tag): bool {
                return collect($coach['tags'] ?? [])
                    ->contains(fn ($existing): bool => strtolower(trim((string) $existing)) === $tag);
            })
            ->values()
            ->all();
    }

    public function getSelectedCoachProperty(): ?array
    {
        if (! $this->selectedCoachId) {
            return null;
        }

        return collect($this->allCoaches())->firstWhere('id', $this->selectedCoachId);
    }

    public function getSelectedSchoolProperty(): ?array
    {
        if (! $this->selectedSchoolId) return null;
        $school = collect($this->allSchools())->firstWhere('id', $this->selectedSchoolId);
        if (! $school) return null;
        $businessId = (string) ($school['business_id'] ?? $school['id'] ?? '');
        $school['coaches'] = collect($this->allCoaches())->filter(fn (array $coach): bool => (string) ($coach['business_id'] ?? '') === $businessId || trim((string) ($coach['school'] ?? '')) === trim((string) ($school['name'] ?? '')))->values()->all();
        return $school;
    }

    public function getDivisionsProperty(): array { return collect($this->allSchools())->pluck('division')->filter()->unique()->sort()->values()->all(); }
    public function getConferencesProperty(): array { return collect($this->allSchools())->pluck('conference')->filter()->unique()->sort()->values()->all(); }

    protected function filteredSchoolsQuery(): Collection
    {
        return collect($this->allSchools())->filter(function (array $school): bool {
            if ($this->search !== '') {
                $haystack = strtolower(implode(' ', [$school['name'] ?? '', $school['conference'] ?? '', $school['division'] ?? '']));
                if (! str_contains($haystack, strtolower($this->search))) return false;
            }
            if ($this->divisionFilter !== '' && (string) ($school['division'] ?? '') !== $this->divisionFilter) return false;
            if ($this->conferenceFilter !== '' && (string) ($school['conference'] ?? '') !== $this->conferenceFilter) return false;
            return true;
        })->sortBy($this->sort === 'coach_count' ? 'coach_count' : 'name');
    }

    protected function filteredCoachesQuery(): Collection
    {
        return collect($this->allCoaches())->filter(function (array $coach): bool {
            $query = trim($this->coachSearch !== '' ? $this->coachSearch : $this->search);
            if ($query !== '') {
                $haystack = strtolower(implode(' ', [$coach['name'] ?? '', $coach['email'] ?? '', $coach['title'] ?? '', $coach['school'] ?? '']));
                if (! str_contains($haystack, strtolower($query))) return false;
            }
            if ($this->divisionFilter !== '' && (string) ($coach['division'] ?? '') !== $this->divisionFilter) return false;
            if ($this->conferenceFilter !== '' && (string) ($coach['conference'] ?? '') !== $this->conferenceFilter) return false;
            return true;
        })->sortBy(fn (array $coach): string => strtolower(($coach['school'] ?? '') . ' ' . ($coach['name'] ?? '')));
    }

    protected function allSchools(): array { return Cache::get($this->activeCacheKey(), [])['schools'] ?? []; }
    protected function allCoaches(): array { return Cache::get($this->activeCacheKey(), [])['coaches'] ?? []; }

    protected function hydrateFromSnapshot(array $snapshot): void
    {
        $this->lists = $snapshot['lists'] ?? [];
        $this->stats = $snapshot['stats'] ?? [];
        $this->topSchools = $snapshot['top_schools'] ?? [];
        $this->nextBusinessSkip = $snapshot['next_business_skip'] ?? 0;
        $this->remoteTotalSchools = $snapshot['remote_total_schools'] ?? null;
        $this->loadedSchoolsCount = (int) ($snapshot['loaded_schools_count'] ?? count($snapshot['schools'] ?? []));
        $this->loadedContactsCount = (int) ($snapshot['loaded_contacts_count'] ?? count($snapshot['coaches'] ?? []));
        $this->loadedPages = (int) ($snapshot['loaded_pages'] ?? 0);
        $this->hasMoreData = (bool) ($snapshot['has_more_data'] ?? true);
        $this->cachedAt = $snapshot['cached_at'] ?? null;
        $this->tagSyncedAt = $snapshot['tag_synced_at'] ?? null;
        if ($this->selectedListKey !== '' && ! collect($this->lists)->contains(fn (array $list): bool => (string) ($list['key'] ?? '') === $this->selectedListKey)) {
            $this->selectedListKey = '';
        }
    }

    protected function storeSnapshot(array $snapshot): void { Cache::put($this->activeCacheKey(), $snapshot, now()->addHours((int) config('ghl.coach_database.cache_hours', 12))); }
    protected function activeCacheKey(): string { return $this->dataCacheKey ?: $this->dataCacheKey = $this->cacheKey(); }

    protected function cacheKey(): string
    {
        $user = Auth::user();
        return 'coach-database:v10:' . ($user?->id ?: 'guest') . ':' . Str::slug((string) ($user?->ghl_location_id ?: 'default'));
    }

    protected function emptySnapshot(array $state = []): array
    {
        return [
            'coaches' => [], 'schools' => [], 'lists' => $state['lists'] ?? [], 'stats' => $state['stats'] ?? [], 'top_schools' => [],
            'next_business_skip' => 0, 'businesses_have_more' => true, 'remote_total_schools' => null,
            'next_contacts_start_after' => null, 'next_contacts_start_after_id' => null, 'contacts_have_more' => true, 'remote_total_contacts' => null,
            'loaded_schools_count' => 0, 'loaded_contacts_count' => 0, 'loaded_pages' => 0, 'has_more_data' => true,
            'custom_list_tags' => [], 'cached_at' => now()->toDateTimeString(), 'tag_synced_at' => null,
        ];
    }
}