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
    public array $templateDetails = [];
    public string $templateSourceSummary = '';
    public array $templateSourceDebug = [];

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

    public ?string $campaignTemplateId = null;
    public ?string $previewTemplateId = null;
    public string $campaignName = '';
    public string $campaignSubject = '';
    public string $campaignPreviewText = '';
    public string $campaignBody = '';
    public string $campaignOriginalHtml = '';
    public bool $campaignTemplateIsDesign = false;
    public array $campaignEditableBlocks = [];
    public string $campaignTargetMode = 'coaches';
    public string $campaignCoachSearch = '';
    public array $campaignCoachIds = [];
    public string $campaignListKey = '';
    public string $campaignSchoolId = '';
    public bool $isSendingCampaign = false;
    public ?string $messageLastId = null;
    public bool $hasMoreMessages = false;
    public bool $isSendingEmail = false;
    public bool $isSyncingTags = false;
    public bool $showNewConversationComposer = false;
    public string $newConversationCoachSearch = '';
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

    public function pollRealtime(): void
    {
        if (! $this->allowed || $this->locked) {
            return;
        }

        if ($this->hasMoreData || $this->isLoadingDataset) {
            $this->loadNextBatch();
            return;
        }

        $this->hydrateFromSnapshot(Cache::get($this->activeCacheKey(), $this->emptySnapshot()));

        if ($this->section === 'conversations') {
            $this->refreshConversationsRealtime();
        }
    }

    public function refreshConversationsRealtime(): void
    {
        $this->loadConversations();
        if ($this->selectedConversationId) {
            $this->loadConversationMessages();
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
                    $value = $item['tag'] ?? $item['name'] ?? $item['value'] ?? null;
                    return is_scalar($value) ? trim((string) $value) : null;
                }

                return is_scalar($item) ? trim((string) $item) : null;
            })
            ->filter(fn (?string $tag): bool => filled($tag))
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

    public function pollConversationUpdates(): void
    {
        if ($this->section !== 'conversations' || ! $this->allowed || $this->locked) {
            return;
        }

        $this->loadConversations();

        if ($this->selectedConversationId) {
            $this->messages = [];
            $this->messageLastId = null;
            $this->loadConversationMessages();
        }
    }

    public function startNewConversation(): void
    {
        $this->showNewConversationComposer = true;
        $this->selectedConversationId = null;
        $this->selectedCoachId = null;
        $this->messages = [];
        $this->messageLastId = null;
        $this->emailSubject = '';
        $this->emailBody = '';
        $this->showNewConversationComposer = false;
    }

    public function cancelNewConversation(): void
    {
        $this->showNewConversationComposer = false;
        $this->selectedCoachId = null;
        $this->emailSubject = '';
        $this->emailBody = '';
    }

    public function selectCoachForNewConversation(string $contactId): void
    {
        $this->showNewConversationComposer = true;
        $this->composeToCoach($contactId);
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
            $this->emailBody = "<p>Hi {$first},</p><p><br></p>";
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
        $plainBody = trim(strip_tags($body));

        if ($subject === '' || $plainBody === '') {
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
            'body' => $body,
            'html' => $body,
            'text' => $plainBody,
            'fromName' => (string) (Auth::user()->name ?? 'PLYRCard'),
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
        $this->showNewConversationComposer = false;

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
        $this->templateSourceSummary = (string) ($result['source'] ?? '');
        $this->templateSourceDebug = is_array($result['debug'] ?? null) ? $result['debug'] : [];

        if (! ($result['success'] ?? false)) {
            $this->error = $result['error'] ?? 'Unable to load templates.';
            Notification::make()->title('Recruiting Center')->body($this->error)->danger()->send();
            return;
        }

        $this->error = null;

        if ($this->previewTemplateId && ! collect($this->templates)->contains(fn (array $template): bool => (string) ($template['id'] ?? '') === $this->previewTemplateId)) {
            $this->previewTemplateId = null;
        }

        if (! $this->previewTemplateId && ! empty($this->templates[0]['id'])) {
            $this->previewTemplateId = (string) $this->templates[0]['id'];
        }

        if ($this->previewTemplateId) {
            $this->loadTemplateDetail($this->previewTemplateId);
        }
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

    public function loadTemplateDetail(string $templateId): ?array
    {
        $templateId = trim($templateId);

        if ($templateId === '') {
            return null;
        }

        if (isset($this->templateDetails[$templateId])) {
            return $this->templateDetails[$templateId];
        }

        $summary = collect($this->templates)->firstWhere('id', $templateId);
        $result = app(CoachDatabaseService::class)->getEmailTemplateForUser(Auth::user(), $templateId);

        if (! ($result['success'] ?? false)) {
            if (is_array($summary)) {
                $this->templateDetails[$templateId] = $summary;
                return $summary;
            }

            Notification::make()->title('Recruiting Center')->body($result['error'] ?? 'Unable to load template details.')->danger()->send();
            return null;
        }

        $detail = $result['template'] ?? [];
        $detail = is_array($detail) ? $detail : [];

        if (is_array($summary)) {
            $detail = $this->mergeTemplateRecord($summary, $detail);
        }

        $this->templateDetails[$templateId] = $detail;
        $this->templates = collect($this->templates)
            ->map(fn (array $template): array => (string) ($template['id'] ?? '') === $templateId ? $this->mergeTemplateRecord($template, $detail) : $template)
            ->values()
            ->all();

        return $detail;
    }

    public function previewTemplate(string $templateId): void
    {
        $this->previewTemplateId = $templateId;
        $this->loadTemplateDetail($templateId);
    }

    public function useTemplate(string $templateId): void
    {
        $template = $this->loadTemplateDetail($templateId)
            ?: collect($this->templates)->firstWhere('id', $templateId);

        if (! is_array($template)) {
            return;
        }

        $subject = $this->templateSubject($template);
        $body = $this->templateHtml($template);

        if ($subject === '') {
            $subject = (string) ($template['name'] ?? 'Recruiting Email');
        }

        if ($body === '') {
            $body = '';
        }

        $this->selectedTemplateId = $templateId;
        $this->campaignTemplateId = $templateId;
        $this->previewTemplateId = $templateId;
        $this->emailSubject = $subject;
        $this->emailBody = $body;
        $this->campaignName = (string) ($template['name'] ?? 'Recruiting Campaign');
        $this->campaignSubject = $subject;
        $this->campaignPreviewText = $this->templatePreviewText($template);
        // Full builder/design templates can be very large HTML documents.
        // Keep that HTML available for preview/sending through previewTemplateHtml,
        // but do not put it into the visible message textarea.
        $this->campaignOriginalHtml = $body;
        $this->campaignTemplateIsDesign = $this->isDesignedTemplateHtml($body);
        $this->campaignEditableBlocks = [];
        // Keep the full template HTML intact. The visual iframe editor updates only
        // the text/image content inside this HTML and syncs the updated full HTML
        // back into campaignBody for preview/sending.
        $this->campaignBody = $body;

        Notification::make()->title('Recruiting Center')->body('Email selected.')->success()->send();
    }

    public function clearCampaignTemplate(): void
    {
        $this->campaignTemplateId = null;
        $this->campaignName = '';
        $this->campaignSubject = '';
        $this->campaignPreviewText = '';
        $this->campaignBody = '';
        $this->campaignOriginalHtml = '';
        $this->campaignTemplateIsDesign = false;
        $this->campaignEditableBlocks = [];
    }

    public function updatedCampaignEditableBlocks(mixed $value = null, ?string $key = null): void
    {
        if (! $this->campaignTemplateIsDesign || trim($this->campaignOriginalHtml) === '') {
            return;
        }

        $this->campaignBody = $this->rebuildTemplateHtmlFromEditableBlocks();
    }

    public function insertCampaignVariable(string $token, string $field = 'body'): void
    {
        $allowed = ['{{coach_name}}', '{{first_name}}', '{{last_name}}', '{{school}}', '{{email}}'];
        $token = trim($token);

        if (! in_array($token, $allowed, true)) {
            return;
        }

        if ($field === 'subject') {
            $this->campaignSubject = trim($this->campaignSubject . ' ' . $token);
            return;
        }

        if ($this->campaignTemplateIsDesign) {
            $this->campaignBody = $this->appendTokenToHtmlBody($this->campaignBody, $token);
            return;
        }

        $separator = trim($this->campaignBody) === '' ? '' : ' ';
        $this->campaignBody .= $separator . $token;
    }

    public function updatedCampaignTargetMode(): void
    {
        $this->campaignCoachIds = [];
        $this->campaignListKey = '';
        $this->campaignSchoolId = '';
    }

    public function sendCampaign(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        if ($this->campaignTemplateId && (trim($this->campaignSubject) === '' || trim($this->campaignBody) === '')) {
            $this->useTemplate($this->campaignTemplateId);
        }

        $subject = trim($this->campaignSubject);
        $body = trim($this->campaignBody);
        if ($body === '' && $this->campaignTemplateIsDesign) {
            $body = trim($this->campaignOriginalHtml);
            $this->campaignBody = $body;
        }

        if ($subject === '' || $body === '') {
            Notification::make()->title('Recruiting Center')->body('Choose a template before creating a campaign.')->danger()->send();
            return;
        }

        $recipients = $this->campaignRecipientCoaches();

        if ($recipients->isEmpty()) {
            Notification::make()->title('Recruiting Center')->body('No coaches with email matched this campaign target.')->danger()->send();
            return;
        }

        $limit = (int) config('ghl.coach_database.campaign_send_limit', 250);
        if ($recipients->count() > $limit) {
            Notification::make()->title('Recruiting Center')->body('This campaign has ' . number_format($recipients->count()) . ' recipients. The current safety limit is ' . number_format($limit) . '. Narrow the target or raise ghl.coach_database.campaign_send_limit.')->danger()->send();
            return;
        }

        $this->isSendingCampaign = true;

        $campaignResult = app(CoachDatabaseService::class)->createEmailCampaignForUser($user, [
            'name' => $this->campaignName !== '' ? $this->campaignName : ('PLYRCard Recruiting Campaign - ' . now()->format('M j, Y g:i A')),
            'subjectLine' => $subject,
            'previewText' => $this->campaignPreviewText,
            'fromName' => (string) ($this->previewTemplate['fromName'] ?? $user->name ?? 'PLYRCard'),
            'fromEmail' => (string) ($this->previewTemplate['fromEmail'] ?? ''),
            'html' => $body,
        ]);

        if (! ($campaignResult['success'] ?? false)) {
            $this->isSendingCampaign = false;
            Notification::make()->title('Recruiting Center')->body($campaignResult['error'] ?? 'Unable to create campaign.')->danger()->send();
            return;
        }

        $sent = 0;
        $failed = 0;

        foreach ($recipients as $coach) {
            $personalizedSubject = $this->replaceCampaignTokens($subject, $coach);
            $personalizedBody = $this->replaceCampaignTokens($body, $coach);

            $payload = [
                'contact_id' => (string) ($coach['id'] ?? ''),
                'contactId' => (string) ($coach['id'] ?? ''),
                'subject' => $personalizedSubject,
                'body' => $personalizedBody,
                'html' => $personalizedBody,
                'text' => trim(strip_tags($personalizedBody)),
                'to' => (string) ($coach['email'] ?? ''),
                'emailTo' => (string) ($coach['email'] ?? ''),
                'fromName' => (string) ($user->name ?? 'PLYRCard'),
            ];

            $result = app(CoachDatabaseService::class)->sendEmailMessageForUser($user, $payload);
            if ($result['success'] ?? false) {
                $sent++;
            } else {
                $failed++;
            }
        }

        $this->isSendingCampaign = false;

        $campaignId = (string) ($campaignResult['campaign_id'] ?? '');
        $bodyText = 'Campaign created' . ($campaignId !== '' ? ' #' . $campaignId : '') . ' and sent to ' . number_format($sent) . ' coach' . ($sent === 1 ? '' : 'es') . ($failed ? '. Failed: ' . number_format($failed) . '.' : '.');

        $notification = Notification::make()->title('Recruiting Center')->body($bodyText);
        ($failed ? $notification->warning() : $notification->success())->send();
    }

    protected function mergeTemplateRecord(array $base, array $detail): array
    {
        $merged = $base;

        foreach ($detail as $key => $value) {
            if (is_string($value) && trim($value) === '' && filled($merged[$key] ?? null)) {
                continue;
            }

            if (is_array($value) && empty($value) && ! empty($merged[$key] ?? null)) {
                continue;
            }

            $merged[$key] = $value;
        }

        return $merged;
    }

    protected function templateSubject(array $template): string
    {
        return trim((string) ($template['subjectLine'] ?? $template['subject'] ?? $template['emailSubject'] ?? $template['raw']['subjectLine'] ?? $template['raw']['subject'] ?? ''));
    }

    protected function templatePreviewText(array $template): string
    {
        return trim((string) ($template['previewText'] ?? $template['preview'] ?? $template['raw']['previewText'] ?? $template['raw']['preview'] ?? ''));
    }

    protected function templateHtml(array $template): string
    {
        foreach ([
            'html', 'body', 'htmlBody', 'renderedHtml', 'rendered_html', 'editorHtml', 'content', 'editorContent',
            'templateData', 'builderData', 'dnd', 'dndData',
            'raw.html', 'raw.body', 'raw.htmlBody', 'raw.renderedHtml', 'raw.editorHtml', 'raw.editorContent',
            'raw.templateData', 'raw.builderData', 'raw.dnd', 'raw.dndData',
        ] as $key) {
            $html = $this->coerceTemplateHtml(data_get($template, $key));
            if ($html !== '') {
                return $html;
            }
        }

        return $this->coerceTemplateHtml($template['raw'] ?? null);
    }

    protected function coerceTemplateHtml(mixed $value): string
    {
        if (is_string($value)) {
            $value = trim($value);

            if ($value === '' || $this->looksLikeTemplateIdentifier($value)) {
                return '';
            }

            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $nested = $this->coerceTemplateHtml($decoded);
                if ($nested !== '') {
                    return $nested;
                }
            }

            if (str_contains($value, '<html') || str_contains($value, '<body') || str_contains($value, '<table') || str_contains($value, '<p') || str_contains($value, '<div') || str_contains($value, '<span') || str_contains($value, '<br') || str_contains($value, '{{')) {
                return $value;
            }

            if ($this->looksLikeReadableTemplateText($value)) {
                return nl2br(e($value), false);
            }

            return '';
        }

        if (! is_array($value)) {
            return '';
        }

        foreach (['html', 'body', 'htmlBody', 'content', 'message', 'previewHtml', 'text', 'editorContent', 'editorHtml', 'templateData', 'templateContent', 'builderData', 'dnd', 'dndData'] as $key) {
            if (array_key_exists($key, $value)) {
                $html = $this->coerceTemplateHtml($value[$key]);
                if ($html !== '') {
                    return $html;
                }
            }
        }

        foreach (['props', 'data', 'attributes'] as $key) {
            if (isset($value[$key]) && is_array($value[$key])) {
                foreach (['html', 'text', 'content', 'body', 'value', 'label'] as $nestedKey) {
                    if (array_key_exists($nestedKey, $value[$key])) {
                        $html = $this->coerceTemplateHtml($value[$key][$nestedKey]);
                        if ($html !== '') {
                            return $html;
                        }
                    }
                }
            }
        }

        foreach (['children', 'blocks', 'rows', 'columns', 'elements', 'nodes', 'values', 'items', 'cells', 'contents'] as $key) {
            if (isset($value[$key]) && is_array($value[$key])) {
                $parts = [];
                foreach ($value[$key] as $child) {
                    $html = $this->coerceTemplateHtml($child);
                    if ($html !== '') {
                        $parts[] = $html;
                    }
                }
                if (! empty($parts)) {
                    return implode("\n", $parts);
                }
            }
        }

        foreach (['design', 'builder', 'data', 'email', 'template', 'editor', 'root', 'document', 'templateData', 'contentData', 'unlayer', 'unlayerData'] as $key) {
            if (isset($value[$key])) {
                $html = $this->coerceTemplateHtml($value[$key]);
                if ($html !== '') {
                    return $html;
                }
            }
        }

        $designPreview = $this->renderTemplateDesignPreview($value);
        if ($designPreview !== '') {
            return $designPreview;
        }

        $parts = $this->collectReadableTemplateText($value);
        if (! empty($parts)) {
            return collect($parts)->unique()->map(fn (string $text): string => '<p>' . e($text) . '</p>')->implode("\n");
        }

        return '';
    }

    protected function renderTemplateDesignPreview(array $value): string
    {
        $fragments = [];
        $this->collectTemplateDesignFragments($value, $fragments);

        if (empty($fragments)) {
            return '';
        }

        $html = collect($fragments)
            ->unique(fn (array $fragment): string => ($fragment['type'] ?? '') . ':' . md5((string) ($fragment['value'] ?? '')))
            ->take(80)
            ->map(function (array $fragment): string {
                $type = (string) ($fragment['type'] ?? 'text');
                $value = trim((string) ($fragment['value'] ?? ''));

                if ($value === '') {
                    return '';
                }

                if ($type === 'html') {
                    return $value;
                }

                if ($type === 'image') {
                    return '<div style="margin:14px 0;text-align:center"><img src="' . e($value) . '" alt="" style="max-width:100%;height:auto;border-radius:10px;display:inline-block" /></div>';
                }

                if ($type === 'link') {
                    return '<p><a href="' . e($value) . '">' . e($value) . '</a></p>';
                }

                return '<p>' . nl2br(e($value), false) . '</p>';
            })
            ->filter()
            ->implode("
");

        return $html !== '' ? '<div style="font-family:Arial,Helvetica,sans-serif;line-height:1.55;color:#111827">' . $html . '</div>' : '';
    }

    protected function collectTemplateDesignFragments(mixed $value, array &$fragments, int $depth = 0): void
    {
        if ($depth > 14) {
            return;
        }

        if (is_string($value)) {
            $text = trim($value);
            if ($text === '' || $this->looksLikeTemplateIdentifier($text)) {
                return;
            }

            $decoded = json_decode($text, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->collectTemplateDesignFragments($decoded, $fragments, $depth + 1);
                return;
            }

            if (preg_match('/<\s*(html|body|table|tr|td|div|p|span|img|a|br|h[1-6])/i', $text)) {
                $fragments[] = ['type' => 'html', 'value' => $text];
                return;
            }

            if ($this->looksLikeReadableTemplateText($text) || str_contains($text, '{')) {
                $fragments[] = ['type' => 'text', 'value' => $text];
            }

            return;
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $item) {
            $key = (string) $key;

            if (is_string($item)) {
                $text = trim($item);
                if ($text === '' || $this->looksLikeTemplateIdentifier($text)) {
                    continue;
                }

                $isUrl = Str::startsWith($text, ['http://', 'https://']);
                $imageKey = in_array($key, ['src', 'image', 'imageUrl', 'image_url', 'backgroundImage', 'background_image', 'thumbnail', 'thumbnailUrl'], true);
                $linkKey = in_array($key, ['href', 'link', 'url', 'redirectUrl'], true);

                if ($isUrl && ($imageKey || preg_match('/\.(png|jpe?g|gif|webp|svg)(\?|$)/i', $text))) {
                    $fragments[] = ['type' => 'image', 'value' => $text];
                    continue;
                }

                if ($isUrl && $linkKey) {
                    $fragments[] = ['type' => 'link', 'value' => $text];
                    continue;
                }

                if (in_array($key, ['html', 'body', 'content', 'text', 'value', 'message', 'label', 'title', 'alt', 'heading', 'paragraph'], true)) {
                    $this->collectTemplateDesignFragments($text, $fragments, $depth + 1);
                }
            } elseif (is_array($item)) {
                $this->collectTemplateDesignFragments($item, $fragments, $depth + 1);
            }
        }
    }

    protected function collectReadableTemplateText(array $value): array
    {
        $parts = [];

        foreach ($value as $key => $item) {
            if (is_string($item) && in_array((string) $key, ['text', 'content', 'body', 'message', 'value', 'label', 'title'], true) && $this->looksLikeReadableTemplateText($item)) {
                $parts[] = trim(strip_tags($item));
            } elseif (is_array($item)) {
                $parts = array_merge($parts, $this->collectReadableTemplateText($item));
            }
        }

        return $parts;
    }

    protected function looksLikeTemplateIdentifier(string $value): bool
    {
        $value = trim($value);

        return (bool) preg_match('/^[a-f0-9]{16,}$/i', $value)
            || ((bool) preg_match('/^[A-Za-z0-9_-]{18,}$/', $value) && ! str_contains($value, ' '));
    }

    protected function looksLikeReadableTemplateText(string $value): bool
    {
        $value = trim(strip_tags($value));

        if (strlen($value) < 25 || ! str_contains($value, ' ')) {
            return false;
        }

        if ($this->looksLikeTemplateIdentifier($value)) {
            return false;
        }

        return (bool) preg_match('/[.!?,]|\s(the|and|you|your|coach|school|hi|hello|thanks)\s/i', ' ' . $value . ' ');
    }

    protected function isDesignedTemplateHtml(string $html): bool
    {
        $lower = strtolower(ltrim($html));

        return $html !== '' && (
            str_starts_with($lower, '<!doctype')
            || str_starts_with($lower, '<html')
            || str_contains($lower, '<body')
            || str_contains($lower, '<table')
            || str_contains($lower, '<img')
            || str_contains($lower, 'editorcontent')
        );
    }

    protected function extractEditableTemplateBlocks(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $document = $this->loadTemplateDom($html);
        if (! $document) {
            return [];
        }

        $blocks = [];
        $textNumber = 1;
        $imageNumber = 1;

        $walk = function ($node) use (&$walk, &$blocks, &$textNumber, &$imageNumber): void {
            if ($node instanceof \DOMText) {
                $text = trim(preg_replace('/\s+/', ' ', $node->wholeText ?? ''));
                if ($this->isEditableTemplateText($text)) {
                    $blocks[] = [
                        'type' => 'text',
                        'label' => 'Text ' . $textNumber++,
                        'value' => $text,
                    ];
                }
                return;
            }

            if ($node instanceof \DOMElement && strtolower($node->tagName) === 'img') {
                $src = trim((string) $node->getAttribute('src'));
                if ($src !== '') {
                    $blocks[] = [
                        'type' => 'image',
                        'label' => 'Image ' . $imageNumber++,
                        'value' => $src,
                    ];
                }
            }

            if ($node->hasChildNodes()) {
                foreach (iterator_to_array($node->childNodes) as $child) {
                    $walk($child);
                }
            }
        };

        $body = $document->getElementsByTagName('body')->item(0) ?: $document->documentElement;
        if ($body) {
            $walk($body);
        }

        return array_values(array_slice($blocks, 0, 80));
    }

    protected function rebuildTemplateHtmlFromEditableBlocks(): string
    {
        $html = $this->campaignOriginalHtml;
        if (trim($html) === '') {
            return '';
        }

        $document = $this->loadTemplateDom($html);
        if (! $document) {
            return $html;
        }

        $blocks = array_values($this->campaignEditableBlocks ?? []);
        $textIndex = 0;
        $imageIndex = 0;

        $walk = function ($node) use (&$walk, $blocks, &$textIndex, &$imageIndex, $document): void {
            if ($node instanceof \DOMText) {
                $text = trim(preg_replace('/\s+/', ' ', $node->wholeText ?? ''));
                if ($this->isEditableTemplateText($text)) {
                    $textBlocks = array_values(array_filter($blocks, fn ($block): bool => ($block['type'] ?? '') === 'text'));
                    if (isset($textBlocks[$textIndex])) {
                        $node->nodeValue = (string) ($textBlocks[$textIndex]['value'] ?? '');
                    }
                    $textIndex++;
                }
                return;
            }

            if ($node instanceof \DOMElement && strtolower($node->tagName) === 'img') {
                $imageBlocks = array_values(array_filter($blocks, fn ($block): bool => ($block['type'] ?? '') === 'image'));
                if (isset($imageBlocks[$imageIndex])) {
                    $src = trim((string) ($imageBlocks[$imageIndex]['value'] ?? ''));
                    if ($src !== '') {
                        $node->setAttribute('src', $src);
                    }
                }
                $imageIndex++;
            }

            if ($node->hasChildNodes()) {
                foreach (iterator_to_array($node->childNodes) as $child) {
                    $walk($child);
                }
            }
        };

        $body = $document->getElementsByTagName('body')->item(0) ?: $document->documentElement;
        if ($body) {
            $walk($body);
        }

        return $document->saveHTML() ?: $html;
    }

    protected function loadTemplateDom(string $html): ?\DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $document : null;
    }

    protected function isEditableTemplateText(string $text): bool
    {
        if ($text === '') {
            return false;
        }

        if (strlen($text) < 2) {
            return false;
        }

        $lower = strtolower($text);
        if (str_contains($lower, '<!--') || str_contains($lower, 'doctype') || str_contains($lower, 'xmlns')) {
            return false;
        }

        return preg_match('/[a-zA-Z0-9]/', $text) === 1;
    }

    protected function replaceCampaignTokens(string $content, array $coach): string
    {
        $replacements = [
            '{{coach_name}}' => (string) ($coach['name'] ?? ''),
            '{{first_name}}' => (string) ($coach['first_name'] ?? ''),
            '{{last_name}}' => (string) ($coach['last_name'] ?? ''),
            '{{school}}' => (string) ($coach['school'] ?? ''),
            '{{email}}' => (string) ($coach['email'] ?? ''),
        ];

        return strtr($content, $replacements);
    }

    protected function campaignRecipientCoaches(): Collection
    {
        $coaches = collect($this->allCoaches())
            ->filter(fn (array $coach): bool => filled($coach['id'] ?? null) && filled($coach['email'] ?? null));

        return match ($this->campaignTargetMode) {
            'all' => $coaches->values(),
            'list' => $this->campaignListKey === ''
                ? collect()
                : $coaches->filter(function (array $coach): bool {
                    $list = collect($this->lists)->firstWhere('key', $this->campaignListKey);
                    $tag = strtolower(trim((string) ($list['tag'] ?? '')));
                    if ($tag === '') {
                        return false;
                    }
                    return collect($coach['tags'] ?? [])->contains(fn ($existing): bool => strtolower(trim((string) $existing)) === $tag);
                })->values(),
            'school' => $this->campaignSchoolId === ''
                ? collect()
                : $coaches->filter(function (array $coach): bool {
                    $school = collect($this->allSchools())->firstWhere('id', $this->campaignSchoolId);
                    if (! $school) {
                        return false;
                    }
                    $businessId = (string) ($school['business_id'] ?? $school['id'] ?? '');
                    return (string) ($coach['business_id'] ?? '') === $businessId
                        || trim((string) ($coach['school'] ?? '')) === trim((string) ($school['name'] ?? ''));
                })->values(),
            default => $coaches->filter(fn (array $coach): bool => in_array((string) ($coach['id'] ?? ''), $this->campaignCoachIds, true))->values(),
        };
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


    public function getPreviewTemplateProperty(): ?array
    {
        if (! $this->previewTemplateId) {
            return collect($this->templates)->first() ?: null;
        }

        if (isset($this->templateDetails[$this->previewTemplateId])) {
            return $this->templateDetails[$this->previewTemplateId];
        }

        return collect($this->templates)->firstWhere('id', $this->previewTemplateId);
    }

    public function getPreviewTemplateSubjectProperty(): string
    {
        $template = $this->previewTemplate;
        return is_array($template) ? $this->templateSubject($template) : '';
    }

    public function getPreviewTemplateHtmlProperty(): string
    {
        $template = $this->previewTemplate;
        if (! is_array($template)) {
            return '';
        }

        return $this->templateHtml($template);
    }


    public function getCampaignEditablePreviewProperty(): string
    {
        $html = trim($this->campaignBody) !== '' ? $this->campaignBody : $this->campaignOriginalHtml;

        if ($html === '') {
            $html = '<p>Start typing your message.</p>';
        }

        $sampleCoach = $this->campaignRecipientCoaches()->first()
            ?: collect($this->allCoaches())->first(fn (array $coach): bool => filled($coach['email'] ?? null))
            ?: [
                'name' => 'Coach Smith',
                'first_name' => 'Coach',
                'last_name' => 'Smith',
                'school' => 'Sample University',
                'title' => 'Head Coach',
                'email' => 'coach@example.com',
            ];

        return $this->makeTemplateHtmlEditable(
            $this->replaceCampaignTokens($html, is_array($sampleCoach) ? $sampleCoach : [])
        );
    }

    protected function makeTemplateHtmlEditable(string $html): string
    {
        $html = trim($html);

        if (! str_contains(strtolower($html), '<html')) {
            $html = '<!doctype html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>';
        }

        $editorScript = <<<'HTML'
<style>
html,body{min-height:100%;}
body{cursor:text;}
body.plyr-editing-ready *{box-sizing:border-box;}
body.plyr-editing-ready a{cursor:text!important;}
body.plyr-editing-ready img{cursor:pointer!important;outline:1px dashed rgba(255,90,61,.55);outline-offset:4px;}
body.plyr-editing-ready img:hover{outline:2px solid #ff5a3d;}
.plyr-edit-focus{outline:2px solid #ff5a3d!important;outline-offset:2px;background:rgba(255,90,61,.05)!important;}
</style>
<script>
(function(){
    var sendTimer = null;

    function sendNow(){
        clearTimeout(sendTimer);
        sendTimer = setTimeout(function(){
            document.querySelectorAll('.plyr-edit-focus').forEach(function(el){ el.classList.remove('plyr-edit-focus'); });
            parent.postMessage({ type: 'plyr-campaign-html', html: document.documentElement.outerHTML }, '*');
        }, 180);
    }

    function command(cmd, value){
        document.body.focus();
        try { document.execCommand(cmd, false, value || null); } catch(e) {}
        sendNow();
    }

    function replaceImage(img){
        var current = img.getAttribute('src') || '';
        var next = prompt('Paste image URL', current);
        if (next !== null && next.trim() !== '') {
            img.setAttribute('src', next.trim());
            sendNow();
        }
    }

    function prepare(){
        document.designMode = 'on';
        if (document.body) {
            document.body.setAttribute('contenteditable', 'true');
            document.body.classList.add('plyr-editing-ready');
            document.body.spellcheck = true;
        }

        document.querySelectorAll('a').forEach(function(a){
            a.addEventListener('click', function(e){ e.preventDefault(); });
        });

        document.querySelectorAll('img').forEach(function(img){
            img.setAttribute('title', 'Click to change image');
            img.addEventListener('click', function(e){
                e.preventDefault();
                e.stopPropagation();
                replaceImage(img);
            });
        });

        document.addEventListener('focusin', function(e){
            document.querySelectorAll('.plyr-edit-focus').forEach(function(el){ el.classList.remove('plyr-edit-focus'); });
            if (e.target && e.target !== document.body && e.target.nodeType === 1) {
                e.target.classList.add('plyr-edit-focus');
            }
        });
        document.addEventListener('input', sendNow);
        document.addEventListener('keyup', sendNow);
        document.addEventListener('mouseup', sendNow);
        document.addEventListener('blur', sendNow, true);
        document.addEventListener('paste', function(e){
            if (! e.target || ! e.target.isContentEditable) return;
            e.preventDefault();
            var text = (e.clipboardData || window.clipboardData).getData('text/plain');
            document.execCommand('insertText', false, text);
            sendNow();
        });

        window.addEventListener('message', function(event){
            if (! event.data || event.data.type !== 'plyr-campaign-command') return;
            if (event.data.command === 'createLink') {
                var url = prompt('Paste link URL', 'https://');
                if (url && url.trim() !== '') command('createLink', url.trim());
                return;
            }
            command(event.data.command || '');
        });

        sendNow();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', prepare);
    else prepare();
})();
</script>
HTML;

        if (preg_match('/<\/body\s*>/i', $html)) {
            return preg_replace('/<\/body\s*>/i', $editorScript . '</body>', $html, 1) ?: ($html . $editorScript);
        }

        return $html . $editorScript;
    }

    protected function appendTokenToHtmlBody(string $html, string $token): string
    {
        $html = trim($html);

        if ($html === '') {
            return '<p>' . e($token) . '</p>';
        }

        $addition = '<span> ' . e($token) . '</span>';

        if (preg_match('/<\/body\s*>/i', $html)) {
            return preg_replace('/<\/body\s*>/i', $addition . '</body>', $html, 1) ?: ($html . $addition);
        }

        return $html . $addition;
    }

    public function getCampaignRenderedPreviewProperty(): string
    {
        $body = trim($this->campaignBody) !== '' ? $this->campaignBody : $this->previewTemplateHtml;

        if ($body === '') {
            $body = '<p>Write your message to preview it.</p>';
        }

        $sampleCoach = $this->campaignRecipientCoaches()->first()
            ?: collect($this->allCoaches())->first(fn (array $coach): bool => filled($coach['email'] ?? null))
            ?: [
                'name' => 'Coach Smith',
                'first_name' => 'Coach',
                'last_name' => 'Smith',
                'school' => 'Sample University',
                'title' => 'Head Coach',
                'email' => 'coach@example.com',
            ];

        $renderedBody = $this->replaceCampaignTokens($body, is_array($sampleCoach) ? $sampleCoach : []);
        $subject = e($this->replaceCampaignTokens($this->campaignSubject ?: $this->previewTemplateSubject ?: 'Campaign preview', is_array($sampleCoach) ? $sampleCoach : []));
        $preheader = e($this->replaceCampaignTokens($this->campaignPreviewText, is_array($sampleCoach) ? $sampleCoach : []));

        if (str_contains(strtolower($renderedBody), '<html')) {
            return $renderedBody;
        }

        return '<!doctype html><html><head><meta charset="utf-8"><style>body{margin:0;background:#f7f7f8;color:#111827;font-family:Arial,sans-serif}.wrap{max-width:720px;margin:0 auto;background:#fff;min-height:100vh}.head{padding:18px 22px;border-bottom:1px solid #e5e7eb}.subj{font-size:18px;font-weight:700}.prev{color:#6b7280;margin-top:6px}.body{padding:22px;line-height:1.55}img{max-width:100%;height:auto}table{max-width:100%}</style></head><body><div class="wrap"><div class="head"><div class="subj">' . $subject . '</div>' . ($preheader !== '' ? '<div class="prev">' . $preheader . '</div>' : '') . '</div><div class="body">' . $renderedBody . '</div></div></body></html>';
    }

    public function getCampaignRecipientCountProperty(): int
    {
        return $this->campaignRecipientCoaches()->count();
    }

    public function getCampaignCoachResultsProperty(): array
    {
        $query = strtolower(trim($this->campaignCoachSearch));

        return collect($this->allCoaches())
            ->filter(fn (array $coach): bool => filled($coach['id'] ?? null) && filled($coach['email'] ?? null))
            ->filter(function (array $coach) use ($query): bool {
                if ($query === '') {
                    return true;
                }

                $haystack = strtolower(implode(' ', [$coach['name'] ?? '', $coach['email'] ?? '', $coach['school'] ?? '', $coach['title'] ?? '']));

                return str_contains($haystack, $query);
            })
            ->take(40)
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