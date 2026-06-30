<?php

namespace App\Filament\Pages\Concerns;

use App\Services\CoachDatabaseService;
use App\Services\GoHighLevelService;
use App\Support\TrackingLinkRewriter;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;

trait InteractsWithCoachDatabase
{
    use WithFileUploads;
    public array $lists = [];
    public array $stats = [];
    public array $topSchools = [];
    public array $conversations = [];
    public array $messages = [];
    public array $dashboardRecentActivity = [];
    public array $dashboardActivitySummary = [];
    public array $templates = [];
    public array $templateDetails = [];
    public string $templateSourceSummary = '';
    public array $templateSourceDebug = [];
    public ?string $templateConnectionKey = null;

    /**
     * Per-request memoization for expensive cache reads/search indexes.
     * These stay protected so Livewire does not try to serialize the full dataset repeatedly.
     */
    protected ?array $coachDatabaseSnapshotMemo = null;
    protected ?array $coachSearchIndexMemo = null;
    protected ?array $schoolCoachIndexMemo = null;

    public bool $allowed = false;
    public bool $locked = false;
    public ?string $reason = null;
    public ?string $error = null;

    public string $section = 'dashboard';
    public string $search = '';
    public string $coachSearch = '';
    public string $conversationSearch = '';
    public string $conversationSchoolFilter = '';
    public string $composeSchoolSearch = '';
    public string $favoriteSchoolSearch = '';
    public string $listSchoolSearch = '';
    public string $divisionFilter = '';
    public string $conferenceFilter = '';
    public string $sort = 'name';
    public string $schoolViewMode = 'grid';
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
    public string $templatePreviewText = '';
    public string $templateGraphicUrl = '';
    public $templateGraphicUpload = null;
    public $templateInlineImageUpload = null;
    public string $composeGraphicUrl = '';
    public $composeGraphicUpload = null;
    public ?string $selectedTemplateId = null;
    public bool $templateIsNew = true;
    public bool $isSavingTemplate = false;

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

        if ($this->section === 'dashboard') {
            $this->loadDashboardActivity();
        }

        if ($this->section === 'conversations') {
            $this->loadConversations();
        }

        if (in_array($this->section, ['campaigns', 'compose'], true)) {
            $this->loadTemplates();
        }

        if ($this->section === 'compose') {
            $this->campaignTargetMode = $this->campaignTargetMode ?: 'list';
            $schoolId = trim((string) request()->query('school', ''));
            if ($schoolId !== '') {
                $this->selectComposeSchool($schoolId);
            }
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
            'compose' => \App\Filament\Pages\CoachDatabaseComposeEmail::getUrl(),
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

    public function openSchoolDashboardModal(string $schoolId): void
    {
        $schoolId = trim($schoolId);
        if ($schoolId === '') {
            return;
        }

        $school = collect($this->allSchools())->first(function (array $item) use ($schoolId): bool {
            $nameHash = md5(strtolower(trim((string) ($item['name'] ?? ''))));
            return (string) ($item['id'] ?? '') === $schoolId
                || (string) ($item['business_id'] ?? '') === $schoolId
                || $nameHash === $schoolId
                || strcasecmp(trim((string) ($item['name'] ?? '')), $schoolId) === 0;
        });

        if (is_array($school)) {
            $resolvedId = (string) ($school['id'] ?? $school['business_id'] ?? $schoolId);
            $this->selectedSchoolId = $resolvedId;
            $this->loadSchoolCoachesById($resolvedId);
            return;
        }

        $this->selectedSchoolId = $schoolId;
    }

    public function openDashboardEngagedSchool(int $index): void
    {
        $schools = $this->dashboardTopEngagedSchools;
        $school = $schools[$index] ?? null;

        if (! is_array($school)) {
            return;
        }

        $schoolId = (string) ($school['id'] ?? $school['business_id'] ?? '');

        if ($schoolId === '' && ! empty($school['name'])) {
            $schoolId = md5(strtolower(trim((string) $school['name'])));
        }

        if ($schoolId !== '') {
            $this->openSchoolDashboardModal($schoolId);
        }
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

    public function updatedConversationSchoolFilter(): void
    {
        $this->selectedConversationId = null;
        $this->messages = [];
    }

    public function updatedCampaignTargetMode(): void
    {
        $this->campaignCoachIds = [];
        $this->campaignListKey = '';
        $this->campaignSchoolId = '';
        $this->campaignCoachSearch = '';
        $this->composeSchoolSearch = '';
    }

    public function updatedCampaignSchoolId(): void
    {
        $this->campaignCoachIds = [];
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


    protected function recruitingDashboardActivityCacheKey($user): string
    {
        return 'coach-database:dashboard-activity:' . ($user?->id ?? 'guest') . ':' . md5((string) ($user?->ghl_location_id ?? '') . '|' . substr((string) ($user?->ghl_api_key ?? ''), -12));
    }

    protected function persistDashboardStatsAfterTracking($user): void
    {
        $snapshot = Cache::get($this->activeCacheKey(), $this->emptySnapshot());
        $snapshot['stats'] = array_merge($snapshot['stats'] ?? [], $this->stats ?? []);
        $snapshot['cached_at'] = now()->toDateTimeString();
        $this->storeSnapshot($snapshot);

        if ($user) {
            Cache::forget($this->recruitingDashboardActivityCacheKey($user));
        }
    }

    public function loadDashboardActivity(): void
    {
        if (! $this->allowed || $this->locked) {
            return;
        }

        $user = Auth::user();
        if (! $user) {
            return;
        }

        // Dashboard tracking numbers come from live contact custom fields.
        // Do not keep the old 10-minute cached summary here, otherwise GHL can be
        // updated while the dashboard still shows stale zero values.
        $cacheKey = $this->recruitingDashboardActivityCacheKey($user);
        Cache::forget($cacheKey);
        $summary = app(GoHighLevelService::class)->getRecruitingDashboardActivityForUser($user);

        if (! is_array($summary)) {
            return;
        }

        $remoteStats = $summary['stats'] ?? [];
        if (is_array($remoteStats)) {
            $this->stats = array_merge($this->stats, array_filter($remoteStats, fn ($value) => $value !== null));
        }

        $recent = $summary['recent_activity'] ?? [];
        $this->dashboardRecentActivity = is_array($recent) ? array_values($recent) : [];
        $this->dashboardActivitySummary = $summary;

        if (empty($this->conversations) && ! empty($summary['conversations']) && is_array($summary['conversations'])) {
            $this->conversations = array_values($summary['conversations']);
        }
    }

    public function startNewConversation(): void
    {
        $this->showNewConversationComposer = true;
        $this->selectedConversationId = null;
        $this->selectedCoachId = null;
        $this->messages = [];
        $this->messageLastId = null;
        try {
            app(GoHighLevelService::class)->incrementRecruitingMetricForUser(Auth::user(), 'emails_sent', 1);
            $this->stats['emails_sent'] = (int) ($this->stats['emails_sent'] ?? 0) + 1;
        } catch (\Throwable $exception) {
            // Keep email sending successful even if the dashboard counter cannot be updated immediately.
        }

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
        $user = Auth::user();
        if (! $user) {
            return;
        }

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

        $contactId = trim((string) $contactId);
        $trackingContext = [
            'athlete_id' => $user->id,
            'contact_id' => $contactId,
            'ghl_contact_id' => $contactId,
            'email_subject' => $subject,
            'source' => 'coach_database_email',
        ];

        try {
            app(GoHighLevelService::class)->ensureRecruitingTrackingFieldsForUser($user);
        } catch (\Throwable $exception) {
            \Log::warning('Recruiting tracking field preparation failed before email send.', [
                'contact_id' => $contactId,
                'error' => $exception->getMessage(),
            ]);
        }

        $trackedBody = $body;
        try {
            $rewriter = app(TrackingLinkRewriter::class);
            $trackedBody = $rewriter->rewriteHtml($trackedBody, $trackingContext);
            $trackedBody = $rewriter->appendOpenPixel($trackedBody, $trackingContext);
        } catch (\Throwable $exception) {
            \Log::warning('Recruiting email link rewrite failed. Sending original body.', [
                'contact_id' => $contactId,
                'error' => $exception->getMessage(),
            ]);
        }

        $payload = [
            'contact_id' => $contactId,
            'contactId' => $contactId,
            'conversation_id' => $this->selectedConversationId,
            'conversationId' => $this->selectedConversationId,
            'subject' => $subject,
            'body' => $trackedBody,
            'html' => $trackedBody,
            'text' => trim(strip_tags($trackedBody)),
            'fromName' => (string) ($user->name ?? 'PLYRCard'),
            'to' => $to,
            'emailTo' => $to,
        ];

        $this->isSendingEmail = true;
        $result = app(CoachDatabaseService::class)->sendEmailMessageForUser($user, $payload);
        $this->isSendingEmail = false;

        if (! ($result['success'] ?? false)) {
            Notification::make()->title('Recruiting Center')->body($result['error'] ?? 'Unable to send email.')->danger()->send();
            return;
        }

        try {
            $trackResult = app(GoHighLevelService::class)->trackRecruitingEmailSentForUser($user, $contactId, [
                'source' => 'coach_database_email',
                'subject' => $subject,
                'to' => $to,
                'host' => request()?->getHost(),
            ]);

            \Log::info('Recruiting email sent tracking result.', [
                'contact_id' => $contactId,
                'tracked' => (bool) ($trackResult['success'] ?? false),
                'result' => $trackResult,
            ]);

            $this->stats['email_sent_count'] = (int) ($this->stats['email_sent_count'] ?? 0) + 1;
            $this->stats['emails_sent'] = (int) ($this->stats['emails_sent'] ?? 0) + 1;
            $this->persistDashboardStatsAfterTracking($user);
        } catch (\Throwable $exception) {
            \Log::warning('Recruiting email sent tracking failed.', [
                'contact_id' => $contactId,
                'error' => $exception->getMessage(),
            ]);
        }

        $this->emailSubject = '';
        $this->emailBody = '';
        $this->showNewConversationComposer = false;

        if ($this->selectedConversationId) {
            $this->messages = [];
            $this->messageLastId = null;
            $this->loadConversationMessages();
        }

        $this->loadDashboardActivity();

        Notification::make()->title('Recruiting Center')->body('Email sent.')->success()->send();
    }

    public function loadTemplates(): void
    {
        $builtIn = $this->hardcodedEmailTemplates();
        $user = Auth::user();
        $currentConnectionKey = $this->templateConnectionKeyForUser($user);

        if ($this->templateConnectionKey !== $currentConnectionKey) {
            $this->templateConnectionKey = $currentConnectionKey;
            $this->templateDetails = [];
            $this->templates = [];
            $this->selectedTemplateId = null;
            $this->previewTemplateId = null;
            $this->campaignTemplateId = null;
            $this->templateIsNew = true;
        }

        $this->templateDetails = collect($this->templateDetails)
            ->filter(fn ($template): bool => is_array($template))
            ->filter(fn (array $template): bool => (string) ($template['connection_key'] ?? $currentConnectionKey) === $currentConnectionKey)
            ->all();

        $result = $user
            ? app(CoachDatabaseService::class)->getEmailTemplatesForUser($user)
            : ['success' => false, 'templates' => [], 'error' => 'No authenticated user.'];

        $ghlTemplates = collect($result['templates'] ?? [])
            ->filter(fn ($template): bool => is_array($template))
            ->map(function (array $template) use ($currentConnectionKey): array {
                $id = (string) ($template['id'] ?? $template['_id'] ?? $template['templateId'] ?? '');

                return array_merge($template, [
                    'id' => $id,
                    'source_type' => 'ghl',
                    'connection_key' => $currentConnectionKey,
                ]);
            })
            ->filter(fn (array $template): bool => trim((string) ($template['id'] ?? '')) !== '')
            ->unique(fn (array $template): string => (string) ($template['id'] ?? ''))
            ->values();

        $this->templates = $ghlTemplates
            ->merge($builtIn)
            ->unique(fn (array $template): string => (string) ($template['id'] ?? ''))
            ->values()
            ->all();

        $this->templateSourceSummary = $ghlTemplates->isNotEmpty()
            ? 'GHL email templates loaded for this API key/location. Built-in PLYRCard templates are included as fallbacks.'
            : 'No GHL templates found for this API key/location. Showing built-in PLYRCard templates.';
        $this->templateSourceDebug = $result['debug'] ?? [];
        $this->error = null;

        if (! ($result['success'] ?? false) && $ghlTemplates->isEmpty() && filled($result['error'] ?? null)) {
            $this->templateSourceDebug = array_merge($this->templateSourceDebug, [[
                'stage' => 'ghl_template_load_failed',
                'error' => $result['error'],
            ]]);
        }

        if ($this->campaignTemplateId && collect($this->templates)->contains(fn (array $template): bool => (string) ($template['id'] ?? '') === $this->campaignTemplateId)) {
            return;
        }

        if ($this->selectedTemplateId && collect($this->templates)->contains(fn (array $template): bool => (string) ($template['id'] ?? '') === $this->selectedTemplateId)) {
            $this->selectTemplate($this->selectedTemplateId);
            return;
        }

        if ($this->templateIsNew && trim(strip_tags((string) $this->templateBody)) === '') {
            $this->templateName = $this->templateName ?: 'New Recruiting Email';
            $this->templateSubject = $this->templateSubject ?: '{{AthleteName}} - {{Position}} interested in {{SchoolName}}';
            $this->templatePreviewText = $this->templatePreviewText ?: 'Quick intro, profile, and highlight link from {{AthleteName}}.';
            $this->templateBody = $this->starterTemplateHtml();
        }

        if (! empty($this->templates[0]['id']) && ! $this->templateIsNew) {
            $this->selectTemplate((string) $this->templates[0]['id']);
        }
    }

    protected function templateConnectionKeyForUser($user): string
    {
        if (! $user) {
            return 'guest';
        }

        $locationId = trim((string) ($user->ghl_location_id ?? config('ghl.location_id') ?? ''));
        $token = trim((string) ($user->ghl_api_key ?? ''));

        return sha1((string) ($user->id ?? 'user') . '|' . $locationId . '|' . substr(sha1($token), 0, 12));
    }

    protected function hardcodedEmailTemplates(): array
    {
        return [
            [
                'id' => 'plyrcard-intro-email',
                'name' => 'Intro to Coach',
                'subjectLine' => 'Prospect for {{SchoolName}} - {{AthleteName}}',
                'previewText' => 'Quick introduction from {{AthleteName}}.',
                'body' => '<p>Hi {{CoachFirstName}},</p><p>My name is {{AthleteName}} and I am a {{GraduationYear}} {{Position}}. I wanted to introduce myself because I am very interested in {{SchoolName}}.</p><p>You can view my PLYRCard profile here: <a href="{{ProfileLink}}">{{ProfileLink}}</a></p><p>You can also watch my highlights here: <a href="{{HighlightLink}}">{{HighlightLink}}</a></p><p>Thank you for your time,<br>{{AthleteName}}</p>',
                'html' => '<p>Hi {{CoachFirstName}},</p><p>My name is {{AthleteName}} and I am a {{GraduationYear}} {{Position}}. I wanted to introduce myself because I am very interested in {{SchoolName}}.</p><p>You can view my PLYRCard profile here: <a href="{{ProfileLink}}">{{ProfileLink}}</a></p><p>You can also watch my highlights here: <a href="{{HighlightLink}}">{{HighlightLink}}</a></p><p>Thank you for your time,<br>{{AthleteName}}</p>',
                'source_type' => 'built_in',
            ],
            [
                'id' => 'plyrcard-follow-up-email',
                'name' => 'Follow Up',
                'subjectLine' => 'Following up - {{AthleteName}}',
                'previewText' => 'Following up with {{CoachFirstName}} at {{SchoolName}}.',
                'body' => '<p>Hi {{CoachFirstName}},</p><p>I wanted to follow up on my previous email and share my PLYRCard again.</p><p>Profile: <a href="{{ProfileLink}}">{{ProfileLink}}</a><br>Highlights: <a href="{{HighlightLink}}">{{HighlightLink}}</a></p><p>I would appreciate the chance to learn more about your program at {{SchoolName}}.</p><p>Thanks,<br>{{AthleteName}}</p>',
                'html' => '<p>Hi {{CoachFirstName}},</p><p>I wanted to follow up on my previous email and share my PLYRCard again.</p><p>Profile: <a href="{{ProfileLink}}">{{ProfileLink}}</a><br>Highlights: <a href="{{HighlightLink}}">{{HighlightLink}}</a></p><p>I would appreciate the chance to learn more about your program at {{SchoolName}}.</p><p>Thanks,<br>{{AthleteName}}</p>',
                'source_type' => 'built_in',
            ],
            [
                'id' => 'plyrcard-camp-invite-email',
                'name' => 'Camp / Visit Interest',
                'subjectLine' => '{{AthleteName}} - camp interest for {{SchoolName}}',
                'previewText' => 'Camp and visit interest from {{AthleteName}}.',
                'body' => '<p>Hi {{CoachFirstName}},</p><p>I am interested in learning more about upcoming camps, ID sessions, or visit opportunities at {{SchoolName}}.</p><p>I am a {{GraduationYear}} {{Position}} with {{ClubTeam}}. My GPA is {{GPA}}.</p><p>Profile: <a href="{{ProfileLink}}">{{ProfileLink}}</a><br>Highlights: <a href="{{HighlightLink}}">{{HighlightLink}}</a></p><p>Thank you,<br>{{AthleteName}}</p>',
                'html' => '<p>Hi {{CoachFirstName}},</p><p>I am interested in learning more about upcoming camps, ID sessions, or visit opportunities at {{SchoolName}}.</p><p>I am a {{GraduationYear}} {{Position}} with {{ClubTeam}}. My GPA is {{GPA}}.</p><p>Profile: <a href="{{ProfileLink}}">{{ProfileLink}}</a><br>Highlights: <a href="{{HighlightLink}}">{{HighlightLink}}</a></p><p>Thank you,<br>{{AthleteName}}</p>',
                'source_type' => 'built_in',
            ],
        ];
    }

    public function newTemplate(): void
    {
        $this->selectedTemplateId = null;
        $this->previewTemplateId = null;
        $this->campaignTemplateId = null;
        $this->templateIsNew = true;
        $this->templateName = 'New Recruiting Email';
        $this->templateSubject = '{{AthleteName}} - {{Position}} interested in {{SchoolName}}';
        $this->templatePreviewText = 'Quick intro, profile, and highlight link from {{AthleteName}}.';
        $this->templateGraphicUrl = '';
        $this->templateGraphicUpload = null;
        $this->templateInlineImageUpload = null;
        $this->templateBody = $this->starterTemplateHtml();
        $this->dispatch('rc-template-editor-refresh', body: base64_encode($this->templateBody));
    }

    public function selectTemplate(string $templateId): void
    {
        $templateId = trim($templateId);

        if ($templateId === '') {
            return;
        }

        $template = $this->loadTemplateDetail($templateId)
            ?: collect($this->templates)->firstWhere('id', $templateId);

        if (! is_array($template)) {
            return;
        }

        $this->selectedTemplateId = $templateId;
        $this->previewTemplateId = $templateId;
        $this->campaignTemplateId = null;
        $this->templateIsNew = $this->isBuiltInTemplateId($templateId);
        $this->templateName = trim((string) ($template['name'] ?? 'Untitled Template')) ?: 'Untitled Template';
        $this->templateSubject = $this->templateSubject($template);
        $this->templatePreviewText = $this->templatePreviewText($template);
        $this->templateGraphicUrl = '';
        $this->templateGraphicUpload = null;
        $this->templateInlineImageUpload = null;
        $this->templateBody = $this->templateHtmlForNativeEditor($template);
        $this->dispatch('rc-template-editor-refresh', body: base64_encode($this->templateBody));
    }

    protected function starterTemplateHtml(): string
    {
        return <<<'HTML'
<div style="max-width:680px;margin:0 auto;background:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#111827;line-height:1.65;font-size:15px;">
    <div style="padding:26px 28px 18px;border:1px solid #e5e7eb;border-radius:18px 18px 0 0;background:#ffffff;">
        <p style="margin:0 0 16px;">Hi {{CoachFirstName}},</p>
        <p style="margin:0 0 16px;">My name is <strong>{{AthleteName}}</strong>. I am a {{GraduationYear}} {{Position}} with {{ClubTeam}}, and I wanted to introduce myself because I am interested in {{SchoolName}}.</p>
        <p style="margin:0 0 16px;">I would appreciate the opportunity to share my profile, highlights, and academic information with your staff. My current GPA is {{GPA}}.</p>
        <p style="margin:0 0 12px;">
            <a class="rc-email-button" href="{{ProfileLink}}" target="_blank" style="display:block;width:100%;box-sizing:border-box;background:#ff5b32;color:#ffffff;text-decoration:none;font-weight:800;border-radius:10px;padding:12px 16px;text-align:center;margin:0 0 10px;">View PLYRCard Profile</a>
            <a class="rc-email-button" href="{{HighlightLink}}" target="_blank" style="display:block;width:100%;box-sizing:border-box;background:#111827;color:#ffffff;text-decoration:none;font-weight:800;border-radius:10px;padding:12px 16px;text-align:center;margin:0;">Watch Highlights</a>
        </p>
        <p style="margin:0 0 16px;">Thank you for your time and consideration. I look forward to learning more about your program.</p>
        <p style="margin:0;">Best,<br><strong>{{AthleteName}}</strong></p>
    </div>
    <div style="padding:18px 28px 22px;border:1px solid #e5e7eb;border-top:0;border-radius:0 0 18px 18px;background:#f9fafb;">
        <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
            <tr><td style="vertical-align:top;padding:0;"><div style="font-size:16px;font-weight:800;color:#111827;">{{AthleteName}}</div><div style="font-size:13px;color:#4b5563;margin-top:3px;">{{GraduationYear}} • {{Position}} • {{ClubTeam}}</div><div style="font-size:13px;color:#4b5563;margin-top:3px;">{{AthleteEmail}} • {{AthletePhone}}</div></td></tr>
            <tr><td style="padding-top:14px;">
                <a href="{{InstagramLink}}" target="_blank" style="display:inline-block;text-decoration:none;margin-right:8px;margin-bottom:6px;"><span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:999px;background:#000000;vertical-align:middle;"><svg width="18" height="18" viewBox="0 0 24 24" role="img" aria-label="Instagram" style="display:block;"><path fill="#ffffff" d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4c0 3.2-2.6 5.8-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8C2 4.6 4.6 2 7.8 2Zm-.2 2A3.6 3.6 0 0 0 4 7.6v8.8A3.6 3.6 0 0 0 7.6 20h8.8a3.6 3.6 0 0 0 3.6-3.6V7.6A3.6 3.6 0 0 0 16.4 4H7.6Zm9.65 1.5a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5ZM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/></svg></span></a>
                <a href="{{TwitterLink}}" target="_blank" style="display:inline-block;text-decoration:none;margin-right:8px;margin-bottom:6px;"><span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:999px;background:#000000;vertical-align:middle;"><svg width="17" height="17" viewBox="0 0 24 24" role="img" aria-label="X" style="display:block;"><path fill="#ffffff" d="M18.9 2h3.1l-6.8 7.8L23.2 22h-6.3l-4.9-7.3L6.4 22H3.3l7.3-8.4L2.8 2h6.4l4.4 6.6L18.9 2Zm-1.1 17.9h1.7L8.3 4H6.5l11.3 15.9Z"/></svg></span></a>
                <a href="{{YoutubeLink}}" target="_blank" style="display:inline-block;text-decoration:none;margin-right:8px;margin-bottom:6px;"><span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:999px;background:#000000;vertical-align:middle;"><svg width="20" height="20" viewBox="0 0 24 24" role="img" aria-label="YouTube" style="display:block;"><path fill="#ffffff" d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8ZM9.6 15.6V8.4L15.8 12l-6.2 3.6Z"/></svg></span></a>
            </td></tr>
        </table>
    </div>
</div>
HTML;
    }

    public function createTemplate(): void
    {
        $this->newTemplate();
    }

    public function saveTemplate(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $name = trim($this->templateName);
        $subject = trim($this->templateSubject);
        $bodyText = trim($this->templateBody);

        if ($name === '' || $subject === '' || $bodyText === '') {
            Notification::make()->title('Templates')->body('Add a name, subject, and message.')->danger()->send();
            return;
        }

        $this->isSavingTemplate = true;
        $this->resolveTemplateGraphicUpload();
        $html = $this->buildTemplateHtml($bodyText);

        $shouldUpdateGhl = $this->selectedTemplateId
            && ! $this->templateIsNew
            && ! $this->isBuiltInTemplateId($this->selectedTemplateId);

        $result = null;
        $updatedTemplateId = $this->selectedTemplateId;
        $updateFailures = [];

        if ($shouldUpdateGhl) {
            foreach ($this->templateUpdateCandidateIds((string) $this->selectedTemplateId) as $candidateId) {
                $result = app(CoachDatabaseService::class)->updateEmailTemplateForUser($user, $candidateId, $name, $subject, $html, $this->templatePreviewText);

                if ($result['success'] ?? false) {
                    $updatedTemplateId = $candidateId;
                    break;
                }

                $updateFailures[] = [
                    'template_id' => $candidateId,
                    'status' => $result['status'] ?? null,
                    'error' => $result['error'] ?? 'Unable to update template.',
                ];
            }

            if (! ($result['success'] ?? false) && $this->templateSaveFailedBecauseNotFound($result, $updateFailures)) {
                // Some GHL HTML-builder templates expose one id for loading and a different/internal
                // id for editing. If none of the known ids can be updated, save the edited version as
                // a new GHL template instead of failing with "Template not found" and losing the work.
                $copyName = Str::endsWith($name, ' (Edited Copy)') ? $name : $name . ' (Edited Copy)';
                $result = app(CoachDatabaseService::class)->createEmailTemplateForUser($user, $copyName, $subject, $html, $this->templatePreviewText);
                if ($result['success'] ?? false) {
                    $name = $copyName;
                    $this->templateName = $copyName;
                    $updatedTemplateId = null;
                }
            }
        } else {
            $result = app(CoachDatabaseService::class)->createEmailTemplateForUser($user, $name, $subject, $html, $this->templatePreviewText);
        }

        $this->isSavingTemplate = false;

        if (! ($result['success'] ?? false)) {
            Notification::make()->title('Templates')->body($this->templateErrorMessage($result ?? [], 'Unable to save template.'))->danger()->send();
            return;
        }

        $saved = $result['template'] ?? [];
        if (is_array($saved)) {
            $savedId = (string) ($saved['id'] ?? $saved['_id'] ?? $saved['templateId'] ?? $updatedTemplateId ?? $this->selectedTemplateId ?? '');
            if ($savedId !== '') {
                $this->selectedTemplateId = $savedId;
                $this->templateIsNew = false;
                $this->templateDetails[$savedId] = $this->mergeTemplateRecord($saved, [
                    'id' => $savedId,
                    'connection_key' => $this->templateConnectionKey,
                    'name' => $name,
                    'subjectLine' => $subject,
                    'previewText' => $this->templatePreviewText,
                    'graphicUrl' => $this->templateGraphicUrl,
                    'html' => $html,
                    'body' => $html,
                    'update_failures' => $updateFailures,
                ]);
            }
        }

        $this->loadTemplates();

        $message = ! empty($updateFailures) && blank($updatedTemplateId)
            ? 'Template saved as a new edited copy because GHL would not update the original template id.'
            : 'Template saved.';

        Notification::make()->title('Templates')->body($message)->success()->send();
    }

    protected function templateUpdateCandidateIds(string $templateId): array
    {
        $summary = collect($this->templates)->firstWhere('id', $templateId) ?: [];
        $detail = $this->templateDetails[$templateId] ?? [];
        $values = [$templateId];

        foreach ([$summary, $detail] as $record) {
            if (! is_array($record)) {
                continue;
            }

            foreach ([
                'id', '_id', 'templateId', 'template_id', 'campaignId', 'campaign_id', 'emailCampaignId', 'email_campaign_id',
                'builderId', 'builder_id', 'contentId', 'content_id', 'emailId', 'email_id', 'detail_candidate_id',
                'raw.id', 'raw._id', 'raw.templateId', 'raw.template_id', 'raw.campaignId', 'raw.campaign_id', 'raw.emailCampaignId', 'raw.email_campaign_id',
                'raw.builderId', 'raw.builder_id', 'raw.contentId', 'raw.content_id', 'raw.emailId', 'raw.email_id',
                'raw.data.id', 'raw.data._id', 'raw.data.templateId', 'raw.data.template_id', 'raw.data.campaignId', 'raw.data.builderId', 'raw.data.contentId',
                'raw.template.id', 'raw.template._id', 'raw.template.templateId', 'raw.template.campaignId',
                'raw.email.id', 'raw.email._id', 'raw.email.templateId',
                'raw.campaign.id', 'raw.campaign._id', 'raw.campaign.templateId',
            ] as $path) {
                $value = data_get($record, $path);
                if (is_scalar($value)) {
                    $values[] = (string) $value;
                }
            }
        }

        return collect($values)
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '' && ! in_array(strtolower($value), ['null', 'undefined'], true))
            ->unique()
            ->values()
            ->all();
    }

    protected function templateSaveFailedBecauseNotFound(?array $result, array $failures = []): bool
    {
        if (is_array($result) && (int) ($result['status'] ?? 0) === 404) {
            return true;
        }

        $message = strtolower((string) ($result['error'] ?? ''));
        if ($message !== '' && (str_contains($message, 'not found') || str_contains($message, 'does not exist') || str_contains($message, 'invalid template'))) {
            return true;
        }

        foreach ($failures as $failure) {
            if ((int) ($failure['status'] ?? 0) === 404) {
                return true;
            }

            $failureMessage = strtolower((string) ($failure['error'] ?? ''));
            if ($failureMessage !== '' && (str_contains($failureMessage, 'not found') || str_contains($failureMessage, 'does not exist') || str_contains($failureMessage, 'invalid template'))) {
                return true;
            }
        }

        return false;
    }

    public function deleteTemplate(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        if (! $this->selectedTemplateId || $this->templateIsNew) {
            $this->newTemplate();
            return;
        }

        $result = app(CoachDatabaseService::class)->deleteEmailTemplateForUser($user, $this->selectedTemplateId);

        if (! ($result['success'] ?? false)) {
            Notification::make()->title('Templates')->body($this->templateErrorMessage($result, 'Unable to delete template.'))->danger()->send();
            return;
        }

        $this->selectedTemplateId = null;
        $this->templateDetails = [];
        $this->templateGraphicUrl = '';
        $this->templateGraphicUpload = null;
        $this->loadTemplates();
        Notification::make()->title('Templates')->body('Template deleted.')->success()->send();
    }


    /**
     * Backward-compatible alias for older Compose Email markup/cached Livewire payloads.
     */
    public function useCampaignTemplate(string $templateId): void
    {
        $this->useTemplateForCompose($templateId);
    }

    /**
     * Backward-compatible alias for older Compose Email send buttons.
     */
    public function sendCampaign(): void
    {
        $this->sendComposedEmail();
    }

    public function useTemplateForCompose(string $templateId): void
    {
        $templateId = trim($templateId);

        if ($templateId === '') {
            return;
        }

        $template = $this->loadTemplateDetail($templateId)
            ?: collect($this->templates)->firstWhere('id', $templateId);

        if (! is_array($template)) {
            Notification::make()->title('Compose Email')->body('Template could not be opened.')->danger()->send();
            return;
        }

        $this->campaignTemplateId = $templateId;
        $this->previewTemplateId = $templateId;
        $this->campaignName = trim((string) ($template['name'] ?? 'Recruiting Email')) ?: 'Recruiting Email';
        $this->campaignSubject = $this->templateSubject($template);
        $this->campaignPreviewText = $this->templatePreviewText($template);
        $this->composeGraphicUrl = '';
        $this->campaignBody = $this->templateHtmlForNativeEditor($template);

        if (trim($this->campaignBody) === '') {
            $this->campaignBody = $this->templateTextToHtml(trim(strip_tags((string) ($template['body'] ?? $template['html'] ?? ''))));
        }
    }

    public function clearComposeTemplate(): void
    {
        $this->campaignTemplateId = null;
        $this->previewTemplateId = null;
        $this->campaignName = '';
        $this->campaignSubject = '';
        $this->campaignPreviewText = '';
        $this->campaignBody = '';
        $this->composeGraphicUrl = '';
        $this->composeGraphicUpload = null;
    }

    public function removeComposeGraphic(): void
    {
        $this->composeGraphicUrl = '';
        $this->composeGraphicUpload = null;
    }

    public function sendComposedEmail(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $subject = trim($this->campaignSubject);
        $bodyText = trim($this->campaignBody);

        if ($subject === '' || $bodyText === '') {
            Notification::make()->title('Compose Email')->body('Choose a template or write a subject and message.')->danger()->send();
            return;
        }

        $recipients = $this->campaignRecipientCoaches();

        if ($recipients->isEmpty()) {
            Notification::make()->title('Compose Email')->body('Choose at least one coach with an email address.')->danger()->send();
            return;
        }

        $limit = (int) config('ghl.coach_database.campaign_send_limit', 250);
        if ($recipients->count() > $limit) {
            Notification::make()->title('Compose Email')->body('Too many recipients. Choose fewer coaches or raise the send limit.')->danger()->send();
            return;
        }

        try {
            app(GoHighLevelService::class)->ensureRecruitingTrackingFieldsForUser($user);
        } catch (\Throwable $exception) {
            \Log::warning('Recruiting tracking field preparation failed before campaign send.', [
                'error' => $exception->getMessage(),
            ]);
        }

        $this->isSendingCampaign = true;
        $this->resolveComposeGraphicUpload();
        $html = $this->buildComposeHtml($bodyText);
        $sent = 0;
        $failed = 0;

        foreach ($recipients as $coach) {
            $contactId = trim((string) ($coach['id'] ?? ''));
            $personalizedSubject = $this->replaceCampaignTokens($subject, $coach);
            $personalizedBody = $this->replaceCampaignTokens($html, $coach);

            $trackingContext = [
                'athlete_id' => $user->id,
                'contact_id' => $contactId,
                'ghl_contact_id' => $contactId,
                'email_subject' => $personalizedSubject,
                'source' => 'coach_database_campaign_email',
            ];

            $trackedBody = $personalizedBody;
            try {
                $rewriter = app(TrackingLinkRewriter::class);
                $trackedBody = $rewriter->rewriteHtml($trackedBody, $trackingContext);
                $trackedBody = $rewriter->appendOpenPixel($trackedBody, $trackingContext);
            } catch (\Throwable $exception) {
                \Log::warning('Recruiting campaign link rewrite failed. Sending original body.', [
                    'contact_id' => $contactId,
                    'error' => $exception->getMessage(),
                ]);
            }

            $payload = [
                'contact_id' => $contactId,
                'contactId' => $contactId,
                'subject' => $personalizedSubject,
                'body' => $trackedBody,
                'html' => $trackedBody,
                'text' => trim(strip_tags($trackedBody)),
                'to' => (string) ($coach['email'] ?? ''),
                'emailTo' => (string) ($coach['email'] ?? ''),
                'fromName' => (string) ($user->name ?? 'PLYRCard'),
            ];

            $result = app(CoachDatabaseService::class)->sendEmailMessageForUser($user, $payload);
            if ($result['success'] ?? false) {
                $sent++;

                try {
                    app(GoHighLevelService::class)->trackRecruitingEmailSentForUser($user, $contactId, [
                        'source' => 'coach_database_campaign_email',
                        'subject' => $personalizedSubject,
                        'to' => (string) ($coach['email'] ?? ''),
                        'host' => request()?->getHost(),
                    ]);
                } catch (\Throwable $exception) {
                    \Log::warning('Recruiting campaign sent tracking failed.', [
                        'contact_id' => $contactId,
                        'error' => $exception->getMessage(),
                    ]);
                }
            } else {
                $failed++;
            }
        }

        $this->isSendingCampaign = false;

        if ($sent > 0) {
            $this->stats['email_sent_count'] = (int) ($this->stats['email_sent_count'] ?? 0) + $sent;
            $this->stats['emails_sent'] = (int) ($this->stats['emails_sent'] ?? 0) + $sent;
            $this->persistDashboardStatsAfterTracking($user);
            $this->loadDashboardActivity();
        }

        $message = 'Sent to ' . number_format($sent) . ' coach' . ($sent === 1 ? '' : 'es') . ($failed ? '. Failed: ' . number_format($failed) . '.' : '.');
        $notification = Notification::make()->title('Compose Email')->body($message);
        ($failed ? $notification->warning() : $notification->success())->send();
    }

    public function loadTemplateDetail(string $templateId): ?array
    {
        $templateId = trim($templateId);

        if ($templateId === '') {
            return null;
        }

        if (isset($this->templateDetails[$templateId])) {
            $cached = $this->templateDetails[$templateId];
            if (! is_array($cached) || (string) ($cached['connection_key'] ?? $this->templateConnectionKey) === (string) $this->templateConnectionKey) {
                return $cached;
            }
            unset($this->templateDetails[$templateId]);
        }

        $summary = collect($this->templates)->firstWhere('id', $templateId)
            ?: collect($this->hardcodedEmailTemplates())->firstWhere('id', $templateId);

        if (is_array($summary) && ($this->isBuiltInTemplateId($templateId) || ($summary['source_type'] ?? null) === 'built_in')) {
            $this->templateDetails[$templateId] = $summary;
            return $summary;
        }

        if (Auth::user()) {
            $result = app(CoachDatabaseService::class)->getEmailTemplateForUser(Auth::user(), $templateId);

            if (($result['success'] ?? false) && is_array($result['template'] ?? null)) {
                $detail = $this->mergeTemplateRecord(is_array($summary) ? $summary : [], array_merge($result['template'], [
                    'id' => $templateId,
                    'source_type' => 'ghl',
                    'connection_key' => $this->templateConnectionKey,
                ]));

                $this->templateDetails[$templateId] = $detail;
                return $detail;
            }
        }

        if (is_array($summary)) {
            $this->templateDetails[$templateId] = $summary;
            return $summary;
        }

        return null;
    }

    public function insertTemplateVariable(string $token, string $field = 'body'): void
    {
        $token = trim($token);

        if (! $this->isAllowedTemplateToken($token)) {
            return;
        }

        if ($field === 'subject') {
            $this->templateSubject = $this->appendTokenOnce($this->templateSubject, $token);
            return;
        }

        if ($field === 'preview') {
            $this->templatePreviewText = $this->appendTokenOnce($this->templatePreviewText, $token);
            return;
        }

        $this->templateBody = $this->appendTokenOnce($this->templateBody, $token);
    }

    public function removeTemplateGraphic(): void
    {
        $this->templateGraphicUrl = '';
        $this->templateGraphicUpload = null;
    }

    public function uploadTemplateEditorImage(): array
    {
        $user = Auth::user();

        if (! $user || ! $this->templateInlineImageUpload) {
            return ['success' => false, 'error' => 'Choose an image first.'];
        }

        try {
            $this->validate([
                'templateInlineImageUpload' => ['image', 'max:25600'],
            ]);

            $result = app(CoachDatabaseService::class)->uploadMediaForUser($user, $this->templateInlineImageUpload);
            $this->templateInlineImageUpload = null;

            if (! ($result['success'] ?? false)) {
                return [
                    'success' => false,
                    'error' => $this->templateErrorMessage($result, 'Unable to upload image.'),
                ];
            }

            return [
                'success' => true,
                'url' => (string) ($result['url'] ?? ''),
            ];
        } catch (\Throwable $e) {
            $this->templateInlineImageUpload = null;

            return ['success' => false, 'error' => 'Unable to upload image.'];
        }
    }

    protected function isAllowedTemplateToken(string $token): bool
    {
        return in_array($token, [
            '{{CoachFirstName}}', '{{CoachLastName}}', '{{CoachName}}', '{{SchoolName}}', '{{CoachTitle}}',
            '{{AthleteName}}', '{{GraduationYear}}', '{{Position}}', '{{ClubTeam}}', '{{GPA}}',
            '{{AthleteEmail}}', '{{AthletePhone}}', '{{HighlightLink}}', '{{ProfileLink}}',
            '{{InstagramLink}}', '{{TwitterLink}}', '{{YoutubeLink}}',
        ], true);
    }

    protected function appendTokenOnce(string $value, string $token): string
    {
        $value = rtrim($value);

        if (str_ends_with($value, $token)) {
            return $value;
        }

        return $value . ($value === '' ? '' : ' ') . $token;
    }

    public function getRenderedTemplateSubjectProperty(): string
    {
        return $this->replaceTemplateTokens($this->templateSubject);
    }

    public function getRenderedTemplatePreviewTextProperty(): string
    {
        return $this->replaceTemplateTokens($this->templatePreviewText);
    }

    public function getRenderedTemplateBodyProperty(): string
    {
        return $this->renderTemplateHtmlForPreview($this->buildTemplateHtml($this->templateBody));
    }

    protected function replaceTemplateTokens(string $value): string
    {
        $samples = [
            '{{CoachFirstName}}' => 'Stephens',
            '{{CoachLastName}}' => 'Salas',
            '{{CoachName}}' => 'Stephens Salas',
            '{{SchoolName}}' => 'Abilene Christian University',
            '{{CoachTitle}}' => 'Head Coach',
            '{{AthleteName}}' => (string) (Auth::user()?->name ?? 'Alex Johnson'),
            '{{GraduationYear}}' => '2027',
            '{{Position}}' => 'Center Back',
            '{{ClubTeam}}' => 'Baltimore Celtic ECNL',
            '{{GPA}}' => '4.0',
            '{{AthleteEmail}}' => $this->firstUserTokenText(['email', 'athlete_email', 'player_email'], 'athlete@example.com'),
            '{{AthletePhone}}' => $this->firstUserTokenText(['phone', 'phone_number', 'mobile', 'athlete_phone'], '(555) 123-4567'),
            '{{HighlightLink}}' => $this->firstUserTokenText(['highlight_link', 'highlights_link', 'highlightLink'], 'https://plyrcard.com/highlights'),
            '{{ProfileLink}}' => $this->firstUserTokenText(['profile_link', 'plyrcard_link', 'profileLink'], 'https://plyrcard.com/profile'),
            '{{InstagramLink}}' => $this->firstUserTokenText(['instagram_link', 'instagram_url', 'instagram', 'social_links.instagram', 'social.instagram'], 'https://instagram.com/yourhandle'),
            '{{TwitterLink}}' => $this->firstUserTokenText(['twitter_link', 'twitter_url', 'x_link', 'x_url', 'twitter', 'social_links.twitter', 'social_links.x', 'social.twitter', 'social.x'], 'https://x.com/yourhandle'),
            '{{YoutubeLink}}' => $this->firstUserTokenText(['youtube_link', 'youtube_url', 'youtube', 'social_links.youtube', 'social.youtube'], 'https://youtube.com/@yourhandle'),
        ];

        return strtr($value, $samples);
    }

    protected function templateErrorMessage(array $result, string $fallback): string
    {
        $error = $result['error'] ?? $result['message'] ?? $result['raw']['message'] ?? $result['raw']['error'] ?? null;

        if (is_string($error) && trim($error) !== '') {
            return trim($error);
        }

        if (is_array($error)) {
            $flattened = collect($error)
                ->flatten()
                ->filter(fn ($value): bool => is_scalar($value) && trim((string) $value) !== '')
                ->map(fn ($value): string => trim((string) $value))
                ->unique()
                ->values()
                ->all();

            if (! empty($flattened)) {
                return implode(' ', array_slice($flattened, 0, 3));
            }
        }

        $raw = $result['raw'] ?? null;
        if (is_array($raw)) {
            foreach (['message', 'error', 'errors', 'details'] as $key) {
                if (! array_key_exists($key, $raw)) {
                    continue;
                }

                $value = $raw[$key];
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }

                if (is_array($value)) {
                    $flattened = collect($value)
                        ->flatten()
                        ->filter(fn ($item): bool => is_scalar($item) && trim((string) $item) !== '')
                        ->map(fn ($item): string => trim((string) $item))
                        ->unique()
                        ->values()
                        ->all();

                    if (! empty($flattened)) {
                        return implode(' ', array_slice($flattened, 0, 3));
                    }
                }
            }
        }

        return $fallback;
    }

    protected function buildTemplateHtml(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        // Quill stores the message as HTML. Keep that HTML intact so images,
        // links, buttons, lists, colors, and headings remain compatible with GHL.
        if (preg_match('/<\s*(p|div|h1|h2|h3|ul|ol|li|blockquote|img|a|table|span|strong|em|br)\b/i', $text)) {
            return $this->sanitizeTemplateHtml($text);
        }

        return $this->templateTextToHtml($text);
    }

    protected function sanitizeTemplateHtml(string $html): string
    {
        $html = trim($html);
        $html = preg_replace('/<\s*(script|iframe|object|embed|form|input|button)[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $html) ?? $html;
        $html = preg_replace("/\s+on[a-z]+\s*=\s*(\"[^\"]*\"|'[^']*'|[^\s>]+)/i", '', $html) ?? $html;
        $html = preg_replace('/javascript\s*:/i', '', $html) ?? $html;

        return $html;
    }

    protected function renderTemplateHtmlForPreview(string $html): string
    {
        return $this->replaceTemplateTokens($html);
    }

    protected function resolveTemplateGraphicUpload(): void
    {
        if (! $this->templateGraphicUpload) {
            return;
        }

        $user = Auth::user();
        if (! $user) {
            $this->templateGraphicUpload = null;
            return;
        }

        try {
            $this->validate([
                'templateGraphicUpload' => ['image', 'max:25600'],
            ]);

            $result = app(CoachDatabaseService::class)->uploadMediaForUser($user, $this->templateGraphicUpload);
            $this->templateGraphicUpload = null;

            if (! ($result['success'] ?? false) || blank($result['url'] ?? null)) {
                Notification::make()
                    ->title('Templates')
                    ->body($this->templateErrorMessage($result, 'Unable to upload graphic to GHL media.'))
                    ->danger()
                    ->send();
                return;
            }

            $this->templateGraphicUrl = trim((string) $result['url']);
        } catch (\Throwable $e) {
            $this->templateGraphicUpload = null;
            Notification::make()->title('Templates')->body('Unable to upload graphic to GHL media.')->danger()->send();
        }
    }

    protected function templateTextToHtml(string $text): string
    {
        $paragraphs = preg_split('/\R{2,}/', trim($text)) ?: [];

        return collect($paragraphs)
            ->map(function (string $paragraph): string {
                $paragraph = trim($paragraph);
                if ($paragraph === '') {
                    return '';
                }

                return '<p>' . nl2br(e($paragraph), false) . '</p>';
            })
            ->filter()
            ->implode("\n");
    }

    protected function htmlToTemplateText(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $html = preg_replace('/<!--.*?-->/s', '', $html) ?? $html;
        $html = preg_replace('/<\s*(script|style|head|title|meta|link|svg|noscript)[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $html) ?? $html;
        $html = preg_replace('/<\s*img\b[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('/<\s*(br|hr)\s*\/?\s*>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\s*\/\s*(p|div|tr|table|section|article|h1|h2|h3|h4|h5|h6)\s*>/i', "\n\n", $html) ?? $html;
        $html = preg_replace('/<\s*\/\s*li\s*>/i', "\n", $html) ?? $html;

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        if ($loaded) {
            foreach (['script', 'style', 'head', 'title', 'meta', 'link', 'svg', 'noscript', 'img'] as $tag) {
                while (($nodes = $dom->getElementsByTagName($tag))->length > 0) {
                    $node = $nodes->item(0);
                    $node?->parentNode?->removeChild($node);
                }
            }

            $body = $dom->getElementsByTagName('body')->item(0);
            $text = $body ? $body->textContent : $dom->textContent;
        } else {
            $text = strip_tags($html);
        }

        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = preg_split('/\R+/', $text) ?: [];
        $lines = collect($lines)
            ->map(fn (string $line): string => trim(preg_replace('/[ \t]+/', ' ', $line) ?? $line))
            ->filter(function (string $line): bool {
                if ($line === '') {
                    return false;
                }

                if (preg_match('/^(view this email|unsubscribe|manage preferences|powered by)/i', $line)) {
                    return false;
                }

                if (preg_match('/^[a-f0-9]{16,}$/i', $line)) {
                    return false;
                }

                if (preg_match('/^(https?:\/\/|data:image\/)/i', $line)) {
                    return false;
                }

                return true;
            })
            ->values()
            ->all();

        return trim(implode("\n", $lines));
    }

    protected function extractFirstTemplateImageUrl(string $html): string
    {
        if (preg_match('/<img\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return '';
    }

    protected function removeFirstTemplateImage(string $html): string
    {
        return preg_replace('/<p[^>]*>\s*<img\b[^>]*>\s*<\/p>/i', '', $html, 1)
            ?? preg_replace('/<img\b[^>]*>/i', '', $html, 1)
            ?? $html;
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

    protected function isBuiltInTemplateId(?string $templateId): bool
    {
        return is_string($templateId) && str_starts_with($templateId, 'plyrcard-');
    }

    protected function templateHtmlForNativeEditor(array $template): string
    {
        $html = $this->templateHtml($template);

        if ($html === '') {
            $html = $this->coerceTemplateHtml($template);
        }

        return $this->normalizeHtmlForNativeEditor($html);
    }

    protected function normalizeHtmlForNativeEditor(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        // Some GHL/TyncMe responses return escaped HTML. Decode it before
        // placing it into the native contenteditable editor so users see the
        // rendered email, not raw <p> / <table> code.
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($decoded !== '') {
            $html = $decoded;
        }

        if (preg_match('/<\s*(p|div|h1|h2|h3|h4|h5|h6|ul|ol|li|blockquote|img|a|table|tr|td|span|strong|em|br|body|html)\b/i', $html)) {
            return $this->sanitizeTemplateHtml($html);
        }

        return $this->templateTextToHtml(trim(strip_tags($html)));
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

            if (strlen($value) > 80 && preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $value)) {
                $base64 = base64_decode($value, true);
                if (is_string($base64) && trim($base64) !== '' && $base64 !== $value) {
                    $nested = $this->coerceTemplateHtml($base64);
                    if ($nested !== '') {
                        return $nested;
                    }
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

        foreach (['props', 'data', 'attributes', 'values', 'properties', 'settings'] as $key) {
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
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $coachName = trim((string) ($coach['name'] ?? ''));
        $parts = preg_split('/\s+/', $coachName, 2) ?: [];
        $firstName = trim((string) ($coach['first_name'] ?? ($parts[0] ?? '')));
        $lastName = trim((string) ($coach['last_name'] ?? ($parts[1] ?? '')));

        if ($coachName === '') {
            $coachName = trim($firstName . ' ' . $lastName);
        }

        if ($firstName === '' && $coachName !== '') {
            $firstName = trim((string) ($parts[0] ?? $coachName));
        }

        $schoolName = trim((string) ($coach['school'] ?? ''));
        $title = trim((string) ($coach['title'] ?? 'Coach')) ?: 'Coach';
        $user = Auth::user();

        $values = [
            'CoachFirstName' => $firstName ?: 'Coach',
            'CoachLastName' => $lastName,
            'CoachName' => $coachName ?: trim(($firstName ?: 'Coach') . ' ' . $lastName),
            'SchoolName' => $schoolName,
            'CoachTitle' => $title,
            'CoachEmail' => $this->tokenText($coach['email'] ?? null),
            'CoachPhone' => $this->tokenText($coach['phone'] ?? null),
            'Sport' => $this->tokenText($coach['sport'] ?? null),
            'Conference' => $this->tokenText($coach['conference'] ?? null),
            'Division' => $this->tokenText($coach['division'] ?? null),
            'City' => $this->tokenText($coach['city'] ?? null),
            'State' => $this->tokenText($coach['state'] ?? null),
            'AthleteName' => $this->tokenText($user?->name ?? null, '[Your Name]'),
            'GraduationYear' => $this->userTokenText('graduation_year', '[Graduation Year]'),
            'Position' => $this->userTokenText('position', '[Position]'),
            'ClubTeam' => $this->userTokenText('club_team', '[Club Team]'),
            'GPA' => $this->userTokenText('gpa', '[GPA]'),
            'AthleteEmail' => $this->firstUserTokenText(['email', 'athlete_email', 'player_email'], '[Email]'),
            'AthletePhone' => $this->firstUserTokenText(['phone', 'phone_number', 'mobile', 'athlete_phone'], '[Phone]'),
            'HighlightLink' => $this->firstUserTokenText(['highlight_link', 'highlights_link', 'highlightLink'], '[Highlight Link]'),
            'ProfileLink' => $this->firstUserTokenText(['profile_link', 'plyrcard_link', 'profileLink'], '[Profile Link]'),
            'InstagramLink' => $this->firstUserTokenText(['instagram_link', 'instagram_url', 'instagram', 'social_links.instagram', 'social.instagram'], '#'),
            'TwitterLink' => $this->firstUserTokenText(['twitter_link', 'twitter_url', 'x_link', 'x_url', 'twitter', 'social_links.twitter', 'social_links.x', 'social.twitter', 'social.x'], '#'),
            'YoutubeLink' => $this->firstUserTokenText(['youtube_link', 'youtube_url', 'youtube', 'social_links.youtube', 'social.youtube'], '#'),
        ];

        $aliases = [
            'CoachFirstName' => ['coach_first_name', 'first_name', 'firstname', 'coach.first_name', 'contact.first_name', 'contact.firstName', 'custom_values.coach_first_name'],
            'CoachLastName' => ['coach_last_name', 'last_name', 'lastname', 'coach.last_name', 'contact.last_name', 'contact.lastName', 'custom_values.coach_last_name'],
            'CoachName' => ['coach_name', 'name', 'full_name', 'coach.name', 'contact.name', 'contact.full_name'],
            'SchoolName' => ['school_name', 'school', 'school.name', 'custom_values.school_name'],
            'CoachTitle' => ['coach_title', 'title', 'coach.title', 'contact.title'],
            'CoachEmail' => ['coach_email', 'email', 'coach.email', 'contact.email'],
            'CoachPhone' => ['coach_phone', 'phone', 'coach.phone', 'contact.phone'],
            'Sport' => ['sport'],
            'Conference' => ['conference'],
            'Division' => ['division'],
            'City' => ['city'],
            'State' => ['state'],
            'AthleteName' => ['athlete_name', 'player_name', 'user.name'],
            'GraduationYear' => ['graduation_year', 'grad_year', 'gradYear'],
            'Position' => ['position'],
            'ClubTeam' => ['club_team', 'team'],
            'GPA' => ['gpa'],
            'AthleteEmail' => ['athlete_email', 'player_email', 'email', 'user.email'],
            'AthletePhone' => ['athlete_phone', 'player_phone', 'phone', 'phone_number', 'mobile'],
            'HighlightLink' => ['highlight_link', 'highlights_link', 'highlightLink'],
            'ProfileLink' => ['profile_link', 'plyrcard_link', 'profileLink'],
            'InstagramLink' => ['instagram_link', 'instagram_url', 'instagram', 'social_links.instagram', 'social.instagram'],
            'TwitterLink' => ['twitter_link', 'twitter_url', 'x_link', 'x_url', 'twitter', 'social_links.twitter', 'social_links.x', 'social.twitter', 'social.x'],
            'YoutubeLink' => ['youtube_link', 'youtube_url', 'youtube', 'social_links.youtube', 'social.youtube'],
        ];

        foreach ($values as $canonical => $value) {
            $names = collect([$canonical, lcfirst($canonical), Str::snake($canonical)])
                ->merge($aliases[$canonical] ?? [])
                ->filter()
                ->unique()
                ->values();

            foreach ($names as $name) {
                $quoted = preg_quote((string) $name, '/');
                $content = preg_replace('/\{\{\s*' . $quoted . '\s*\}\}/i', (string) $value, $content) ?? $content;
                $content = preg_replace('/\[\s*' . $quoted . '\s*\]/i', (string) $value, $content) ?? $content;
                $content = preg_replace('/%' . $quoted . '%/i', (string) $value, $content) ?? $content;
            }
        }

        return $content;
    }

    protected function userTokenText(string $key, string $fallback = ''): string
    {
        $user = Auth::user();

        if (! $user) {
            return $fallback;
        }

        return $this->tokenText(data_get($user, $key), $fallback);
    }

    protected function firstUserTokenText(array $keys, string $fallback = ''): string
    {
        $user = Auth::user();

        if (! $user) {
            return $fallback;
        }

        foreach ($keys as $key) {
            $value = $this->tokenText(data_get($user, $key), '');

            if ($value !== '') {
                return $value;
            }
        }

        return $fallback;
    }

    protected function tokenText(mixed $value, string $fallback = ''): string
    {
        if (is_null($value) || $value === '') {
            return $fallback;
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if ($value instanceof \Stringable) {
            return trim((string) $value);
        }

        if (is_array($value)) {
            $preferred = [
                'label',
                'name',
                'title',
                'value',
                'text',
                'position',
                'primary',
            ];

            foreach ($preferred as $key) {
                if (array_key_exists($key, $value) && is_scalar($value[$key])) {
                    return trim((string) $value[$key]);
                }
            }

            $flat = collect($value)
                ->flatten()
                ->filter(fn ($item): bool => is_scalar($item) && trim((string) $item) !== '')
                ->map(fn ($item): string => trim((string) $item))
                ->unique()
                ->values();

            return $flat->isNotEmpty() ? $flat->implode(', ') : $fallback;
        }

        return $fallback;
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


    public function getDashboardMetricsProperty(): array
    {
        $stats = $this->stats ?? [];
        $schools = collect($this->allSchools());

        $savedSchools = (int) (($stats['saved_schools'] ?? $schools->filter(fn (array $school): bool => (bool) ($school['is_saved'] ?? false))->count()) ?: 0);
        $favoriteSchools = (int) (($stats['favorite_schools'] ?? $schools->filter(fn (array $school): bool => (bool) ($school['is_favorite'] ?? false))->count()) ?: 0);

        $trackedWebsiteViews = (int) ($stats['view_profile_website'] ?? $stats['website_clicks'] ?? 0);
        $trackedInstagramViews = (int) ($stats['view_profile_instagram'] ?? $stats['instagram_clicks'] ?? 0);
        $trackedYoutubeViews = (int) ($stats['view_profile_youtube'] ?? $stats['youtube_clicks'] ?? 0);
        $trackedXViews = (int) ($stats['view_profile_x'] ?? $stats['x_clicks'] ?? $stats['twitter_clicks'] ?? 0);
        $trackedEmailProfileLinks = (int) ($stats['view_profile_email_link'] ?? 0);

        $trackedProfileTotal = (int) ($stats['view_profile_total'] ?? 0);
        if ($trackedProfileTotal === 0) {
            $trackedProfileTotal = $trackedWebsiteViews + $trackedInstagramViews + $trackedYoutubeViews + $trackedXViews + $trackedEmailProfileLinks;
        }

        $emailSentCount = (int) ($stats['email_sent_count'] ?? $stats['emails_sent'] ?? 0);
        if ($emailSentCount === 0) {
            $emailSentCount = (int) (($stats['campaigns_sent'] ?? 0) ?: 0) + (int) (($stats['personal_emails_sent'] ?? 0) ?: 0);
        }

        $emailOpenCount = (int) ($stats['email_open_count'] ?? $stats['email_opens'] ?? 0);
        $emailClickCount = (int) ($stats['email_click_count'] ?? $stats['email_clicks'] ?? $stats['link_clicks'] ?? $stats['trigger_link_clicks'] ?? $stats['trigger_clicks'] ?? 0);
        $linkClicks = $trackedWebsiteViews + $trackedInstagramViews + $trackedYoutubeViews + $trackedXViews + $trackedEmailProfileLinks + $emailClickCount;

        $engagedSchools = (int) (($stats['engaged_schools'] ?? $schools->filter(function (array $school): bool {
            return ((int) ($school['replies'] ?? $school['coach_replies'] ?? 0) > 0)
                || ((int) ($school['link_clicks'] ?? $school['trigger_link_clicks'] ?? $school['trigger_clicks'] ?? 0) > 0)
                || ((int) ($school['profile_views'] ?? 0) + (int) ($school['highlight_views'] ?? 0) > 0)
                || ((int) ($school['engagement_score'] ?? 0) > 0);
        })->count()) ?: 0);

        return [
            'saved_schools' => $savedSchools,
            'favorite_schools' => $favoriteSchools,
            'engaged_schools' => $engagedSchools,
            'emails_sent' => $emailSentCount,
            'email_sent_count' => $emailSentCount,
            'email_open_count' => $emailOpenCount,
            'email_click_count' => $emailClickCount,
            'profile_views' => $trackedProfileTotal,
            'view_profile_total' => $trackedProfileTotal,
            'view_profile_website' => $trackedWebsiteViews,
            'view_profile_instagram' => $trackedInstagramViews,
            'view_profile_youtube' => $trackedYoutubeViews,
            'view_profile_x' => $trackedXViews,
            'view_profile_email_link' => $trackedEmailProfileLinks,
            'link_clicks' => $linkClicks,
            'trigger_link_clicks' => $linkClicks,
            'email_open_rate' => (int) (($stats['email_open_rate'] ?? 0) ?: 0),
            'coach_replies' => (int) (($stats['coach_replies'] ?? $stats['replies'] ?? 0) ?: 0),
            'sparks' => $this->dashboardActivitySummary['sparks'] ?? $this->fallbackDashboardSparks($stats),
        ];
    }


    protected function fallbackDashboardSparks(array $stats = []): array
    {
        $make = function (int $total): array {
            $total = max(0, $total);
            if ($total === 0) {
                return [0, 1, 0, 2, 1, 3, 1];
            }
            $base = max(1, (int) floor($total / 7));
            return [max(0, $base - 1), $base + 1, $base, $base + 2, max(0, $base - 1), $base + 1, max(1, $total - ($base * 5))];
        };

        $schools = collect($this->allSchools())->values();
        $seriesFromSchools = function (array $keys, int $fallbackTotal) use ($schools, $make): array {
            $values = $schools->map(function (array $school) use ($keys): int {
                $sum = 0;
                foreach ($keys as $key) {
                    $sum += (int) ($school[$key] ?? 0);
                }
                return $sum;
            })->filter(fn (int $value): bool => $value > 0)->take(7)->values()->all();

            if (array_sum($values) <= 0) {
                return $make($fallbackTotal);
            }

            return array_pad(array_slice($values, 0, 7), 7, 0);
        };

        return [
            'profile_views' => $seriesFromSchools(['profile_views', 'highlight_views'], (int) ($stats['profile_views'] ?? 0)),
            'link_clicks' => $seriesFromSchools(['link_clicks', 'trigger_link_clicks', 'trigger_clicks'], (int) ($stats['link_clicks'] ?? $stats['trigger_link_clicks'] ?? 0)),
            'email_open_rate' => $make((int) ($stats['email_open_rate'] ?? 0)),
            'coach_replies' => $seriesFromSchools(['coach_replies', 'replies'], (int) ($stats['coach_replies'] ?? $stats['replies'] ?? 0)),
        ];
    }

    public function getDashboardRecommendationsProperty(): array
    {
        $user = Auth::user();
        $steps = [];
        $metrics = $this->dashboardMetrics;

        $profileMissing = collect([
            $user?->name ?? null,
            $this->firstUserTokenText(['graduation_year', 'grad_year', 'profile.graduation_year'], ''),
            $this->firstUserTokenText(['position', 'primary_position', 'profile.position'], ''),
        ])->filter(fn ($value) => filled($value))->count() < 3;

        if ($profileMissing) {
            $steps[] = [
                'title' => 'Complete your athlete profile',
                'copy' => 'Add name, grad year, position, links, and socials so email templates personalize correctly.',
                'url' => method_exists(\App\Filament\Pages\AthleteProfile::class, 'getUrl') ? \App\Filament\Pages\AthleteProfile::getUrl() : '#',
                'label' => 'Set up profile',
            ];
        }

        if ((int) ($metrics['saved_schools'] ?? 0) === 0) {
            $steps[] = [
                'title' => 'Build your first school list',
                'copy' => 'Search by division, conference, state, or coach and add schools to a focused recruiting list.',
                'url' => \App\Filament\Pages\CoachDatabaseSchools::getUrl(),
                'label' => 'Find schools',
            ];
        }

        if ((int) ($metrics['emails_sent'] ?? 0) === 0) {
            $steps[] = [
                'title' => 'Send your first recruiting email',
                'copy' => 'Use a starter template, add your profile and highlight links, then send to selected coaches.',
                'url' => \App\Filament\Pages\CoachDatabaseComposeEmail::getUrl(),
                'label' => 'Compose email',
            ];
        }

        if ((int) ($metrics['trigger_link_clicks'] ?? 0) > 0 && (int) ($metrics['coach_replies'] ?? 0) === 0) {
            $steps[] = [
                'title' => 'Follow up with clicked coaches',
                'copy' => 'Coaches have interacted with your links. Send a shorter follow-up while interest is fresh.',
                'url' => \App\Filament\Pages\CoachDatabaseComposeEmail::getUrl(),
                'label' => 'Follow up',
            ];
        }

        if (empty($steps)) {
            $steps[] = [
                'title' => 'Review engaged schools',
                'copy' => 'Prioritize schools with replies and link clicks, then move them into your target lists.',
                'url' => \App\Filament\Pages\CoachDatabaseLists::getUrl(),
                'label' => 'Manage lists',
            ];
        }

        return array_slice($steps, 0, 3);
    }

    public function getDashboardTopEngagedSchoolsProperty(): array
    {
        $schools = collect($this->topSchools ?: $this->allSchools())
            ->map(function (array $school): array {
                $replies = (int) ($school['replies'] ?? $school['coach_replies'] ?? 0);
                $clicks = (int) ($school['link_clicks'] ?? $school['trigger_link_clicks'] ?? $school['trigger_clicks'] ?? 0);
                $views = (int) ($school['profile_views'] ?? 0) + (int) ($school['highlight_views'] ?? 0);
                $score = ($replies * 20) + ($clicks * 6) + ($views * 2) + (int) ($school['engagement_score'] ?? 0);
                $school['lead_score'] = $score;
                $school['has_replied'] = $replies > 0;
                if (empty($school['id'])) {
                    $school['id'] = (string) ($school['business_id'] ?? '');
                }
                if (empty($school['id']) && ! empty($school['name'])) {
                    $school['id'] = md5(strtolower(trim((string) $school['name'])));
                }
                return $school;
            })
            ->filter(fn (array $school): bool => (int) ($school['lead_score'] ?? 0) > 0)
            ->sortByDesc(fn (array $school): int => (int) ($school['lead_score'] ?? 0))
            ->take(5)
            ->values()
            ->all();

        return $schools ?: collect($this->allSchools())->take(5)->values()->all();
    }

    public function getDashboardRecentActivityProperty(): array
    {
        $format = function (array $item): array {
            $copy = trim(strip_tags((string) ($item['copy'] ?? '')));
            $copy = preg_replace('/\s+/', ' ', $copy) ?: 'Recent recruiting activity';
            return [
                'type' => $item['type'] ?? 'activity',
                'title' => $item['title'] ?? 'Activity',
                'copy' => Str::limit($copy, 220),
                'time' => $item['time'] ?? null,
                'url' => $item['url'] ?? \App\Filament\Pages\CoachDatabaseConversations::getUrl(),
            ];
        };

        if (! empty($this->dashboardRecentActivity)) {
            return collect($this->dashboardRecentActivity)->take(8)->map($format)->values()->all();
        }

        return collect($this->conversations ?? [])
            ->take(8)
            ->map(fn (array $conversation): array => $format([
                'type' => 'conversation',
                'title' => $conversation['contact_name'] ?? $conversation['name'] ?? 'Coach conversation',
                'copy' => $conversation['last_message'] ?? $conversation['snippet'] ?? 'New recruiting email activity',
                'time' => $conversation['last_message_at'] ?? $conversation['updated_at'] ?? null,
                'url' => \App\Filament\Pages\CoachDatabaseConversations::getUrl(),
            ]))
            ->values()
            ->all();
    }

    public function getDashboardSchoolsProperty(): array { return collect($this->allSchools())->take(8)->values()->all(); }
    public function getFilteredSchoolsProperty(): array { return $this->filteredSchoolsQuery()->take($this->schoolDisplayLimit)->values()->all(); }
    public function getFilteredSchoolsCountProperty(): int { return $this->filteredSchoolsQuery()->count(); }
    public function getCanLoadMoreSchoolsProperty(): bool { return $this->filteredSchoolsCount > count($this->filteredSchools); }
    public function getFilteredCoachesProperty(): array { return $this->filteredCoachesQuery()->take($this->coachDisplayLimit)->values()->all(); }
    public function getFilteredCoachesCountProperty(): int { return $this->filteredCoachesQuery()->count(); }
    public function getCanLoadMoreCoachesProperty(): bool { return $this->filteredCoachesCount > count($this->filteredCoaches); }
    public function getFavoriteSchoolsProperty(): array { return $this->filterSchoolsForSearch(collect($this->allSchools())->filter(fn (array $school): bool => (bool) ($school['is_favorite'] ?? false)), $this->favoriteSchoolSearch)->values()->all(); }
    public function getFavoriteCoachesProperty(): array { return collect($this->allCoaches())->filter(fn (array $coach): bool => (bool) ($coach['is_favorite_coach'] ?? false))->take(80)->values()->all(); }


    public function getSavedSchoolsProperty(): array
    {
        return $this->filterSchoolsForSearch(collect($this->allSchools())->filter(fn (array $school): bool => (bool) ($school['is_saved'] ?? false)), $this->favoriteSchoolSearch)->values()->all();
    }

    public function getSavedCoachesProperty(): array
    {
        return collect($this->allCoaches())->filter(fn (array $coach): bool => (bool) ($coach['is_saved_coach'] ?? false))->take(120)->values()->all();
    }

    protected function filterSchoolsForSearch(Collection $schools, string $query): Collection
    {
        $query = strtolower(trim($query));

        if ($query === '') {
            return $schools;
        }

        return $schools->filter(function (array $school) use ($query): bool {
            $haystack = strtolower(implode(' ', [
                $school['name'] ?? '',
                $school['conference'] ?? '',
                $school['division'] ?? '',
                $school['head_coach']['name'] ?? '',
            ]));

            return str_contains($haystack, $query);
        });
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

        return $this->filterSchoolsForSearch(collect($this->allSchools())
            ->filter(fn (array $school): bool => in_array((string) ($list['key'] ?? ''), $school['list_keys'] ?? [], true)), $this->listSchoolSearch)
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
        // The editor should always be the rendered email template itself when one is selected.
        // Never fall back to a plain "Start typing" box while a template body exists elsewhere.
        $html = trim($this->campaignBody);

        if ($html === '') {
            $html = trim($this->campaignOriginalHtml);
        }

        if ($html === '') {
            $html = trim($this->previewTemplateHtml);
        }

        if ($html === '') {
            $html = '<!doctype html><html><head><meta charset="utf-8"><style>body{margin:0;background:#fff;color:#111827;font-family:Arial,sans-serif}.empty{padding:32px;text-align:center;color:#6b7280}</style></head><body><div class="empty">Choose an email to edit.</div></body></html>';
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

    protected function buildComposeHtml(string $text): string
    {
        // Preserve real HTML from the hardcoded templates/editor. The old path
        // always treated compose content as plain text, which could escape HTML
        // and make template values look broken in previews/sends.
        $html = $this->buildTemplateHtml($text);
        $graphic = trim($this->composeGraphicUrl);

        if ($graphic !== '') {
            $image = '<p style="margin:0 0 18px;text-align:center"><img src="' . e($graphic) . '" alt="Email graphic" style="max-width:100%;height:auto;border-radius:14px;display:inline-block"></p>';
            return $image . $html;
        }

        return $html;
    }

    protected function resolveComposeGraphicUpload(): void
    {
        if (! $this->composeGraphicUpload) {
            return;
        }

        $user = Auth::user();
        if (! $user) {
            $this->composeGraphicUpload = null;
            return;
        }

        try {
            $this->validate([
                'composeGraphicUpload' => ['image', 'max:25600'],
            ]);

            $result = app(CoachDatabaseService::class)->uploadMediaForUser($user, $this->composeGraphicUpload);
            $this->composeGraphicUpload = null;

            if (! ($result['success'] ?? false) || blank($result['url'] ?? null)) {
                Notification::make()
                    ->title('Compose Email')
                    ->body($this->templateErrorMessage($result, 'Unable to upload image to GHL media.'))
                    ->danger()
                    ->send();
                return;
            }

            $this->composeGraphicUrl = trim((string) $result['url']);
        } catch (\Throwable $exception) {
            $this->composeGraphicUpload = null;
            Notification::make()->title('Compose Email')->body('Unable to upload image to GHL media.')->danger()->send();
        }
    }

    public function composeEmailSchool(string $schoolId): void
    {
        $schoolId = trim($schoolId);
        if ($schoolId === '') {
            return;
        }

        $this->redirect($this->pageUrl('compose') . '?school=' . urlencode($schoolId), navigate: true);
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


    public function getComposePreviewCoachProperty(): array
    {
        $coach = $this->campaignRecipientCoaches()->first()
            ?: collect($this->allCoaches())->first(fn (array $coach): bool => filled($coach['email'] ?? null));

        return is_array($coach) ? $coach : [
            'name' => 'Stephens Salas',
            'first_name' => 'Stephens',
            'last_name' => 'Salas',
            'school' => 'Abilene Christian University',
            'title' => 'Head Coach',
            'email' => 'stephens.salas@example.com',
        ];
    }

    public function getComposeRenderedSubjectProperty(): string
    {
        return $this->replaceCampaignTokens($this->campaignSubject ?: 'Subject preview', $this->composePreviewCoach);
    }

    public function getComposeRenderedBodyProperty(): string
    {
        $body = trim($this->campaignBody) !== '' ? $this->campaignBody : 'Choose a template or write your message.';
        return $this->replaceCampaignTokens($this->buildComposeHtml($body), $this->composePreviewCoach);
    }

    public function getComposeSelectedListProperty(): ?array
    {
        if ($this->campaignListKey === '') {
            return null;
        }

        return collect($this->lists)->firstWhere('key', $this->campaignListKey);
    }

    public function getComposeSchoolOptionsProperty(): array
    {
        return collect($this->allSchools())
            ->filter(fn (array $school): bool => filled($school['id'] ?? null) && filled($school['name'] ?? null))
            ->sortBy('name')
            ->values()
            ->all();
    }

    public function getComposeSchoolResultsProperty(): array
    {
        $query = strtolower(trim($this->composeSchoolSearch));

        return collect($this->composeSchoolOptions)
            ->filter(function (array $school) use ($query): bool {
                if ($query === '') {
                    return true;
                }

                $haystack = strtolower(implode(' ', [
                    $school['name'] ?? '',
                    $school['conference'] ?? '',
                    $school['division'] ?? '',
                    $school['state'] ?? '',
                    $school['city'] ?? '',
                ]));

                return str_contains($haystack, $query);
            })
            ->take($query === '' ? 12 : 24)
            ->values()
            ->all();
    }

    public function selectComposeSchool(string $schoolId): void
    {
        $schoolId = trim($schoolId);

        if ($schoolId === '') {
            $this->campaignSchoolId = '';
            return;
        }

        $this->campaignTargetMode = 'school';
        $this->campaignSchoolId = $schoolId;

        $school = collect($this->allSchools())->first(function (array $school) use ($schoolId): bool {
            return (string) ($school['id'] ?? '') === $schoolId;
        });

        $this->composeSchoolSearch = is_array($school) ? (string) ($school['name'] ?? '') : $this->composeSchoolSearch;
    }

    /** Backward-compatible alias for older school picker markup. */
    public function selectCampaignSchool(string $schoolId): void
    {
        $this->selectComposeSchool($schoolId);
    }

    public function getConversationSchoolOptionsProperty(): array
    {
        $schoolNames = collect($this->allCoaches())
            ->pluck('school')
            ->filter()
            ->map(fn ($school): string => trim((string) $school))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return $schoolNames->take(250)->all();
    }

    public function getFilteredConversationsProperty(): array
    {
        $schoolFilter = trim($this->conversationSchoolFilter);

        if ($schoolFilter === '') {
            return $this->conversations;
        }

        $coachesByEmail = collect($this->allCoaches())
            ->filter(fn (array $coach): bool => filled($coach['email'] ?? null))
            ->keyBy(fn (array $coach): string => strtolower(trim((string) ($coach['email'] ?? ''))));

        $coachesById = collect($this->allCoaches())
            ->filter(fn (array $coach): bool => filled($coach['id'] ?? null))
            ->keyBy(fn (array $coach): string => (string) ($coach['id'] ?? ''));

        return collect($this->conversations)
            ->filter(function (array $conversation) use ($schoolFilter, $coachesByEmail, $coachesById): bool {
                $email = strtolower(trim((string) ($conversation['email'] ?? $conversation['contact_email'] ?? '')));
                $contactId = (string) ($conversation['contact_id'] ?? $conversation['contactId'] ?? '');

                $coach = $contactId !== '' ? $coachesById->get($contactId) : null;
                if (! $coach && $email !== '') {
                    $coach = $coachesByEmail->get($email);
                }

                $conversationSchool = trim((string) ($conversation['school'] ?? $conversation['company_name'] ?? ''));
                $coachSchool = is_array($coach) ? trim((string) ($coach['school'] ?? '')) : '';

                return strcasecmp($conversationSchool, $schoolFilter) === 0
                    || strcasecmp($coachSchool, $schoolFilter) === 0;
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
        if (! $this->selectedSchoolId) {
            return null;
        }

        $selectedId = (string) $this->selectedSchoolId;
        $school = collect($this->allSchools())->firstWhere('id', $selectedId);

        if (! $school) {
            $dashboardTopSchools = $this->dashboardTopEngagedSchools ?? [];
            if ($dashboardTopSchools instanceof \Illuminate\Support\Collection) {
                $dashboardTopSchools = $dashboardTopSchools->all();
            }

            $school = collect(is_array($dashboardTopSchools) ? $dashboardTopSchools : [])
                ->first(function ($item) use ($selectedId): bool {
                    if (! is_array($item)) {
                        return false;
                    }

                    return (string) ($item['id'] ?? '') === $selectedId
                        || (string) ($item['business_id'] ?? '') === $selectedId
                        || md5(strtolower(trim((string) ($item['name'] ?? '')))) === $selectedId
                        || strcasecmp(trim((string) ($item['name'] ?? '')), $selectedId) === 0;
                });
        }

        if (! $school || ! is_array($school)) {
            return null;
        }

        $businessId = (string) ($school['business_id'] ?? $school['id'] ?? '');
        $schoolName = trim((string) ($school['name'] ?? ''));
        $school['coaches'] = collect($this->allCoaches())
            ->filter(fn (array $coach): bool => (string) ($coach['business_id'] ?? '') === $businessId || trim((string) ($coach['school'] ?? '')) === $schoolName)
            ->values()
            ->all();

        return $school;
    }

    public function getDivisionsProperty(): array { return collect($this->allSchools())->pluck('division')->filter()->unique()->sort()->values()->all(); }
    public function getConferencesProperty(): array { return collect($this->allSchools())->pluck('conference')->filter()->unique()->sort()->values()->all(); }

    public function clearSchoolFilters(): void
    {
        $this->search = '';
        $this->divisionFilter = '';
        $this->conferenceFilter = '';
        $this->sort = 'name';
    }

    public function setDivisionFilter(string $division): void
    {
        $this->divisionFilter = $this->divisionFilter === $division ? '' : $division;
    }

    public function setSchoolViewMode(string $mode): void
    {
        $this->schoolViewMode = in_array($mode, ['grid', 'list'], true) ? $mode : 'grid';
    }

    protected function normalizeSearchText(mixed $value): string
    {
        if (is_null($value)) {
            return '';
        }

        if (is_scalar($value)) {
            $text = (string) $value;
        } elseif (is_array($value)) {
            $text = collect($value)->map(fn ($item): string => $this->normalizeSearchText($item))->implode(' ');
        } elseif (is_object($value)) {
            $text = method_exists($value, '__toString') ? (string) $value : json_encode($value);
        } else {
            $text = '';
        }

        $text = strtolower(trim((string) $text));

        return str_replace([
            'ncaa division i', 'ncaa division ii', 'ncaa division iii',
            'ncaa d-i', 'ncaa d-ii', 'ncaa d-iii',
            'ncaa d1', 'ncaa d2', 'ncaa d3',
            'division i', 'division ii', 'division iii',
            'd-i', 'd-ii', 'd-iii',
            'd 1', 'd 2', 'd 3',
        ], [
            'd1', 'd2', 'd3',
            'd1', 'd2', 'd3',
            'd1', 'd2', 'd3',
            'd1', 'd2', 'd3',
            'd1', 'd2', 'd3',
            'd1', 'd2', 'd3',
        ], $text);
    }

    protected function normalizeDivisionValue(mixed $value): string
    {
        $text = $this->normalizeSearchText($value);
        $compact = preg_replace('/[^a-z0-9]+/', '', $text) ?: '';

        return match (true) {
            $compact === 'd1' || str_contains($compact, 'division1') || str_contains($compact, 'divisioni') => 'd1',
            $compact === 'd2' || str_contains($compact, 'division2') || str_contains($compact, 'divisionii') => 'd2',
            $compact === 'd3' || str_contains($compact, 'division3') || str_contains($compact, 'divisioniii') => 'd3',
            str_contains($compact, 'naia') => 'naia',
            str_contains($compact, 'njcaa') => 'njcaa',
            default => $compact,
        };
    }

    protected function divisionMatches(mixed $actual, mixed $selected): bool
    {
        $selected = $this->normalizeDivisionValue($selected);

        if ($selected === '') {
            return true;
        }

        return $this->normalizeDivisionValue($actual) === $selected;
    }

    protected function conferenceSearchTokens(mixed $conference): array
    {
        $raw = trim($this->tokenText($conference));

        if ($raw === '') {
            return [];
        }

        $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $raw) ?: '');
        $words = collect(preg_split('/\s+/', trim($normalized)) ?: [])
            ->filter(fn (string $word): bool => $word !== '' && ! in_array($word, ['the', 'and', 'of', 'for', 'conference', 'athletic', 'athletics', 'association', 'league'], true))
            ->values();

        $abbr = $words
            ->map(fn (string $word): string => substr($word, 0, 1))
            ->implode('');

        $aliases = [
            'atlantic coast conference' => ['acc'],
            'southeastern conference' => ['sec'],
            'big ten conference' => ['big ten', 'b1g'],
            'big 10 conference' => ['big ten', 'b1g'],
            'big 12 conference' => ['big twelve'],
            'big twelve conference' => ['big 12'],
            'pac 12 conference' => ['pac12', 'pac 12'],
            'pac twelve conference' => ['pac12', 'pac 12'],
            'colonial athletic association' => ['caa'],
            'coastal athletic association' => ['caa'],
            'big east conference' => ['big east'],
            'american athletic conference' => ['aac', 'the american'],
            'atlantic 10 conference' => ['a10', 'a 10', 'atlantic ten'],
            'atlantic ten conference' => ['a10', 'a 10', 'atlantic 10'],
            'sun belt conference' => ['sun belt', 'sbc'],
            'southern conference' => ['socon'],
            'western athletic conference' => ['wac'],
            'missouri valley conference' => ['mvc'],
            'mountain west conference' => ['mwc'],
            'west coast conference' => ['wcc'],
            'big west conference' => ['big west'],
            'horizon league' => ['horizon'],
            'ivy league' => ['ivy'],
            'patriot league' => ['patriot'],
            'summit league' => ['summit'],
            'southland conference' => ['southland'],
            'ohio valley conference' => ['ovc'],
            'metro atlantic athletic conference' => ['maac'],
            'mid american conference' => ['mac'],
            'mid-american conference' => ['mac'],
            'america east conference' => ['america east', 'aec'],
            'atlantic sun conference' => ['asun', 'a sun'],
            'asun conference' => ['asun', 'a sun'],
        ];

        $tokens = [$raw, $normalized, $abbr];

        foreach ($aliases as $name => $nameAliases) {
            if ($normalized === $name || str_contains($normalized, $name) || str_contains($name, $normalized)) {
                $tokens = array_merge($tokens, [$name], $nameAliases);
            }
        }

        return collect($tokens)
            ->map(fn ($token): string => trim((string) $token))
            ->filter()
            ->unique(fn (string $token): string => strtolower($token))
            ->values()
            ->all();
    }

    protected function conferenceMatches(mixed $conference, string $needle): bool
    {
        $needle = $this->normalizeSearchText($needle);

        if ($needle === '') {
            return true;
        }

        return str_contains($this->normalizeSearchText($this->conferenceSearchTokens($conference)), $needle);
    }

    protected function schoolCoachSearchIndex(): array
    {
        if (is_array($this->schoolCoachIndexMemo)) {
            return $this->schoolCoachIndexMemo;
        }

        $index = [];

        foreach ($this->allCoaches() as $coach) {
            if (! is_array($coach)) {
                continue;
            }

            $keys = [];
            $businessId = trim((string) ($coach['business_id'] ?? ''));
            $schoolName = strtolower(trim((string) ($coach['school'] ?? '')));

            if ($businessId !== '') {
                $keys[] = 'business:' . $businessId;
            }

            if ($schoolName !== '') {
                $keys[] = 'school:' . $schoolName;
            }

            foreach (array_unique($keys) as $key) {
                $index[$key] ??= [];
                $coachId = (string) ($coach['id'] ?? md5(json_encode($coach)));
                $index[$key][$coachId] = $coach;
            }
        }

        return $this->schoolCoachIndexMemo = $index;
    }

    protected function coachesForSchoolSearch(array $school): array
    {
        $keys = [];
        $businessId = trim((string) ($school['business_id'] ?? $school['id'] ?? ''));
        $schoolName = strtolower(trim((string) ($school['name'] ?? '')));

        if ($businessId !== '') {
            $keys[] = 'business:' . $businessId;
        }

        if ($schoolName !== '') {
            $keys[] = 'school:' . $schoolName;
        }

        $index = $this->schoolCoachSearchIndex();
        $coaches = [];

        foreach (array_unique($keys) as $key) {
            foreach (($index[$key] ?? []) as $coachId => $coach) {
                $coaches[$coachId] = $coach;
            }
        }

        return array_values($coaches);
    }

    protected function coachSearchHaystack(array $coach): string
    {
        return $this->normalizeSearchText([
            $coach['name'] ?? '',
            $coach['first_name'] ?? '',
            $coach['last_name'] ?? '',
            $coach['email'] ?? '',
            $coach['phone'] ?? '',
            $coach['title'] ?? '',
            $coach['position'] ?? '',
            $coach['school'] ?? '',
            $coach['conference'] ?? '',
            $this->conferenceSearchTokens($coach['conference'] ?? ''),
            $coach['division'] ?? '',
            $coach['city'] ?? '',
            $coach['state'] ?? '',
            $coach['tags'] ?? [],
        ]);
    }

    protected function schoolSearchHaystack(array $school): string
    {
        $coaches = collect($this->coachesForSchoolSearch($school))
            ->flatMap(function (array $coach): array {
                return [
                    $coach['name'] ?? '',
                    $coach['first_name'] ?? '',
                    $coach['last_name'] ?? '',
                    $coach['email'] ?? '',
                    $coach['title'] ?? '',
                    $coach['position'] ?? '',
                    $coach['school'] ?? '',
                    $coach['division'] ?? '',
                    $coach['conference'] ?? '',
                    $this->conferenceSearchTokens($coach['conference'] ?? ''),
                    $coach['city'] ?? '',
                    $coach['state'] ?? '',
                ];
            })
            ->all();

        return $this->normalizeSearchText(array_merge([
            $school['name'] ?? '',
            $school['conference'] ?? '',
            $this->conferenceSearchTokens($school['conference'] ?? ''),
            $school['division'] ?? '',
            $school['city'] ?? '',
            $school['state'] ?? '',
            $school['head_coach']['name'] ?? '',
            $school['head_coach']['title'] ?? '',
        ], $coaches));
    }

    protected function filteredSchoolsQuery(): Collection
    {
        $query = $this->normalizeSearchText($this->search);
        $divisionFilter = $this->divisionFilter;

        return collect($this->allSchools())->filter(function (array $school) use ($query, $divisionFilter): bool {
            if ($query !== '' && ! str_contains($this->schoolSearchHaystack($school), $query)) {
                return false;
            }

            if ($divisionFilter !== '' && ! $this->divisionMatches($school['division'] ?? '', $divisionFilter)) {
                return false;
            }

            if ($this->conferenceFilter !== '' && ! $this->conferenceMatches($school['conference'] ?? '', $this->conferenceFilter)) {
                return false;
            }

            return true;
        })->sortBy($this->sort === 'coach_count' ? 'coach_count' : 'name');
    }

    protected function filteredCoachesQuery(): Collection
    {
        $query = $this->normalizeSearchText($this->coachSearch !== '' ? $this->coachSearch : $this->search);
        $divisionFilter = $this->divisionFilter;

        return collect($this->allCoaches())->filter(function (array $coach) use ($query, $divisionFilter): bool {
            if ($query !== '') {
                $haystack = $this->coachSearchHaystack($coach);

                if (! str_contains($haystack, $query)) {
                    return false;
                }
            }

            if ($divisionFilter !== '' && ! $this->divisionMatches($coach['division'] ?? '', $divisionFilter)) {
                return false;
            }

            if ($this->conferenceFilter !== '' && ! $this->conferenceMatches($coach['conference'] ?? '', $this->conferenceFilter)) {
                return false;
            }

            return true;
        })->sortBy(fn (array $coach): string => strtolower(($coach['school'] ?? '') . ' ' . ($coach['name'] ?? '')));
    }

    protected function activeSnapshotRows(): array
    {
        if (is_array($this->coachDatabaseSnapshotMemo)) {
            return $this->coachDatabaseSnapshotMemo;
        }

        $snapshot = Cache::get($this->activeCacheKey(), []);
        $this->coachDatabaseSnapshotMemo = is_array($snapshot) ? $snapshot : [];

        return $this->coachDatabaseSnapshotMemo;
    }

    protected function resetSearchMemos(): void
    {
        $this->coachDatabaseSnapshotMemo = null;
        $this->coachSearchIndexMemo = null;
        $this->schoolCoachIndexMemo = null;
    }

    protected function allSchools(): array
    {
        $snapshot = $this->activeSnapshotRows();
        return is_array($snapshot['schools'] ?? null) ? $snapshot['schools'] : [];
    }

    protected function allCoaches(): array
    {
        $snapshot = $this->activeSnapshotRows();
        return is_array($snapshot['coaches'] ?? null) ? $snapshot['coaches'] : [];
    }

    protected function hydrateFromSnapshot(array $snapshot): void
    {
        $this->coachDatabaseSnapshotMemo = is_array($snapshot) ? $snapshot : [];
        $this->coachSearchIndexMemo = null;
        $this->schoolCoachIndexMemo = null;

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