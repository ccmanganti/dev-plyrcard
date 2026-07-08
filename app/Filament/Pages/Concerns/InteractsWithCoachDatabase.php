<?php

namespace App\Filament\Pages\Concerns;

use App\Services\CoachDatabaseService;
use App\Services\GoHighLevelService;
use App\Support\TrackingLinkRewriter;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
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
    public string $conversationStatusFilter = 'all';
    public string $composeSchoolSearch = '';
    public string $favoriteSchoolSearch = '';
    public string $listSchoolSearch = '';
    public string $divisionFilter = '';
    public string $conferenceFilter = '';
    public string $sort = 'name';
    public string $schoolViewMode = 'grid';
    public string $newListName = '';
    public string $newListColor = '#ff6338';
    public bool $showNewListComposer = false;
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
    public array $composeAttachmentUploads = [];
    public array $composeAttachments = [];
    public ?string $selectedTemplateId = null;
    public bool $templateIsNew = true;
    public bool $isSavingTemplate = false;
    public bool $templateEditorOpen = false;
    public string $templateSearch = '';

    public ?string $campaignTemplateId = null;
    public ?string $previewTemplateId = null;
    public string $campaignName = '';
    public string $campaignSubject = '';
    public string $campaignPreviewText = '';
    public string $campaignBody = '';
    public string $campaignOriginalHtml = '';
    public bool $campaignTemplateIsDesign = false;
    public array $campaignEditableBlocks = [];
    public array $selectedSchoolIds = [];
    public string $campaignTargetMode = 'coaches';
    public string $campaignCoachSearch = '';
    public array $campaignCoachIds = [];
    public string $campaignListKey = '';
    public string $campaignSchoolId = '';
    public bool $campaignHeadCoachOnly = false;
    public bool $composeShowCcBcc = false;
    public string $campaignCc = '';
    public string $campaignBcc = '';
    public bool $showComposePreview = false;
    public bool $composeTemplateMenuOpen = false;
    public bool $composeChooseCoachesOpen = false;
    public bool $composeSchoolPickerOpen = false;
    public bool $composeTemplateAppliedRecently = false;
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

        /**
         * Do not hydrate every school's coaches through /contacts/business during a normal
         * Livewire refresh. That endpoint can timeout on larger GHL locations and it runs
         * inside the browser request. We already load coaches through the paged Contacts
         * endpoint above, then merge/group them into schools. If a specific school needs
         * fresh coach rows later, load it on demand from the drawer/detail action.
         *
         * Set GHL_COACH_DATABASE_HYDRATE_SCHOOL_COACHES_ON_REFRESH=true only for small
         * accounts where the per-business endpoint is fast enough.
         */
        $hydrateBusinessCoaches = (bool) config('ghl.coach_database.hydrate_school_coaches_on_refresh', false);

        if ($hydrateBusinessCoaches) {
            $schoolsToHydrate = collect($snapshot['schools'] ?? [])
                ->filter(fn (array $school): bool => empty($school['coaches_loaded']) && filled($school['business_id'] ?? null))
                ->take((int) config('ghl.coach_database.businesses_per_batch', 2))
                ->values();

            foreach ($schoolsToHydrate as $school) {
                $school = $this->loadSchoolCoachesIntoSnapshot($school, $snapshot, $service, $user);
            }
        } else {
            $snapshot['schools'] = collect($snapshot['schools'] ?? [])
                ->filter(fn ($school): bool => is_array($school))
                ->map(function (array $school): array {
                    $school['coaches_loaded'] = true;
                    $school['coaches_loaded_from'] = $school['coaches_loaded_from'] ?? 'contacts_page';
                    return $school;
                })
                ->values()
                ->all();
        }

        $loadedPages++;
        $snapshot['loaded_pages'] = $loadedPages;
        $hasUnhydratedSchools = $hydrateBusinessCoaches
            && collect($snapshot['schools'] ?? [])->contains(fn (array $school): bool => empty($school['coaches_loaded']) && filled($school['business_id'] ?? null));
        $snapshot['has_more_data'] = (bool) ($snapshot['businesses_have_more'] ?? false)
            || (bool) ($snapshot['contacts_have_more'] ?? false)
            || $hasUnhydratedSchools;
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
                    'logo_url' => $first['school_logo_url'] ?? $first['business_logo_url'] ?? $first['logo_url'] ?? null,
                    'school_logo_url' => $first['school_logo_url'] ?? $first['business_logo_url'] ?? $first['logo_url'] ?? null,
                    'business_logo_url' => $first['business_logo_url'] ?? $first['school_logo_url'] ?? $first['logo_url'] ?? null,
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

        $contactSchoolData = $contacts
            ->filter(fn (array $coach): bool => filled($coach['school'] ?? null))
            ->groupBy(fn (array $coach): string => strtolower(trim((string) ($coach['school'] ?? ''))))
            ->map(function (Collection $group): array {
                $first = $group->first() ?: [];

                return [
                    'logo_url' => $first['school_logo_url'] ?? $first['business_logo_url'] ?? $first['logo_url'] ?? null,
                    'school_logo_url' => $first['school_logo_url'] ?? $first['business_logo_url'] ?? $first['logo_url'] ?? null,
                    'business_logo_url' => $first['business_logo_url'] ?? $first['school_logo_url'] ?? $first['logo_url'] ?? null,
                    'conference' => $first['conference'] ?? null,
                    'division' => $first['division'] ?? null,
                    'city' => $first['city'] ?? null,
                    'state' => $first['state'] ?? null,
                ];
            });

        $snapshot['schools'] = $existingSchools
            ->map(function (array $school) use ($contactSchoolData): array {
                $key = strtolower(trim((string) ($school['name'] ?? '')));
                $incoming = $contactSchoolData->get($key, []);

                foreach (['logo_url', 'school_logo_url', 'business_logo_url', 'conference', 'division', 'city', 'state'] as $field) {
                    if (blank($school[$field] ?? null) && filled($incoming[$field] ?? null)) {
                        $school[$field] = $incoming[$field];
                    }
                }

                return $school;
            })
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

        try {
            $result = $service->getContactsForBusinessForUser(
                $user,
                $businessId,
                0,
                (int) config('ghl.coach_database.business_contacts_page_limit', 50),
                $school,
            );
        } catch (\Throwable $exception) {
            Log::warning('Recruiting school coach hydration skipped after exception.', [
                'user_id' => $user?->id,
                'business_id' => $businessId,
                'error' => $exception->getMessage(),
            ]);

            $school['coaches_loaded'] = true;
            $school['coaches_load_failed'] = true;
            $school['coaches_load_error'] = 'GHL timed out while loading this school coaches.';
            return $school;
        }

        if (! ($result['success'] ?? false)) {
            $school['coaches_loaded'] = true;
            $school['coaches_load_failed'] = true;
            $school['coaches_load_error'] = $result['error'] ?? 'Unable to load coaches for this school.';
            return $school;
        }

        $coaches = $this->mergeCoachRowsById($snapshot['coaches'] ?? [], $result['coaches'] ?? []);

        $snapshot['coaches'] = $coaches;
        $snapshot['schools'] = collect($snapshot['schools'] ?? [])->map(function (array $existing) use ($school, $result): array {
            if ((string) ($existing['id'] ?? '') !== (string) ($school['id'] ?? '')) {
                return $existing;
            }

            $logoUrl = collect($result['coaches'] ?? [])
                ->filter(fn ($coach): bool => is_array($coach))
                ->map(fn (array $coach): ?string => $coach['school_logo_url'] ?? $coach['business_logo_url'] ?? $coach['logo_url'] ?? null)
                ->filter(fn (?string $url): bool => filled($url))
                ->first();

            $firstCoach = collect($result['coaches'] ?? [])->first(fn ($coach): bool => is_array($coach)) ?: [];

            $existing['coaches_loaded'] = true;
            $existing['coach_count'] = count($result['coaches'] ?? []);
            $existing['logo_url'] = $existing['logo_url'] ?? $logoUrl;
            $existing['school_logo_url'] = $existing['school_logo_url'] ?? $logoUrl;
            $existing['business_logo_url'] = $existing['business_logo_url'] ?? $logoUrl;
            $existing['conference'] = $existing['conference'] ?? ($firstCoach['conference'] ?? null);
            $existing['division'] = $existing['division'] ?? ($firstCoach['division'] ?? null);
            return $existing;
        })->values()->all();

        return $school;
    }

    /**
     * Backward-compatible default refresh action.
     *
     * The header reload button now exposes two actions:
     * - refreshStatsOnly(): lightweight one-pass GHL stats sync
     * - refreshCoachDatabase(): full Coach Database dataset reload
     *
     * Keep refreshData() as the default/stats action so older buttons, keyboard
     * shortcuts, or links still perform the safer lightweight refresh.
     */
    public function refreshData(bool $notify = true, string $message = 'Syncing recruiting stats from GHL.'): void
    {
        $this->refreshStatsOnly($notify, $message);
    }

    public function refreshStatsOnly(bool $notify = true, string $message = 'Syncing recruiting stats from GHL.'): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $this->error = null;
        $this->startRecruitingStatsSyncInBackground($user);

        $snapshot = Cache::get($this->activeCacheKey(), $this->emptySnapshot());
        if (is_array($snapshot)) {
            $this->hydrateFromSnapshot($snapshot);
        }

        $this->isLoadingDataset = false;
        $this->hasMoreData = (bool) ($snapshot['has_more_data'] ?? false);

        if ($notify) {
            Notification::make()
                ->title('Recruiting Center')
                ->body('Stats sync started in the background. The dashboard will use the latest cached stats now; refresh again in a moment to see the newly exported GHL values.')
                ->success()
                ->send();
        }
    }

    protected function startRecruitingStatsSyncInBackground($user): void
    {
        $lockKey = 'recruiting:stats-sync-running:' . $user->id;
        $statusKey = 'recruiting:stats-sync-status:' . $user->id;

        if (! Cache::add($lockKey, now()->toDateTimeString(), now()->addMinutes(20))) {
            Cache::put($statusKey, [
                'status' => 'already_running',
                'started_at' => Cache::get($lockKey),
                'user_id' => $user->id,
            ], now()->addMinutes(30));
            return;
        }

        Cache::put($statusKey, [
            'status' => 'running',
            'started_at' => now()->toDateTimeString(),
            'user_id' => $user->id,
        ], now()->addMinutes(30));

        $php = (new PhpExecutableFinder())->find(false) ?: PHP_BINARY;
        $artisan = base_path('artisan');
        $logPath = storage_path('logs/recruiting-stats-sync-' . $user->id . '.log');

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $command = 'start /B "" ' . escapeshellarg($php) . ' ' . escapeshellarg($artisan)
                    . ' recruiting:sync-stats --user=' . (int) $user->id . ' --force --release-lock > ' . escapeshellarg($logPath) . ' 2>&1';
                pclose(popen($command, 'r'));
                return;
            }

            $command = 'nohup ' . escapeshellarg($php) . ' ' . escapeshellarg($artisan)
                . ' recruiting:sync-stats --user=' . (int) $user->id . ' --force --release-lock > ' . escapeshellarg($logPath) . ' 2>&1 &';

            Process::fromShellCommandline($command, base_path())->run();
        } catch (\Throwable $exception) {
            Cache::forget($lockKey);
            Cache::put($statusKey, [
                'status' => 'failed_to_start',
                'error' => $exception->getMessage(),
                'user_id' => $user->id,
                'failed_at' => now()->toDateTimeString(),
            ], now()->addMinutes(30));

            Log::warning('Unable to start recruiting stats sync in background.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function refreshCoachDatabase(bool $notify = true): void
    {
        if (! $this->allowed || $this->locked) {
            return;
        }

        $this->schoolDisplayLimit = 24;
        $this->coachDisplayLimit = 40;
        $this->selectedSchoolId = null;
        $this->selectedCoachId = null;
        $this->selectedConversationId = null;
        $this->search = '';
        $this->coachSearch = '';
        $this->conversationSearch = '';
        $this->conversationSchoolFilter = '';
        $this->composeSchoolSearch = '';
        $this->favoriteSchoolSearch = '';
        $this->listSchoolSearch = '';
        $this->divisionFilter = '';
        $this->conferenceFilter = '';
        $this->error = null;

        $this->isLoadingDataset = true;
        $this->hasMoreData = true;

        $this->startBackgroundLoad(true);

        if ($notify) {
            Notification::make()
                ->title('Recruiting Center')
                ->body('Reloading the full Coach Database from GHL. This can take a moment for large datasets.')
                ->success()
                ->send();
        }
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
        $color = $this->normalizeListColor($this->newListColor);

        $custom = collect($snapshot['custom_list_tags'] ?? []);
        $custom->put($key, [
            'key' => $key,
            'label' => Str::headline($name),
            'tag' => $tag,
            'custom' => true,
            'color' => $color,
        ]);
        $snapshot['custom_list_tags'] = $custom->all();
        $this->selectedListKey = 'custom:' . $key;
        $this->rebuildAndStoreSnapshot($snapshot);
        $this->newListName = '';
        $this->newListColor = '#ff6338';
        $this->showNewListComposer = false;
        Notification::make()->title('Recruiting Center')->body('List created. Add a school or coach to save it to recruiting contacts.')->success()->send();
    }

    protected function normalizeListColor(?string $color): string
    {
        $color = strtolower(trim((string) $color));

        $allowed = [
            '#ff6338',
            '#3b82f6',
            '#22c55e',
            '#f59e0b',
            '#7c5cff',
        ];

        return in_array($color, $allowed, true) ? $color : '#ff6338';
    }

    public function selectList(string $listKey): void
    {
        $this->selectedListKey = $listKey;
    }

    public function clearSelectedList(): void
    {
        $this->selectedListKey = '';
    }

    public function startAddingSchoolsToList(string $listKey): void
    {
        $listKey = trim($listKey);
        if ($listKey === '') {
            return;
        }

        $this->selectedListKey = $listKey;
        $this->section = 'schools';

        Notification::make()
            ->title('My Lists')
            ->body('Choose schools in Discover Schools, then use Add to List to save them.')
            ->success()
            ->send();
    }

    public function deleteCustomList(string $listKey): void
    {
        $listKey = trim($listKey);
        if ($listKey === '') {
            return;
        }

        $snapshot = Cache::get($this->activeCacheKey(), $this->emptySnapshot());
        $custom = collect($snapshot['custom_list_tags'] ?? []);
        $normalizedKey = str_starts_with($listKey, 'custom:') ? substr($listKey, 7) : $listKey;

        $custom->forget($listKey);
        $custom->forget($normalizedKey);

        $snapshot['custom_list_tags'] = $custom->all();

        if ($this->selectedListKey === $listKey || $this->selectedListKey === $normalizedKey) {
            $this->selectedListKey = '';
        }

        $this->rebuildAndStoreSnapshot($snapshot);

        Notification::make()
            ->title('My Lists')
            ->body('List removed. Existing GHL contact tags are left untouched.')
            ->success()
            ->send();
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


    public function updatedConversationStatusFilter(): void
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
        $this->campaignHeadCoachOnly = false;
        $this->composeChooseCoachesOpen = false;
    }

    public function updatedCampaignSchoolId(): void
    {
        $this->campaignCoachIds = [];
        $this->campaignHeadCoachOnly = false;
    }

    public function updatedCampaignCoachIds(): void
    {
        if (! empty($this->campaignCoachIds)) {
            $this->campaignTargetMode = 'coaches';
            $this->campaignHeadCoachOnly = false;
        }
    }

    public function updatedComposeSchoolSearch(): void
    {
        $this->composeSchoolPickerOpen = trim($this->composeSchoolSearch) !== '';
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
        $this->persistDashboardStatsAndActivity($user);
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

        // Always fetch current tracking values for the dashboard.
        // The tracking counters are stored remotely and can change immediately after a send/click,
        // so a cached dashboard summary causes the UI to flash the right value and then fall back.
        try {
            Cache::forget($this->recruitingDashboardActivityCacheKey($user));
            $summary = app(GoHighLevelService::class)->getRecruitingDashboardActivityForUser($user);
        } catch (\Throwable $exception) {
            \Log::warning('Recruiting dashboard activity refresh failed.', [
                'user_id' => $user->id ?? null,
                'error' => $exception->getMessage(),
            ]);
            $summary = [];
        }

        if (! is_array($summary) || empty($summary)) {
            return;
        }

        $remoteStats = $summary['stats'] ?? [];
        if (is_array($remoteStats)) {
            $this->stats = $this->mergeDashboardTrackingStats($this->stats ?? [], $remoteStats);
        }

        $recent = $summary['recent_activity'] ?? [];
        $this->dashboardRecentActivity = is_array($recent) ? array_values($recent) : [];
        $this->dashboardActivitySummary = $summary;

        if (empty($this->conversations) && ! empty($summary['conversations']) && is_array($summary['conversations'])) {
            $this->conversations = array_values($summary['conversations']);
        }

        $this->persistDashboardStatsAndActivity($user);
    }

    protected function mergeDashboardTrackingStats(array $baseStats, array $remoteStats): array
    {
        $merged = array_merge($baseStats, array_filter($remoteStats, fn ($value) => $value !== null));

        foreach ($this->dashboardTrackingStatKeys() as $key) {
            $merged[$key] = max((int) ($baseStats[$key] ?? 0), (int) ($remoteStats[$key] ?? 0), (int) ($merged[$key] ?? 0));
        }

        $profileBreakdown = (int) ($merged['view_profile_website'] ?? 0)
            + (int) ($merged['view_profile_instagram'] ?? 0)
            + (int) ($merged['view_profile_youtube'] ?? 0)
            + (int) ($merged['view_profile_x'] ?? 0)
            + (int) ($merged['view_profile_email_link'] ?? 0);

        $merged['view_profile_total'] = max((int) ($merged['view_profile_total'] ?? 0), $profileBreakdown);
        $merged['profile_views'] = max((int) ($merged['profile_views'] ?? 0), (int) ($merged['view_profile_total'] ?? 0));
        $merged['emails_sent'] = max((int) ($merged['emails_sent'] ?? 0), (int) ($merged['email_sent_count'] ?? 0));
        $merged['email_sent_count'] = max((int) ($merged['email_sent_count'] ?? 0), (int) ($merged['emails_sent'] ?? 0));
        $merged['email_opens'] = max((int) ($merged['email_opens'] ?? 0), (int) ($merged['email_open_count'] ?? 0));
        $socialClicks = (int) ($merged['website_click_count'] ?? 0)
            + (int) ($merged['instagram_click_count'] ?? 0)
            + (int) ($merged['youtube_click_count'] ?? 0)
            + (int) ($merged['x_click_count'] ?? 0);
        $merged['link_clicks'] = max((int) ($merged['link_clicks'] ?? 0), (int) ($merged['email_click_count'] ?? 0) + $socialClicks);
        $merged['trigger_link_clicks'] = max((int) ($merged['trigger_link_clicks'] ?? 0), (int) ($merged['link_clicks'] ?? 0));

        return $merged;
    }

    protected function dashboardTrackingStatKeys(): array
    {
        return [
            'view_profile_total',
            'view_profile_website',
            'view_profile_instagram',
            'view_profile_youtube',
            'view_profile_x',
            'view_profile_email_link',
            'email_sent_count',
            'email_open_count',
            'email_click_count',
            'website_click_count',
            'instagram_click_count',
            'youtube_click_count',
            'x_click_count',
            'emails_sent',
            'email_opens',
            'link_clicks',
            'trigger_link_clicks',
            'coach_replies',
        ];
    }

    protected function persistDashboardStatsAndActivity($user = null): void
    {
        $snapshot = Cache::get($this->activeCacheKey(), $this->emptySnapshot());
        $snapshot['stats'] = $this->mergeDashboardTrackingStats($snapshot['stats'] ?? [], $this->stats ?? []);
        $snapshot['dashboard_recent_activity'] = $this->dashboardRecentActivity ?? [];
        $snapshot['dashboard_activity_summary'] = $this->dashboardActivitySummary ?? [];
        $snapshot['cached_at'] = now()->toDateTimeString();
        $this->storeSnapshot($snapshot);

        if ($user) {
            Cache::forget($this->recruitingDashboardActivityCacheKey($user));
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


    protected function selectedConversationRow(): ?array
    {
        if (! $this->selectedConversationId) {
            return null;
        }

        $conversation = collect($this->conversations ?? [])->firstWhere('id', $this->selectedConversationId);

        return is_array($conversation) ? $conversation : null;
    }

    protected function selectedConversationCoachRow(): ?array
    {
        $conversation = $this->selectedConversationRow();
        $contactId = trim((string) ($conversation['contact_id'] ?? $conversation['contactId'] ?? ''));
        $email = strtolower(trim((string) ($conversation['email'] ?? $conversation['contact_email'] ?? '')));

        if ($contactId !== '') {
            $coach = collect($this->allCoaches())->firstWhere('id', $contactId);
            if (is_array($coach)) {
                return $coach;
            }
        }

        if ($email !== '') {
            $coach = collect($this->allCoaches())->first(function (array $row) use ($email): bool {
                return strtolower(trim((string) ($row['email'] ?? ''))) === $email;
            });

            if (is_array($coach)) {
                return $coach;
            }
        }

        return null;
    }

    public function openSelectedConversationInComposer(): void
    {
        $conversation = $this->selectedConversationRow();
        $coach = $this->selectedConversationCoachRow();
        $contactId = trim((string) ($coach['id'] ?? $conversation['contact_id'] ?? $conversation['contactId'] ?? ''));

        if ($contactId !== '') {
            $this->selectedCoachId = $contactId;
        }

        $coachName = (string) ($coach['name'] ?? $conversation['contact_name'] ?? $conversation['name'] ?? 'Coach');
        $first = trim(explode(' ', $coachName)[0] ?? 'Coach') ?: 'Coach';
        $this->emailSubject = $this->emailSubject ?: 'Re: ' . trim((string) ($conversation['subject'] ?? 'Coach conversation'));
        $this->emailBody = '<p>Hi ' . e($first) . ',</p><p><br></p>';
        $this->section = 'compose';
        $this->activeSubpage = 'compose-email';
    }

    public function starSelectedConversation(): void
    {
        $coach = $this->selectedConversationCoachRow();
        $contactId = trim((string) ($coach['id'] ?? ''));

        if ($contactId === '') {
            Notification::make()->title('Recruiting Center')->body('No matched coach contact found to star.')->warning()->send();
            return;
        }

        $this->favoriteCoach($contactId);
        Notification::make()->title('Recruiting Center')->body('Coach starred.')->success()->send();
    }

    public function scheduleSelectedConversation(): void
    {
        Notification::make()->title('Recruiting Center')->body('Schedule action is ready for calendar integration.')->success()->send();
    }

    public function moreSelectedConversation(): void
    {
        Notification::make()->title('Recruiting Center')->body('More conversation actions are available from the coach profile.')->success()->send();
    }

    public function viewSelectedConversationSchool(): void
    {
        $coach = $this->selectedConversationCoachRow();
        $conversation = $this->selectedConversationRow();
        $schoolId = trim((string) ($coach['school_id'] ?? $coach['business_id'] ?? $coach['ghl_business_id'] ?? $conversation['business_id'] ?? $conversation['company_id'] ?? ''));
        $schoolName = trim((string) ($coach['school'] ?? $coach['company_name'] ?? $conversation['school'] ?? $conversation['company_name'] ?? ''));

        if ($schoolId === '' && $schoolName !== '') {
            $school = collect($this->allSchools())->first(function (array $row) use ($schoolName): bool {
                return strcasecmp(trim((string) ($row['name'] ?? '')), $schoolName) === 0;
            });
            $schoolId = trim((string) ($school['id'] ?? $school['business_id'] ?? $school['name'] ?? ''));
        }

        if ($schoolId === '') {
            Notification::make()->title('Recruiting Center')->body('No matched school found for this conversation.')->warning()->send();
            return;
        }

        $this->openSchoolDashboardModal($schoolId);
    }

    public function addSelectedConversationSchoolToList(): void
    {
        $coach = $this->selectedConversationCoachRow();
        $conversation = $this->selectedConversationRow();
        $schoolId = trim((string) ($coach['school_id'] ?? $coach['business_id'] ?? $coach['ghl_business_id'] ?? $conversation['business_id'] ?? $conversation['company_id'] ?? ''));
        $schoolName = trim((string) ($coach['school'] ?? $coach['company_name'] ?? $conversation['school'] ?? $conversation['company_name'] ?? ''));

        if ($schoolId === '' && $schoolName !== '') {
            $school = collect($this->allSchools())->first(function (array $row) use ($schoolName): bool {
                return strcasecmp(trim((string) ($row['name'] ?? '')), $schoolName) === 0;
            });
            $schoolId = trim((string) ($school['id'] ?? $school['business_id'] ?? $school['name'] ?? ''));
        }

        if ($schoolId === '') {
            Notification::make()->title('Recruiting Center')->body('No matched school found to add to a list.')->warning()->send();
            return;
        }

        $defaultListKey = 'general_recruiting';
        $this->addSchoolToListById($schoolId, $defaultListKey);
        Notification::make()->title('Recruiting Center')->body('School added to General Recruiting.')->success()->send();
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
            'business_id' => $coach['business_id'] ?? $coach['ghl_business_id'] ?? null,
            'ghl_business_id' => $coach['business_id'] ?? $coach['ghl_business_id'] ?? null,
            'coach_name' => $coach['name'] ?? $conversation['contact_name'] ?? null,
            'coach_email' => $to,
            'school' => $coach['school'] ?? $coach['company_name'] ?? null,
            'school_name' => $coach['school'] ?? $coach['company_name'] ?? null,
            'school_logo_url' => $coach['school_logo_url'] ?? $coach['business_logo_url'] ?? $coach['logo_url'] ?? null,
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
            'skip_internal_sent_tracking' => true,
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
        $this->templateEditorOpen = true;
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

        $this->templateEditorOpen = true;
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
        $this->templateBody = $this->normalizeTemplateLinksForCurrentTracking($this->templateHtmlForNativeEditor($template));
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
                <a href="{{InstagramLink}}" data-plyrcard-link="instagram" target="_blank" style="display:inline-block;text-decoration:none;margin-right:8px;margin-bottom:6px;"><span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:999px;background:#000000;vertical-align:middle;"><svg width="18" height="18" viewBox="0 0 24 24" role="img" aria-label="Instagram" style="display:block;"><path fill="#ffffff" d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4c0 3.2-2.6 5.8-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8C2 4.6 4.6 2 7.8 2Zm-.2 2A3.6 3.6 0 0 0 4 7.6v8.8A3.6 3.6 0 0 0 7.6 20h8.8a3.6 3.6 0 0 0 3.6-3.6V7.6A3.6 3.6 0 0 0 16.4 4H7.6Zm9.65 1.5a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5ZM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/></svg></span></a>
                <a href="{{XLink}}" data-plyrcard-link="x" target="_blank" style="display:inline-block;text-decoration:none;margin-right:8px;margin-bottom:6px;"><span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:999px;background:#000000;vertical-align:middle;"><svg width="17" height="17" viewBox="0 0 24 24" role="img" aria-label="X" style="display:block;"><path fill="#ffffff" d="M18.9 2h3.1l-6.8 7.8L23.2 22h-6.3l-4.9-7.3L6.4 22H3.3l7.3-8.4L2.8 2h6.4l4.4 6.6L18.9 2Zm-1.1 17.9h1.7L8.3 4H6.5l11.3 15.9Z"/></svg></span></a>
                <a href="{{YoutubeLink}}" data-plyrcard-link="youtube" target="_blank" style="display:inline-block;text-decoration:none;margin-right:8px;margin-bottom:6px;"><span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:999px;background:#000000;vertical-align:middle;"><svg width="20" height="20" viewBox="0 0 24 24" role="img" aria-label="YouTube" style="display:block;"><path fill="#ffffff" d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8ZM9.6 15.6V8.4L15.8 12l-6.2 3.6Z"/></svg></span></a>
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
        $html = $this->normalizeTemplateLinksForCurrentTracking($this->buildTemplateHtml($bodyText));

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


    public function closeTemplateEditor(): void
    {
        $this->templateEditorOpen = false;
        $this->selectedTemplateId = null;
        $this->previewTemplateId = null;
        $this->templateIsNew = false;
    }


    public function deleteTemplateById(string $templateId): void
    {
        $templateId = trim($templateId);
        if ($templateId === '') {
            return;
        }

        $this->selectedTemplateId = $templateId;
        $this->templateIsNew = $this->isBuiltInTemplateId($templateId);
        $this->deleteTemplate();
        $this->templateEditorOpen = false;
    }

    public function duplicateTemplate(string $templateId): void
    {
        $templateId = trim($templateId);
        if ($templateId === '') {
            return;
        }

        $template = $this->loadTemplateDetail($templateId)
            ?: collect($this->templates)->firstWhere('id', $templateId);

        if (! is_array($template)) {
            Notification::make()->title('Templates')->body('Template could not be duplicated.')->danger()->send();
            return;
        }

        $this->templateEditorOpen = true;
        $this->selectedTemplateId = null;
        $this->previewTemplateId = null;
        $this->campaignTemplateId = null;
        $this->templateIsNew = true;
        $this->templateName = trim((string) ($template['name'] ?? 'Email Template')) . ' Copy';
        $this->templateSubject = $this->templateSubject($template);
        $this->templatePreviewText = $this->templatePreviewText($template);
        $this->templateGraphicUrl = '';
        $this->templateGraphicUpload = null;
        $this->templateInlineImageUpload = null;
        $this->templateBody = $this->normalizeTemplateLinksForCurrentTracking($this->templateHtmlForNativeEditor($template));
        $this->dispatch('rc-template-editor-refresh', body: base64_encode($this->templateBody));
    }

    public function templateQuickAction(string $action): void
    {
        $action = trim($action);
        $body = trim((string) $this->templateBody);

        if ($body === '') {
            Notification::make()->title('Templates')->body('Add template copy first.')->warning()->send();
            return;
        }

        if ($action === 'shorter') {
            $body = preg_replace('/<p>\s*<\/p>/i', '', $body) ?: $body;
            $body = str_replace(['I would love the opportunity to', 'I wanted to'], ['I would like to', 'I want to'], $body);
        } elseif ($action === 'professional') {
            $body = str_replace(['Hi ', 'Thanks,'], ['Hello ', 'Best regards,'], $body);
        } elseif ($action === 'personalize') {
            if (! str_contains($body, '{{SchoolName}}')) {
                $body = '<p>Hello {{CoachFirstName}},</p>' . $body . '<p>I am especially interested in {{SchoolName}} and your program.</p>';
            }
        } elseif ($action === 'improve') {
            if (! str_contains($body, '{{ProfileLink}}')) {
                $body .= '<p>You can view my profile here: <a href="{{ProfileLink}}">{{ProfileLink}}</a></p>';
            }
        }

        $this->templateBody = $body;
        $this->dispatch('rc-template-editor-refresh', body: base64_encode($this->templateBody));
        Notification::make()->title('Templates')->body('Template updated. Review before saving.')->success()->send();
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
        $this->campaignBody = $this->normalizeTemplateLinksForCurrentTracking($this->templateHtmlForNativeEditor($template));

        if (trim($this->campaignBody) === '') {
            $this->campaignBody = $this->normalizeTemplateLinksForCurrentTracking($this->templateTextToHtml(trim(strip_tags((string) ($template['body'] ?? $template['html'] ?? '')))));
        }

        $this->composeTemplateAppliedRecently = true;
        Notification::make()->title('Compose Email')->body('Template loaded.')->success()->send();
    }


    public function openComposePreview(): void
    {
        $this->showComposePreview = true;
    }

    public function closeComposePreview(): void
    {
        $this->showComposePreview = false;
    }

    public function clearComposeRecipients(): void
    {
        $this->campaignSchoolId = '';
        $this->campaignListKey = '';
        $this->campaignCoachIds = [];
        $this->campaignHeadCoachOnly = false;
        $this->composeSchoolSearch = '';
        $this->campaignCoachSearch = '';
        $this->composeChooseCoachesOpen = false;
        $this->composeSchoolPickerOpen = false;
        $this->campaignTargetMode = 'coaches';
    }

    public function setComposeSchoolHeadCoachOnly(): void
    {
        if ($this->campaignSchoolId === '') {
            $this->campaignTargetMode = 'school';
            return;
        }

        $this->campaignTargetMode = 'school';
        $this->campaignHeadCoachOnly = true;
        $this->campaignCoachIds = [];
        $this->composeChooseCoachesOpen = false;
    }

    public function setComposeSchoolAllCoaches(): void
    {
        if ($this->campaignSchoolId === '') {
            $this->campaignTargetMode = 'school';
            return;
        }

        $this->campaignTargetMode = 'school';
        $this->campaignHeadCoachOnly = false;
        $this->campaignCoachIds = [];
        $this->composeChooseCoachesOpen = false;
    }

    public function openComposeCoachChooser(): void
    {
        $this->campaignTargetMode = 'coaches';
        $this->campaignHeadCoachOnly = false;
        $this->composeChooseCoachesOpen = true;

        if (! empty($this->campaignCoachIds)) {
            return;
        }

        if ($this->campaignSchoolId !== '') {
            $this->campaignCoachIds = collect($this->composeSchoolCoaches)
                ->pluck('id')
                ->filter()
                ->map(fn ($id): string => (string) $id)
                ->values()
                ->all();
        }
    }

    public function toggleCampaignCoach(string $coachId): void
    {
        $coachId = trim($coachId);
        if ($coachId === '') {
            return;
        }

        $ids = collect($this->campaignCoachIds)
            ->map(fn ($id): string => (string) $id)
            ->filter()
            ->values();

        if ($ids->contains($coachId)) {
            $this->campaignCoachIds = $ids->reject(fn (string $id): bool => $id === $coachId)->values()->all();
        } else {
            $this->campaignCoachIds = $ids->push($coachId)->unique()->values()->all();
        }

        $this->campaignTargetMode = 'coaches';
        $this->campaignHeadCoachOnly = false;
        $this->composeChooseCoachesOpen = true;
    }

    public function selectAllComposeSchoolCoaches(): void
    {
        $this->campaignCoachIds = collect($this->composeSchoolCoaches)
            ->pluck('id')
            ->filter()
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();

        $this->campaignTargetMode = 'coaches';
        $this->campaignHeadCoachOnly = false;
        $this->composeChooseCoachesOpen = true;
    }

    public function clearComposeCoachSelection(): void
    {
        $this->campaignCoachIds = [];
        $this->campaignTargetMode = $this->campaignSchoolId !== '' ? 'school' : 'coaches';
        $this->campaignHeadCoachOnly = false;
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
        $this->composeAttachmentUploads = [];
        $this->composeAttachments = [];
    }

    public function removeComposeGraphic(): void
    {
        $this->composeGraphicUrl = '';
        $this->composeGraphicUpload = null;
    }

    public function updatedComposeAttachmentUploads(): void
    {
        $this->addComposeAttachments();
    }

    public function addComposeAttachments(): void
    {
        if (empty($this->composeAttachmentUploads)) {
            return;
        }

        $user = Auth::user();
        if (! $user) {
            $this->composeAttachmentUploads = [];
            return;
        }

        $files = collect($this->composeAttachmentUploads)
            ->filter(fn ($file): bool => is_object($file) && method_exists($file, 'getRealPath'))
            ->values();

        if ($files->isEmpty()) {
            $this->composeAttachmentUploads = [];
            return;
        }

        try {
            $this->validate([
                'composeAttachmentUploads.*' => ['file', 'max:25600'],
            ]);
        } catch (\Throwable $exception) {
            $this->composeAttachmentUploads = [];
            Notification::make()->title('Attachments')->body('Each attachment must be 25MB or smaller.')->danger()->send();
            return;
        }

        foreach ($files as $file) {
            try {
                $result = app(CoachDatabaseService::class)->uploadMediaForUser($user, $file);

                if (! ($result['success'] ?? false) || blank($result['url'] ?? null)) {
                    Notification::make()
                        ->title('Attachments')
                        ->body($this->templateErrorMessage($result, 'Unable to upload one attachment to GHL media.'))
                        ->danger()
                        ->send();
                    continue;
                }

                $name = method_exists($file, 'getClientOriginalName')
                    ? (string) $file->getClientOriginalName()
                    : basename((string) ($result['url'] ?? 'attachment'));

                $this->composeAttachments[] = [
                    'name' => $name,
                    'url' => trim((string) $result['url']),
                    'mime_type' => method_exists($file, 'getMimeType') ? (string) $file->getMimeType() : null,
                    'size' => method_exists($file, 'getSize') ? (int) $file->getSize() : null,
                ];
            } catch (\Throwable $exception) {
                Notification::make()->title('Attachments')->body('Unable to upload one attachment to GHL media.')->danger()->send();
            }
        }

        $this->composeAttachmentUploads = [];
    }

    public function removeComposeAttachment(int $index): void
    {
        if (! array_key_exists($index, $this->composeAttachments)) {
            return;
        }

        unset($this->composeAttachments[$index]);
        $this->composeAttachments = array_values($this->composeAttachments);
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
        $html = $this->normalizeTemplateLinksForCurrentTracking($this->buildComposeHtml($bodyText));
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
                'business_id' => $coach['business_id'] ?? $coach['ghl_business_id'] ?? null,
                'ghl_business_id' => $coach['business_id'] ?? $coach['ghl_business_id'] ?? null,
                'coach_name' => $coach['name'] ?? null,
                'coach_email' => $coach['email'] ?? null,
                'school' => $coach['school'] ?? $coach['company_name'] ?? null,
                'school_name' => $coach['school'] ?? $coach['company_name'] ?? null,
                'school_logo_url' => $coach['school_logo_url'] ?? $coach['business_logo_url'] ?? $coach['logo_url'] ?? null,
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
                'cc' => trim($this->campaignCc),
                'bcc' => trim($this->campaignBcc),
                'fromName' => (string) ($user->name ?? 'PLYRCard'),
                'skip_internal_sent_tracking' => true,
                'attachments' => $this->composeAttachments,
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
            '{{InstagramLink}}', '{{TwitterLink}}', '{{XLink}}', '{{YoutubeLink}}', '{{YouTubeLink}}',
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
            '{{HighlightLink}}' => $this->userHighlightUrl('https://plyrcard.com/highlights'),
            '{{ProfileLink}}' => $this->userProfileUrl('https://plyrcard.com/profile'),
            '{{InstagramLink}}' => $this->userSocialUrl('instagram', 'https://instagram.com/yourhandle'),
            '{{TwitterLink}}' => $this->userSocialUrl('x', 'https://x.com/yourhandle'),
            '{{XLink}}' => $this->userSocialUrl('x', 'https://x.com/yourhandle'),
            '{{YoutubeLink}}' => $this->userSocialUrl('youtube', 'https://youtube.com/@yourhandle'),
            '{{YouTubeLink}}' => $this->userSocialUrl('youtube', 'https://youtube.com/@yourhandle'),
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


    /**
     * Templates should store stable merge tokens, not generated /track URLs.
     * Tracking URLs are generated at send time per coach/contact so old templates
     * automatically use the newest cross-domain tracking format without being recreated.
     */
    protected function normalizeTemplateLinksForCurrentTracking(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        return preg_replace_callback('/<a\b(?=[^>]*\bhref\s*=)([^>]*)>(.*?)<\/a>/is', function (array $matches): string {
            $anchor = $matches[0];
            $attributes = $matches[1] ?? '';
            $innerHtml = $matches[2] ?? '';

            if (! preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/is', $attributes, $hrefMatch)) {
                return $anchor;
            }

            $href = html_entity_decode((string) ($hrefMatch[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $token = $this->templateMergeTokenForHref($href, $anchor, $innerHtml);

            if ($token === null) {
                return $anchor;
            }

            return preg_replace('/\bhref\s*=\s*(["\'])(.*?)\1/is', 'href="' . $token . '"', $anchor, 1) ?: $anchor;
        }, $html) ?: $html;
    }

    protected function templateMergeTokenForHref(string $href, string $anchorHtml = '', string $innerHtml = ''): ?string
    {
        $href = trim($href);
        if ($href === '') {
            return null;
        }

        $lowerHref = strtolower($href);
        if (Str::startsWith($lowerHref, ['#', 'mailto:', 'tel:', 'sms:', 'javascript:', 'data:'])) {
            return null;
        }

        $tracked = $this->decodedTrackingHref($href);
        $trackedDestination = strtolower((string) ($tracked['destination_url'] ?? ''));
        $trackedPlatform = strtolower((string) ($tracked['platform'] ?? ''));
        $trackedEvent = strtolower((string) ($tracked['event_type'] ?? ''));

        // If a saved GHL template already contains an old /track/... URL, decode the
        // compact payload and convert it back to the stable merge token. This is the
        // important self-healing step for social icon templates: even if GHL stripped
        // aria-labels/classes from the SVG, the token payload still tells us the real
        // platform and destination.
        if ($trackedPlatform === 'instagram' || str_contains($trackedDestination, 'instagram.com')) {
            return '{{InstagramLink}}';
        }

        if (in_array($trackedPlatform, ['youtube', 'yt'], true) || str_contains($trackedDestination, 'youtube.com') || str_contains($trackedDestination, 'youtu.be')) {
            return str_contains($trackedEvent, 'profile') ? '{{HighlightLink}}' : '{{YoutubeLink}}';
        }

        if (in_array($trackedPlatform, ['x', 'twitter'], true) || str_contains($trackedDestination, 'x.com') || str_contains($trackedDestination, 'twitter.com')) {
            return '{{XLink}}';
        }

        if ($trackedDestination !== '' && (str_contains($trackedDestination, 'plyrcard.com') || str_contains($trackedDestination, 'dev.plyrcard.com') || str_contains($trackedDestination, '127.0.0.1') || str_contains($trackedDestination, 'localhost'))) {
            if (str_contains($trackedDestination, 'highlight') || str_contains($trackedDestination, '#highlights') || $trackedEvent === 'highlight_view') {
                return '{{HighlightLink}}';
            }
            return '{{ProfileLink}}';
        }

        $haystack = strtolower(strip_tags(html_entity_decode($anchorHtml . ' ' . $innerHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8')) . ' ' . $anchorHtml . ' ' . $href);
        $isTracked = str_contains($lowerHref, '/track/click/') || str_contains($lowerHref, '/track/profile/') || str_contains($lowerHref, '/track/open/');

        if (str_contains($haystack, 'data-plyrcard-link="instagram"') || str_contains($haystack, "data-plyrcard-link='instagram'") || str_contains($haystack, 'instagram') || str_contains($lowerHref, 'instagram.com')) {
            return '{{InstagramLink}}';
        }

        if (str_contains($haystack, 'data-plyrcard-link="youtube"') || str_contains($haystack, "data-plyrcard-link='youtube'") || str_contains($haystack, 'youtube') || str_contains($haystack, 'youtu.be') || str_contains($lowerHref, 'youtube.com') || str_contains($lowerHref, 'youtu.be')) {
            return '{{YoutubeLink}}';
        }

        if (str_contains($haystack, 'data-plyrcard-link="x"') || str_contains($haystack, "data-plyrcard-link='x'") || str_contains($haystack, 'twitter') || str_contains($haystack, ' x ') || str_contains($haystack, 'aria-label="x"') || str_contains($lowerHref, 'x.com') || str_contains($lowerHref, 'twitter.com')) {
            return '{{XLink}}';
        }

        if (str_contains($haystack, 'highlight') || str_contains($haystack, 'watch') || str_contains($haystack, 'video') || str_contains($lowerHref, 'youtube.com/watch') || str_contains($lowerHref, 'youtu.be/')) {
            return '{{HighlightLink}}';
        }

        if ($isTracked || str_contains($haystack, 'profile') || str_contains($haystack, 'plyrcard') || str_contains($lowerHref, 'plyrcard.com') || str_contains($lowerHref, 'dev.plyrcard.com') || str_contains($lowerHref, '127.0.0.1') || str_contains($lowerHref, 'localhost')) {
            return '{{ProfileLink}}';
        }

        return null;
    }

    protected function decodedTrackingHref(string $href): array
    {
        $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($href === '' || (! str_contains($href, '/track/click/') && ! str_contains($href, '/track/profile/'))) {
            return [];
        }

        $path = parse_url($href, PHP_URL_PATH) ?: $href;
        $token = '';
        foreach (['/track/click/', '/track/profile/'] as $prefix) {
            $pos = strpos($path, $prefix);
            if ($pos !== false) {
                $token = substr($path, $pos + strlen($prefix));
                break;
            }
        }

        $token = trim((string) preg_replace('/\.gif$/i', '', $token));
        if ($token === '') {
            return [];
        }

        $encoded = str_contains($token, '~') ? explode('~', $token, 2)[0] : (str_contains($token, '.') ? explode('.', $token, 2)[0] : '');
        if ($encoded === '') {
            return [];
        }

        $decoded = strtr($encoded, '-_', '+/');
        $decoded .= str_repeat('=', (4 - strlen($decoded) % 4) % 4);
        $json = base64_decode($decoded, true);
        if (! is_string($json) || $json === '') {
            return [];
        }

        $payload = json_decode($json, true);
        if (! is_array($payload)) {
            return [];
        }

        $aliases = [
            'e' => 'event_type',
            'd' => 'destination_url',
            'p' => 'platform',
            's' => 'source',
        ];

        foreach ($aliases as $alias => $key) {
            if (array_key_exists($alias, $payload) && ! array_key_exists($key, $payload)) {
                $payload[$key] = $payload[$alias];
            }
        }

        return $payload;
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
            'HighlightLink' => $this->userHighlightUrl('[Highlight Link]'),
            'ProfileLink' => $this->userProfileUrl('[Profile Link]'),
            'InstagramLink' => $this->userSocialUrl('instagram', '#'),
            'TwitterLink' => $this->userSocialUrl('x', '#'),
            'XLink' => $this->userSocialUrl('x', '#'),
            'YoutubeLink' => $this->userSocialUrl('youtube', '#'),
            'YouTubeLink' => $this->userSocialUrl('youtube', '#'),
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
            'InstagramLink' => ['instagram_link', 'instagram_url', 'instagram', 'ig_handle', 'social_links.instagram', 'social.instagram'],
            'TwitterLink' => ['twitter_link', 'twitter_url', 'x_link', 'x_url', 'twitter', 'x_handle', 'social_links.twitter', 'social_links.x', 'social.twitter', 'social.x'],
            'XLink' => ['x_link', 'x_url', 'x', 'twitter_link', 'twitter_url', 'x_handle'],
            'YoutubeLink' => ['youtube_link', 'youtube_url', 'youtube', 'yt_url', 'social_links.youtube', 'social.youtube'],
            'YouTubeLink' => ['youtube_link', 'youtube_url', 'youtube', 'yt_url', 'social_links.youtube', 'social.youtube'],
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

    protected function userProfileUrl(string $fallback = ''): string
    {
        $user = Auth::user();
        if (! $user) {
            return $fallback;
        }

        foreach (['profile_link', 'plyrcard_link', 'profileLink', 'website_url', 'public_url', 'url'] as $key) {
            $value = $this->tokenText(data_get($user, $key), '');
            if ($this->isUsableTemplateUrl($value)) {
                return $this->normalizeTemplateUrl($value);
            }
        }

        try {
            $website = method_exists($user, 'activeWebsite') ? $user->activeWebsite()->first() : null;
            foreach (['url', 'public_url', 'website_url', 'slug', 'name'] as $key) {
                $value = $this->tokenText(data_get($website, $key), '');
                if ($this->isUsableTemplateUrl($value)) {
                    return $this->normalizeTemplateUrl($value);
                }
                if ($value !== '' && ! str_contains($value, ' ')) {
                    return rtrim((string) config('app.url', 'https://plyrcard.com'), '/') . '/' . ltrim($value, '/');
                }
            }
        } catch (\Throwable $exception) {
            // Fall back below.
        }

        $slug = Str::of(trim((string) ($user->first_name ?? '') . ' ' . (string) ($user->last_name ?? '')))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->toString();

        if ($slug !== '') {
            return rtrim((string) config('app.url', 'https://plyrcard.com'), '/') . '/' . $slug;
        }

        return $fallback;
    }

    protected function userHighlightUrl(string $fallback = ''): string
    {
        $user = Auth::user();
        if (! $user) {
            return $fallback;
        }

        foreach (['featured_video_url', 'yt_url', 'youtube_url', 'highlight_link', 'highlights_link', 'highlightLink'] as $key) {
            $value = $this->tokenText(data_get($user, $key), '');
            if ($this->isUsableTemplateUrl($value)) {
                return $this->normalizeTemplateUrl($value);
            }
        }

        $profile = $this->userProfileUrl('');
        return $profile !== '' ? rtrim($profile, '/') . '#highlights' : $fallback;
    }

    protected function userSocialUrl(string $platform, string $fallback = ''): string
    {
        $user = Auth::user();
        if (! $user) {
            return $fallback;
        }

        $platform = strtolower(trim($platform));
        $value = match ($platform) {
            'instagram' => $this->firstUserTokenText(['ig_handle', 'instagram', 'instagram_handle', 'instagram_url'], ''),
            'x', 'twitter' => $this->firstUserTokenText(['x_handle', 'x', 'twitter', 'twitter_handle', 'x_url', 'twitter_url'], ''),
            'youtube', 'yt' => $this->firstUserTokenText(['yt_url', 'youtube_url', 'youtube', 'youtube_channel'], ''),
            default => '',
        };

        $url = $this->normalizeSocialUrl($platform, $value);
        return $url !== '' ? $url : $fallback;
    }

    protected function normalizeSocialUrl(string $platform, string $value): string
    {
        $value = trim($value);
        if ($value === '' || $value === '#') {
            return '';
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        $handle = ltrim($value, '@');
        $platform = strtolower(trim($platform));

        if ($platform === 'instagram') {
            return 'https://instagram.com/' . $handle;
        }

        if (in_array($platform, ['x', 'twitter'], true)) {
            return 'https://x.com/' . $handle;
        }

        if (in_array($platform, ['youtube', 'yt'], true)) {
            if (str_contains($handle, '.')) {
                return 'https://' . $handle;
            }
            return 'https://youtube.com/@' . ltrim($handle, '@');
        }

        return str_contains($value, '.') ? 'https://' . $value : '';
    }

    protected function isUsableTemplateUrl(string $value): bool
    {
        $value = trim($value);
        return $value !== '' && $value !== '#' && ! str_starts_with($value, '[');
    }

    protected function normalizeTemplateUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '' || $value === '#') {
            return $value;
        }
        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }
        if (Str::startsWith($value, '/')) {
            return rtrim((string) config('app.url', 'https://plyrcard.com'), '/') . $value;
        }
        return str_contains($value, '.') ? 'https://' . $value : rtrim((string) config('app.url', 'https://plyrcard.com'), '/') . '/' . ltrim($value, '/');
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
                })
                    ->when($this->campaignHeadCoachOnly, function (Collection $schoolCoaches): Collection {
                        $headCoaches = $schoolCoaches->filter(fn (array $coach): bool => str_contains(strtolower((string) ($coach['title'] ?? '')), 'head'));
                        return $headCoaches->isNotEmpty() ? $headCoaches->take(1) : $schoolCoaches->take(1);
                    })
                    ->values(),
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
        $existingStats = $this->mergeDashboardTrackingStats($snapshot['stats'] ?? [], $this->stats ?? []);
        $existingRecentActivity = $snapshot['dashboard_recent_activity'] ?? $this->dashboardRecentActivity ?? [];
        $existingActivitySummary = $snapshot['dashboard_activity_summary'] ?? $this->dashboardActivitySummary ?? [];

        $dashboard = app(CoachDatabaseService::class)->rebuildFromSchoolCompanySnapshot($snapshot['schools'] ?? [], $snapshot['coaches'] ?? [], Auth::user(), $snapshot['custom_list_tags'] ?? []);
        $dashboard['stats'] = $this->mergeDashboardTrackingStats($dashboard['stats'] ?? [], $existingStats);

        $snapshot = array_merge($snapshot, $dashboard, [
            'dashboard_recent_activity' => $existingRecentActivity,
            'dashboard_activity_summary' => $existingActivitySummary,
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

        $savedSchoolsFromRows = $schools->filter(fn (array $school): bool => $this->schoolRowHasSavedFlag($school))->count();
        $favoriteSchoolsFromRows = $schools->filter(fn (array $school): bool => $this->schoolRowHasFavoriteFlag($school))->count();
        $savedSchoolsFromLists = $this->schoolCountFromListLabels(['saved', 'saved schools']);
        $favoriteSchoolsFromLists = $this->schoolCountFromListLabels(['favorite', 'favorites', 'favorite schools']);

        $savedSchools = max(
            (int) (($stats['saved_schools'] ?? 0) ?: 0),
            $savedSchoolsFromRows,
            $savedSchoolsFromLists,
        );

        $favoriteSchools = max(
            (int) (($stats['favorite_schools'] ?? 0) ?: 0),
            $favoriteSchoolsFromRows,
            $favoriteSchoolsFromLists,
        );

        $trackedWebsiteViews = (int) ($stats['view_profile_website'] ?? $stats['website_clicks'] ?? 0);
        $trackedInstagramViews = (int) ($stats['view_profile_instagram'] ?? $stats['instagram_clicks'] ?? 0);
        $trackedYoutubeViews = (int) ($stats['view_profile_youtube'] ?? $stats['youtube_clicks'] ?? 0);
        $trackedXViews = (int) ($stats['view_profile_x'] ?? $stats['x_clicks'] ?? $stats['twitter_clicks'] ?? 0);
        $trackedEmailProfileLinks = (int) ($stats['view_profile_email_link'] ?? 0);

        $trackedProfileTotal = (int) ($stats['view_profile_total'] ?? 0);
        if ($trackedProfileTotal === 0) {
            $trackedProfileTotal = $trackedWebsiteViews + $trackedInstagramViews + $trackedYoutubeViews + $trackedXViews + $trackedEmailProfileLinks;
        }

        $emailSentCount = max((int) ($stats['email_sent_count'] ?? 0), (int) ($stats['emails_sent'] ?? 0), (int) (($stats['campaigns_sent'] ?? 0) ?: 0) + (int) (($stats['personal_emails_sent'] ?? 0) ?: 0));

        $emailOpenCount = (int) ($stats['email_open_count'] ?? $stats['email_opens'] ?? 0);
        $emailClickCount = (int) ($stats['email_click_count'] ?? $stats['email_clicks'] ?? 0);
        $socialClickCount = (int) ($stats['website_click_count'] ?? $stats['website_clicks'] ?? 0) + (int) ($stats['instagram_click_count'] ?? $stats['instagram_clicks'] ?? 0) + (int) ($stats['youtube_click_count'] ?? $stats['youtube_clicks'] ?? 0) + (int) ($stats['x_click_count'] ?? $stats['x_clicks'] ?? $stats['twitter_clicks'] ?? 0);
        $linkClicks = max((int) ($stats['link_clicks'] ?? $stats['trigger_link_clicks'] ?? $stats['trigger_clicks'] ?? 0), $emailClickCount + $socialClickCount);

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
            'website_click_count' => (int) ($stats['website_click_count'] ?? $stats['website_clicks'] ?? 0),
            'instagram_click_count' => (int) ($stats['instagram_click_count'] ?? $stats['instagram_clicks'] ?? 0),
            'youtube_click_count' => (int) ($stats['youtube_click_count'] ?? $stats['youtube_clicks'] ?? 0),
            'x_click_count' => (int) ($stats['x_click_count'] ?? $stats['x_clicks'] ?? $stats['twitter_clicks'] ?? 0),
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


    public function getCoachEngagementRowsProperty(): array
    {
        $platforms = [
            'website' => ['label' => 'Website', 'key' => 'website_click_count', 'class' => 'is-blue', 'icon' => '⌁'],
            'instagram' => ['label' => 'Instagram', 'key' => 'instagram_click_count', 'class' => 'is-pink', 'icon' => '◎'],
            'youtube' => ['label' => 'YouTube', 'key' => 'youtube_click_count', 'class' => 'is-red', 'icon' => '▶'],
            'x' => ['label' => 'X', 'key' => 'x_click_count', 'class' => 'is-neutral', 'icon' => '𝕏'],
            'email' => ['label' => 'Email link', 'key' => 'email_click_count', 'class' => 'is-coral', 'icon' => '↗'],
        ];

        return collect($this->allCoaches())
            ->filter(fn ($coach): bool => is_array($coach) && filled($coach['id'] ?? null))
            ->flatMap(function (array $coach) use ($platforms): array {
                $rows = [];
                $coachName = trim((string) ($coach['name'] ?? 'Coach contact')) ?: 'Coach contact';
                $school = trim((string) ($coach['school'] ?? $coach['company_name'] ?? ''));
                $lastPlatform = strtolower(trim((string) ($coach['last_clicked_platform'] ?? '')));
                $lastUrl = trim((string) ($coach['last_clicked_url'] ?? ''));
                $lastTime = $coach['last_profile_view_at'] ?? $coach['date_updated'] ?? $coach['updated_at'] ?? null;

                foreach ($platforms as $platform => $config) {
                    $count = (int) ($coach[$config['key']] ?? 0);
                    if ($count <= 0) {
                        continue;
                    }

                    $rows[] = [
                        'coach_id' => (string) ($coach['id'] ?? ''),
                        'coach_name' => $coachName,
                        'school' => $school,
                        'title' => $coachName,
                        'copy' => $school !== ''
                            ? $coachName . ' clicked ' . $config['label'] . ' ' . number_format($count) . ' ' . \Illuminate\Support\Str::plural('time', $count) . ' • ' . $school
                            : $coachName . ' clicked ' . $config['label'] . ' ' . number_format($count) . ' ' . \Illuminate\Support\Str::plural('time', $count),
                        'platform' => $config['label'],
                        'platform_key' => $platform,
                        'platform_class' => $config['class'],
                        'platform_icon' => $config['icon'],
                        'clicks' => $count,
                        'url' => $lastPlatform === $platform ? $lastUrl : '',
                        'time' => $lastTime,
                        'time_label' => $lastTime ? \Carbon\Carbon::parse($lastTime)->diffForHumans() : 'Synced',
                    ];
                }

                return $rows;
            })
            ->sortByDesc(fn (array $row): int => (int) ($row['clicks'] ?? 0))
            ->take(30)
            ->values()
            ->all();
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

    public function getGlobalSearchSuggestionsProperty(): array
    {
        $query = $this->normalizeSearchText($this->search);

        if ($query === '') {
            return [
                'schools' => [],
                'coaches' => [],
                'conferences' => [],
                'divisions' => [],
                'lists' => [],
                'total' => 0,
            ];
        }

        $schools = collect($this->allSchools())
            ->filter(fn (array $school): bool => str_contains($this->schoolSearchHaystack($school), $query))
            ->take(5)
            ->map(function (array $school): array {
                return [
                    'type' => 'school',
                    'category' => 'School',
                    'label' => (string) ($school['name'] ?? 'School'),
                    'detail' => trim(collect([$school['conference'] ?? null, $school['division'] ?? null])->filter()->implode(' • ')),
                    'id' => (string) ($school['id'] ?? $school['business_id'] ?? ''),
                    'value' => (string) ($school['name'] ?? ''),
                    'logo_url' => (string) ($school['logo_url'] ?? $school['school_logo_url'] ?? $school['business_logo_url'] ?? ''),
                ];
            })
            ->values();

        $coaches = collect($this->allCoaches())
            ->filter(fn (array $coach): bool => str_contains($this->coachSearchHaystack($coach), $query))
            ->take(5)
            ->map(function (array $coach): array {
                return [
                    'type' => 'coach',
                    'category' => 'Coach',
                    'label' => (string) ($coach['name'] ?? 'Coach'),
                    'detail' => trim(collect([$coach['title'] ?? null, $coach['school'] ?? null])->filter()->implode(' • ')),
                    'id' => (string) ($coach['id'] ?? ''),
                    'value' => (string) ($coach['name'] ?? $coach['email'] ?? ''),
                    'logo_url' => (string) ($coach['logo_url'] ?? $coach['school_logo_url'] ?? $coach['business_logo_url'] ?? ''),
                ];
            })
            ->values();

        $conferences = collect($this->allSchools())
            ->pluck('conference')
            ->filter()
            ->unique(fn ($conference): string => strtolower(trim((string) $conference)))
            ->filter(fn ($conference): bool => str_contains($this->normalizeSearchText([$conference, $this->conferenceSearchTokens($conference)]), $query))
            ->take(5)
            ->map(function ($conference): array {
                $count = collect($this->allSchools())
                    ->filter(fn (array $school): bool => strcasecmp(trim((string) ($school['conference'] ?? '')), trim((string) $conference)) === 0)
                    ->count();

                return [
                    'type' => 'conference',
                    'category' => 'Conference',
                    'label' => (string) $conference,
                    'detail' => number_format($count) . ' school' . ($count === 1 ? '' : 's'),
                    'id' => '',
                    'value' => (string) $conference,
                    'logo_url' => '',
                ];
            })
            ->values();

        $divisions = collect($this->allSchools())
            ->pluck('division')
            ->filter()
            ->unique(fn ($division): string => $this->normalizeDivisionValue($division) ?: strtolower(trim((string) $division)))
            ->filter(fn ($division): bool => str_contains($this->normalizeSearchText([$division, $this->normalizeDivisionValue($division)]), $query))
            ->take(5)
            ->map(function ($division): array {
                $normalized = $this->normalizeDivisionValue($division);
                $count = collect($this->allSchools())
                    ->filter(fn (array $school): bool => $this->divisionMatches($school['division'] ?? '', $normalized ?: (string) $division))
                    ->count();

                return [
                    'type' => 'division',
                    'category' => 'Division',
                    'label' => (string) $division,
                    'detail' => number_format($count) . ' school' . ($count === 1 ? '' : 's'),
                    'id' => $normalized,
                    'value' => (string) $division,
                    'logo_url' => '',
                ];
            })
            ->values();

        $lists = collect($this->lists)
            ->filter(function (array $list) use ($query): bool {
                return str_contains($this->normalizeSearchText([
                    $list['label'] ?? '',
                    $list['name'] ?? '',
                    $list['key'] ?? '',
                    $list['tag'] ?? '',
                ]), $query);
            })
            ->take(5)
            ->map(function (array $list): array {
                $key = (string) ($list['key'] ?? '');
                $tag = strtolower(trim((string) ($list['tag'] ?? '')));
                $schoolCount = collect($this->allSchools())
                    ->filter(fn (array $school): bool => in_array($key, $school['list_keys'] ?? [], true))
                    ->count();
                $coachCount = collect($this->allCoaches())
                    ->filter(function (array $coach) use ($tag): bool {
                        return $tag !== '' && collect($coach['tags'] ?? [])
                            ->contains(fn ($existing): bool => strtolower(trim((string) $existing)) === $tag);
                    })
                    ->count();

                return [
                    'type' => 'list',
                    'category' => 'Student List',
                    'label' => (string) ($list['label'] ?? Str::headline($key ?: 'List')),
                    'detail' => trim(number_format($schoolCount) . ' schools • ' . number_format($coachCount) . ' coaches'),
                    'id' => $key,
                    'value' => (string) ($list['label'] ?? $key),
                    'logo_url' => '',
                ];
            })
            ->values();

        $groups = [
            'schools' => $schools->all(),
            'coaches' => $coaches->all(),
            'conferences' => $conferences->all(),
            'divisions' => $divisions->all(),
            'lists' => $lists->all(),
        ];

        $groups['total'] = collect($groups)->filter(fn ($items, string $key): bool => $key !== 'total')->sum(fn ($items): int => count($items));

        return $groups;
    }

    public function selectGlobalSearchSuggestion(string $type, string $value = '', string $id = ''): void
    {
        $type = strtolower(trim($type));
        $value = trim($value);
        $id = trim($id);

        match ($type) {
            'school' => $this->jumpToSchoolSearchResult($value, $id),
            'coach' => $this->jumpToCoachSearchResult($value, $id),
            'conference' => $this->jumpToConferenceSearchResult($value),
            'division' => $this->jumpToDivisionSearchResult($value, $id),
            'list' => $this->jumpToListSearchResult($id, $value),
            default => null,
        };
    }

    protected function jumpToSchoolSearchResult(string $value, string $id = ''): void
    {
        $school = $this->resolveSchoolSearchTarget($value, $id);
        $schoolId = (string) ($school['id'] ?? $school['business_id'] ?? $id);

        $this->section = 'schools';
        $this->search = '';
        $this->coachSearch = '';
        $this->divisionFilter = '';
        $this->conferenceFilter = '';
        $this->schoolDisplayLimit = 24;

        if ($schoolId !== '') {
            $this->openSchoolDashboardModal($schoolId);
        }
    }

    protected function jumpToCoachSearchResult(string $value, string $id = ''): void
    {
        $coach = $this->resolveCoachSearchTarget($value, $id);
        $school = is_array($coach) ? $this->resolveSchoolForCoachSearchTarget($coach) : null;
        $schoolId = (string) ($school['id'] ?? $school['business_id'] ?? '');

        $this->section = 'schools';
        $this->search = '';
        $this->coachSearch = '';
        $this->divisionFilter = '';
        $this->conferenceFilter = '';
        $this->coachDisplayLimit = 40;
        $this->selectedCoachId = (string) ($coach['id'] ?? $id ?: '') ?: null;

        if ($schoolId !== '') {
            $this->openSchoolDashboardModal($schoolId);
        }
    }

    protected function resolveCoachSearchTarget(string $value, string $id = ''): ?array
    {
        $normalizedValue = $this->normalizeSearchText($value);

        return collect($this->allCoaches())->first(function (array $coach) use ($id, $normalizedValue): bool {
            if ($id !== '' && (string) ($coach['id'] ?? '') === $id) {
                return true;
            }

            if ($normalizedValue === '') {
                return false;
            }

            return str_contains($this->normalizeSearchText([
                $coach['name'] ?? '',
                $coach['email'] ?? '',
                $coach['phone'] ?? '',
                $coach['school'] ?? '',
            ]), $normalizedValue);
        });
    }

    protected function resolveSchoolSearchTarget(string $value, string $id = ''): ?array
    {
        $normalizedValue = $this->normalizeSearchText($value);

        return collect($this->allSchools())->first(function (array $school) use ($id, $normalizedValue): bool {
            if ($id !== '' && in_array($id, [(string) ($school['id'] ?? ''), (string) ($school['business_id'] ?? '')], true)) {
                return true;
            }

            if ($normalizedValue === '') {
                return false;
            }

            return str_contains($this->schoolSearchHaystack($school), $normalizedValue);
        });
    }

    protected function resolveSchoolForCoachSearchTarget(array $coach): ?array
    {
        $businessId = trim((string) ($coach['business_id'] ?? $coach['company_id'] ?? $coach['companyId'] ?? ''));
        $schoolName = trim((string) ($coach['school'] ?? $coach['company_name'] ?? ''));
        $normalizedSchoolName = $this->normalizeSearchText($schoolName);

        return collect($this->allSchools())->first(function (array $school) use ($businessId, $normalizedSchoolName): bool {
            if ($businessId !== '' && in_array($businessId, [(string) ($school['business_id'] ?? ''), (string) ($school['id'] ?? '')], true)) {
                return true;
            }

            if ($normalizedSchoolName === '') {
                return false;
            }

            return $this->normalizeSearchText($school['name'] ?? '') === $normalizedSchoolName;
        });
    }

    protected function jumpToConferenceSearchResult(string $value): void
    {
        $this->section = 'schools';
        $this->search = '';
        $this->coachSearch = '';
        $this->divisionFilter = '';
        $this->conferenceFilter = $value;
        $this->schoolDisplayLimit = 24;
    }

    protected function jumpToDivisionSearchResult(string $value, string $id = ''): void
    {
        $this->section = 'schools';
        $this->search = '';
        $this->coachSearch = '';
        $this->conferenceFilter = '';
        $this->divisionFilter = $id !== '' ? $id : $this->normalizeDivisionValue($value);
        $this->schoolDisplayLimit = 24;
    }

    protected function jumpToListSearchResult(string $id, string $value = ''): void
    {
        $this->section = 'lists';
        $this->search = '';
        $this->coachSearch = '';
        $this->listSchoolSearch = '';
        $this->selectedListKey = $id !== '' ? $id : (collect($this->lists)->firstWhere('label', $value)['key'] ?? '');
    }

    public function clearGlobalSearch(): void
    {
        $this->search = '';
        $this->coachSearch = '';
        $this->divisionFilter = '';
        $this->conferenceFilter = '';
        $this->favoriteSchoolSearch = '';
        $this->listSchoolSearch = '';
    }

    public function getFilteredSchoolsProperty(): array
    {
        return $this->filteredSchoolsQuery()
            ->take($this->schoolDisplayLimit)
            ->map(fn (array $school): array => $this->hydrateSchoolRowForDisplay($school))
            ->values()
            ->all();
    }
    public function getFilteredSchoolsCountProperty(): int { return $this->filteredSchoolsQuery()->count(); }
    public function getCanLoadMoreSchoolsProperty(): bool { return $this->filteredSchoolsCount > count($this->filteredSchools); }
    public function getFilteredCoachesProperty(): array { return $this->filteredCoachesQuery()->take($this->coachDisplayLimit)->values()->all(); }
    public function getFilteredCoachesCountProperty(): int { return $this->filteredCoachesQuery()->count(); }
    public function getCanLoadMoreCoachesProperty(): bool { return $this->filteredCoachesCount > count($this->filteredCoaches); }

    protected function normalizeSchoolLogoCandidate(mixed $value): string
    {
        if (is_null($value)) {
            return '';
        }

        if (is_array($value)) {
            foreach (['url', 'value', 'src', 'link', 'mediaUrl', 'fileUrl', 'downloadUrl', 'thumbnailUrl'] as $key) {
                if (array_key_exists($key, $value)) {
                    $resolved = $this->normalizeSchoolLogoCandidate($value[$key]);
                    if ($resolved !== '') {
                        return $resolved;
                    }
                }
            }

            foreach ($value as $child) {
                $resolved = $this->normalizeSchoolLogoCandidate($child);
                if ($resolved !== '') {
                    return $resolved;
                }
            }

            return '';
        }

        if (! is_scalar($value)) {
            return '';
        }

        $url = trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $url = trim($url, " \t\n\r\0\x0B\"'");

        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        $lower = strtolower($url);
        if (str_starts_with($lower, 'http://') || str_starts_with($lower, 'https://')) {
            return $url;
        }

        return '';
    }

    protected function logoUrlFromCustomFieldContainers(array $row): string
    {
        foreach (['customFields', 'customField', 'custom_fields', 'customFieldValues', 'custom_field_values', 'customValues', 'custom_values'] as $containerKey) {
            $raw = data_get($row, $containerKey, []);

            if (! is_array($raw)) {
                continue;
            }

            foreach ($raw as $fieldKey => $fieldValue) {
                $identifiers = [$fieldKey];

                if (is_array($fieldValue)) {
                    foreach (['id', '_id', 'key', 'name', 'label', 'fieldKey', 'field_key', 'customFieldId', 'custom_field_id', 'fieldId', 'field_id', 'mergeField', 'merge_field', 'placeholder', 'slug'] as $identifierKey) {
                        $identifiers[] = $fieldValue[$identifierKey] ?? null;
                    }
                }

                $isLogoField = collect($identifiers)->contains(fn ($identifier): bool => $this->looksLikeLogoIdentifier($identifier));
                $url = $this->normalizeSchoolLogoCandidate($fieldValue);

                if ($isLogoField && $url !== '') {
                    return $url;
                }
            }
        }

        foreach (['contact', 'business', 'company', 'data', 'result'] as $nestedKey) {
            $nested = data_get($row, $nestedKey);
            if (is_array($nested)) {
                $url = $this->logoUrlFromCustomFieldContainers($nested);
                if ($url !== '') {
                    return $url;
                }
            }
        }

        return '';
    }

    protected function looksLikeLogoIdentifier(mixed $identifier): bool
    {
        if (! is_scalar($identifier)) {
            return false;
        }

        $key = strtolower(trim((string) $identifier));
        $key = trim(str_replace(['{{', '}}'], '', $key), '{} ' . "\t\n\r\0\x0B");
        $key = str_replace([' ', '-', '.', ':', '/', '\\'], '_', $key);

        return $key === 'logo'
            || $key === 'business_logo'
            || $key === 'business_logo_url'
            || $key === 'school_logo'
            || $key === 'school_logo_url'
            || $key === 'contact_school_logo'
            || str_ends_with($key, '_logo')
            || str_contains($key, 'school_logo')
            || str_contains($key, 'business_logo');
    }

    protected function logoUrlForSchoolRow(array $school): string
    {
        $candidates = [
            $school['logo_url'] ?? null,
            $school['school_logo_url'] ?? null,
            $school['business_logo_url'] ?? null,
            $school['logo'] ?? null,
            $school['school_logo'] ?? null,
            $school['business_logo'] ?? null,
            $school['business.logo'] ?? null,
            $school['contact.school_logo'] ?? null,
            data_get($school, 'business.logo'),
            data_get($school, 'business.logo_url'),
            data_get($school, 'business.school_logo'),
            data_get($school, 'contact.school_logo'),
            data_get($school, 'customFields.logo'),
            data_get($school, 'customFields.business.logo'),
            data_get($school, 'customFields.school_logo'),
            data_get($school, 'custom_fields.logo'),
            data_get($school, 'custom_fields.business.logo'),
            data_get($school, 'custom_fields.school_logo'),
            data_get($school, 'customFieldValues.logo'),
            data_get($school, 'customFieldValues.business.logo'),
            data_get($school, 'customFieldValues.school_logo'),
            data_get($school, 'head_coach.logo_url'),
            data_get($school, 'head_coach.school_logo_url'),
            data_get($school, 'head_coach.business_logo_url'),
            data_get($school, 'head_coach.logo'),
            data_get($school, 'head_coach.school_logo'),
            data_get($school, 'head_coach.business.logo'),
            data_get($school, 'head_coach.contact.school_logo'),
        ];

        foreach ($candidates as $candidate) {
            $url = $this->normalizeSchoolLogoCandidate($candidate);
            if ($url !== '') {
                return $url;
            }
        }

        $url = $this->logoUrlFromCustomFieldContainers($school);
        if ($url !== '') {
            return $url;
        }

        foreach (($school['coaches'] ?? []) as $coach) {
            if (! is_array($coach)) {
                continue;
            }

            $url = $this->logoUrlForCoachRow($coach);
            if ($url !== '') {
                return $url;
            }
        }

        return '';
    }

    protected function logoUrlForCoachRow(array $coach): string
    {
        foreach ([
            $coach['logo_url'] ?? null,
            $coach['school_logo_url'] ?? null,
            $coach['business_logo_url'] ?? null,
            $coach['logo'] ?? null,
            $coach['school_logo'] ?? null,
            $coach['business_logo'] ?? null,
            $coach['business.logo'] ?? null,
            $coach['contact.school_logo'] ?? null,
            data_get($coach, 'business.logo'),
            data_get($coach, 'contact.school_logo'),
            data_get($coach, 'customFields.logo'),
            data_get($coach, 'customFields.business.logo'),
            data_get($coach, 'customFields.school_logo'),
            data_get($coach, 'custom_fields.logo'),
            data_get($coach, 'custom_fields.business.logo'),
            data_get($coach, 'custom_fields.school_logo'),
            data_get($coach, 'customFieldValues.logo'),
            data_get($coach, 'customFieldValues.business.logo'),
            data_get($coach, 'customFieldValues.school_logo'),
        ] as $candidate) {
            $url = $this->normalizeSchoolLogoCandidate($candidate);
            if ($url !== '') {
                return $url;
            }
        }

        $url = $this->logoUrlFromCustomFieldContainers($coach);
        if ($url !== '') {
            return $url;
        }

        return '';
    }

    protected function logoUrlFromDashboardSchoolReference(array $school): string
    {
        $businessId = trim((string) ($school['business_id'] ?? $school['id'] ?? ''));
        $schoolNameKey = $this->normalizeSchoolMatchKey((string) ($school['name'] ?? ''));

        $candidateRows = collect($this->topSchools ?? [])
            ->merge($this->dashboardTopEngagedSchools ?? [])
            ->merge($this->allSchools())
            ->filter(fn ($row): bool => is_array($row))
            ->values();

        foreach ($candidateRows as $row) {
            $rowBusinessId = trim((string) ($row['business_id'] ?? $row['id'] ?? ''));
            $rowNameKey = $this->normalizeSchoolMatchKey((string) ($row['name'] ?? ''));

            $matchesBusiness = $businessId !== '' && $rowBusinessId !== '' && $businessId === $rowBusinessId;
            $matchesName = $schoolNameKey !== '' && $rowNameKey !== '' && $schoolNameKey === $rowNameKey;

            if (! $matchesBusiness && ! $matchesName) {
                continue;
            }

            $url = $this->logoUrlForSchoolRow($row);
            if ($url !== '') {
                return $url;
            }
        }

        // Last fallback: pull directly from any coach/contact row for the same school.
        foreach ($this->coachesForSchoolSearch($school) as $coach) {
            if (! is_array($coach)) {
                continue;
            }

            $url = $this->logoUrlForCoachRow($coach);
            if ($url !== '') {
                return $url;
            }
        }

        return '';
    }

    protected function enrichSchoolForDisplay(array $school): array
    {
        return $this->hydrateSchoolRowForDisplay($school);
    }

    protected function hydrateSchoolRowForDisplay(array $school): array
    {
        $coaches = $this->coachesForSchoolSearch($school);
        if (! empty($coaches)) {
            $school['coaches'] = $coaches;
        }

        $headCoach = is_array($school['head_coach'] ?? null) ? $school['head_coach'] : [];
        if (blank($headCoach['name'] ?? null) && ! empty($coaches)) {
            $headCoach = collect($coaches)->first(function (array $coach): bool {
                return str_contains(strtolower((string) ($coach['title'] ?? '')), 'head');
            }) ?: ($coaches[0] ?? []);
            if (is_array($headCoach)) {
                $school['head_coach'] = $headCoach;
            }
        }

        $logoUrl = $this->logoUrlForSchoolRow($school);

        if ($logoUrl === '') {
            $logoUrl = $this->logoUrlFromDashboardSchoolReference($school);
        }

        if ($logoUrl !== '') {
            $school['logo_url'] = $logoUrl;
            $school['school_logo_url'] = $school['school_logo_url'] ?? $logoUrl;
            $school['business_logo_url'] = $school['business_logo_url'] ?? $logoUrl;
        }

        return $school;
    }

    public function getFavoriteSchoolsProperty(): array { return $this->filterSchoolsForSearch(collect($this->allSchools())->filter(fn (array $school): bool => $this->schoolRowHasFavoriteFlag($school)), $this->favoriteSchoolSearch !== '' ? $this->favoriteSchoolSearch : $this->search)->values()->all(); }
    public function getFavoriteCoachesProperty(): array { return collect($this->allCoaches())->filter(fn (array $coach): bool => (bool) ($coach['is_favorite_coach'] ?? false))->take(80)->values()->all(); }


    public function getSavedSchoolsProperty(): array
    {
        return $this->filterSchoolsForSearch(collect($this->allSchools())->filter(fn (array $school): bool => $this->schoolRowHasSavedFlag($school)), $this->favoriteSchoolSearch !== '' ? $this->favoriteSchoolSearch : $this->search)->values()->all();
    }

    public function getSavedCoachesProperty(): array
    {
        return collect($this->allCoaches())->filter(fn (array $coach): bool => (bool) ($coach['is_saved_coach'] ?? false))->take(120)->values()->all();
    }

    protected function schoolRowHasFavoriteFlag(array $school): bool
    {
        if ((bool) ($school['is_favorite'] ?? false) || (bool) ($school['is_favorite_school'] ?? false)) {
            return true;
        }

        $listKeys = collect($school['list_keys'] ?? [])
            ->merge($school['lists'] ?? [])
            ->map(fn ($value): string => strtolower(trim((string) $value)))
            ->filter();

        if ($listKeys->contains(fn (string $key): bool => str_contains($key, 'favorite'))) {
            return true;
        }

        $tags = collect($school['tags'] ?? [])
            ->map(fn ($tag): string => strtolower(trim((string) (is_array($tag) ? ($tag['tag'] ?? $tag['name'] ?? $tag['value'] ?? '') : $tag))))
            ->filter();

        return $tags->contains(strtolower(app(CoachDatabaseService::class)->favoriteSchoolTag()))
            || $tags->contains(fn (string $tag): bool => str_contains($tag, 'favorite school'));
    }

    protected function schoolRowHasSavedFlag(array $school): bool
    {
        if ((bool) ($school['is_saved'] ?? false) || (bool) ($school['is_saved_school'] ?? false)) {
            return true;
        }

        $listKeys = collect($school['list_keys'] ?? [])
            ->merge($school['lists'] ?? [])
            ->map(fn ($value): string => strtolower(trim((string) $value)))
            ->filter();

        if ($listKeys->contains(fn (string $key): bool => str_contains($key, 'saved'))) {
            return true;
        }

        $tags = collect($school['tags'] ?? [])
            ->map(fn ($tag): string => strtolower(trim((string) (is_array($tag) ? ($tag['tag'] ?? $tag['name'] ?? $tag['value'] ?? '') : $tag))))
            ->filter();

        return $tags->contains(strtolower(app(CoachDatabaseService::class)->savedSchoolTag()))
            || $tags->contains(fn (string $tag): bool => str_contains($tag, 'saved school'));
    }

    protected function schoolCountFromListLabels(array $needles): int
    {
        $needles = collect($needles)
            ->map(fn (string $needle): string => strtolower(trim($needle)))
            ->filter()
            ->values();

        if ($needles->isEmpty()) {
            return 0;
        }

        return collect($this->lists ?? [])
            ->filter(function (array $list) use ($needles): bool {
                $haystack = strtolower(trim(implode(' ', array_filter([
                    $list['key'] ?? null,
                    $list['label'] ?? null,
                    $list['tag'] ?? null,
                ], fn ($value): bool => is_scalar($value) && trim((string) $value) !== ''))));

                return $needles->contains(fn (string $needle): bool => str_contains($haystack, $needle));
            })
            ->sum(function (array $list): int {
                $schools = $list['schools'] ?? [];

                if (is_array($schools) && count($schools) > 0) {
                    return collect($schools)
                        ->filter(fn ($school): bool => is_array($school) && filled($school['id'] ?? $school['name'] ?? null))
                        ->unique(fn (array $school): string => strtolower(trim((string) ($school['id'] ?? $school['name'] ?? ''))))
                        ->count();
                }

                return (int) (($list['schools_count'] ?? 0) ?: 0);
            });
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
            ->filter(fn (array $school): bool => in_array((string) ($list['key'] ?? ''), $school['list_keys'] ?? [], true)), $this->listSchoolSearch !== '' ? $this->listSchoolSearch : $this->search)
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

        $query = $this->normalizeSearchText($this->listSchoolSearch !== '' ? $this->listSchoolSearch : $this->search);

        return collect($this->allCoaches())
            ->filter(function (array $coach) use ($tag, $query): bool {
                $inList = collect($coach['tags'] ?? [])
                    ->contains(fn ($existing): bool => strtolower(trim((string) $existing)) === $tag);

                return $inList && ($query === '' || str_contains($this->coachSearchHaystack($coach), $query));
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
            $html = $image . $html;
        }

        if (! empty($this->composeAttachments)) {
            $links = collect($this->composeAttachments)
                ->filter(fn ($attachment): bool => is_array($attachment) && filled($attachment['url'] ?? null))
                ->map(function (array $attachment): string {
                    $name = e((string) ($attachment['name'] ?? 'Attachment'));
                    $url = e((string) ($attachment['url'] ?? ''));
                    return '<li style="margin:6px 0"><a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $name . '</a></li>';
                })
                ->implode('');

            if ($links !== '') {
                $html .= '<div style="margin-top:22px;padding-top:14px;border-top:1px solid #e5e7eb;font-family:Arial,Helvetica,sans-serif"><div style="font-weight:700;margin-bottom:8px;color:#111827">Attachments</div><ul style="margin:0;padding-left:18px">' . $links . '</ul></div>';
            }
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

    public function getComposeRecipientNamesProperty(): string
    {
        return $this->campaignRecipientCoaches()
            ->pluck('name')
            ->filter()
            ->take(8)
            ->implode(', ');
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


    public function getComposeSelectedSchoolProperty(): ?array
    {
        if ($this->campaignSchoolId === '') {
            return null;
        }

        return collect($this->allSchools())->firstWhere('id', $this->campaignSchoolId);
    }

    public function getComposeSchoolCoachesProperty(): array
    {
        $school = $this->composeSelectedSchool;
        if (! is_array($school)) {
            return [];
        }

        $businessId = (string) ($school['business_id'] ?? $school['id'] ?? '');
        $schoolName = trim((string) ($school['name'] ?? ''));

        return collect($this->allCoaches())
            ->filter(fn (array $coach): bool => filled($coach['id'] ?? null) && filled($coach['email'] ?? null))
            ->filter(fn (array $coach): bool => (string) ($coach['business_id'] ?? '') === $businessId || trim((string) ($coach['school'] ?? '')) === $schoolName)
            ->sortBy(function (array $coach): string {
                $title = strtolower((string) ($coach['title'] ?? ''));
                return (str_contains($title, 'head') ? '0' : '1') . '|' . strtolower((string) ($coach['name'] ?? ''));
            })
            ->values()
            ->all();
    }

    public function getComposeTargetLabelProperty(): string
    {
        $count = $this->campaignRecipientCount;

        if ($count <= 0) {
            return 'Add a school';
        }

        return 'Send to ' . number_format($count) . ' coach' . ($count === 1 ? '' : 'es');
    }

    public function getComposeSendingDescriptionProperty(): string
    {
        $count = $this->campaignRecipientCount;
        if ($count <= 0) {
            return 'No school selected — search to add one below';
        }

        $prefix = match (true) {
            $this->campaignTargetMode === 'school' && $this->campaignHeadCoachOnly => 'head coach only',
            $this->campaignTargetMode === 'school' => 'all coaches',
            $this->campaignTargetMode === 'all' => 'all coaches',
            $this->campaignTargetMode === 'list' => 'selected list',
            default => $count . ' coaches',
        };

        return 'Sending to ' . $prefix . ($this->composeRecipientNames !== '' ? ': ' . $this->composeRecipientNames : '');
    }

    public function getComposeTemplateOptionsProperty(): array
    {
        return collect($this->templates)
            ->map(function (array $template): array {
                $subject = trim((string) ($template['subject'] ?? $template['preview_text'] ?? ''));
                $preview = trim((string) ($template['preview_text'] ?? $template['description'] ?? $template['body_preview'] ?? ''));
                if ($preview === '') {
                    $preview = trim(strip_tags((string) ($template['body'] ?? $template['html'] ?? '')));
                }

                return array_merge($template, [
                    'compose_subject_preview' => \Illuminate\Support\Str::limit($subject !== '' ? $subject : 'Recruiting email', 72),
                    'compose_body_preview' => \Illuminate\Support\Str::limit($preview !== '' ? $preview : 'Personalized message preview', 96),
                ]);
            })
            ->values()
            ->all();
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
            ->map(fn (array $school): array => $this->enrichSchoolForDisplay($school))
            ->groupBy(function (array $school): string {
                $businessId = trim((string) ($school['business_id'] ?? ''));
                if ($businessId !== '') {
                    return 'business:' . strtolower($businessId);
                }

                return 'name:' . strtolower(trim((string) ($school['name'] ?? '')));
            })
            ->map(function ($group): array {
                $rows = collect($group)->values();
                $primary = $rows->sortByDesc(function (array $school): int {
                    return (filled($school['logo_url'] ?? null) ? 100 : 0)
                        + (filled($school['business_id'] ?? null) ? 20 : 0)
                        + (int) ($school['coach_count'] ?? 0);
                })->first() ?: [];

                $coachCount = max((int) ($primary['coach_count'] ?? 0), $rows->max(fn (array $school): int => (int) ($school['coach_count'] ?? 0)) ?: 0);
                $primary['coach_count'] = $coachCount;

                if (blank($primary['logo_url'] ?? null)) {
                    $logo = $rows
                        ->map(fn (array $school): string => $this->logoUrlForSchoolRow($school))
                        ->first(fn (string $url): bool => $url !== '');

                    if ($logo) {
                        $primary['logo_url'] = $logo;
                        $primary['school_logo_url'] = $primary['school_logo_url'] ?? $logo;
                        $primary['business_logo_url'] = $primary['business_logo_url'] ?? $logo;
                    }
                }

                return $primary;
            })
            ->sortBy(fn (array $school): string => strtolower((string) ($school['name'] ?? '')))
            ->values()
            ->all();
    }

    public function getComposeSchoolResultsProperty(): array
    {
        $query = $this->normalizeSearchText($this->composeSchoolSearch);

        return collect($this->composeSchoolOptions)
            ->filter(function (array $school) use ($query): bool {
                if ($query === '') {
                    return true;
                }

                $haystack = $this->normalizeSearchText([
                    $school['name'] ?? '',
                    $school['conference'] ?? '',
                    $school['division'] ?? '',
                    $school['state'] ?? '',
                    $school['city'] ?? '',
                    $school['head_coach']['name'] ?? '',
                    $school['head_coach']['email'] ?? '',
                ]);

                return str_contains($haystack, $query);
            })
            ->take($query === '' ? 8 : 12)
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
        $this->campaignHeadCoachOnly = true;
        $this->campaignCoachIds = [];
        $this->composeSchoolPickerOpen = false;
        $this->composeChooseCoachesOpen = false;

        $school = collect($this->allSchools())->first(function (array $school) use ($schoolId): bool {
            return (string) ($school['id'] ?? '') === $schoolId;
        });

        // Clear the picker after selection so the suggestion dropdown does not remain open.
        $this->composeSchoolSearch = '';
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
        $statusFilter = strtolower(trim((string) ($this->conversationStatusFilter ?? 'all')));

        $base = collect($this->conversations ?? []);

        if ($statusFilter === 'unread') {
            $base = $base->filter(fn (array $conversation): bool => (int) ($conversation['unread_count'] ?? 0) > 0);
        } elseif ($statusFilter === 'starred') {
            $base = $base->filter(function (array $conversation): bool {
                $tags = collect($conversation['tags'] ?? [])->map(fn ($tag): string => strtolower(trim((string) $tag)));
                return (bool) ($conversation['starred'] ?? $conversation['is_starred'] ?? false)
                    || $tags->contains(fn (string $tag): bool => str_contains($tag, 'favorite') || str_contains($tag, 'star'));
            });
        }

        if ($schoolFilter === '') {
            return $base->values()->all();
        }

        $coachesByEmail = collect($this->allCoaches())
            ->filter(fn (array $coach): bool => filled($coach['email'] ?? null))
            ->keyBy(fn (array $coach): string => strtolower(trim((string) ($coach['email'] ?? ''))));

        $coachesById = collect($this->allCoaches())
            ->filter(fn (array $coach): bool => filled($coach['id'] ?? null))
            ->keyBy(fn (array $coach): string => (string) ($coach['id'] ?? ''));

        return $base
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

    public function getDivisionsProperty(): array
    {
        return collect($this->allSchools())
            ->pluck('division')
            ->filter()
            ->unique(fn ($division): string => $this->normalizeDivisionValue($division) ?: strtolower(trim((string) $division)))
            ->sort()
            ->values()
            ->all();
    }

    public function getConferencesProperty(): array
    {
        $divisionFilter = trim((string) $this->divisionFilter);

        return collect($this->allSchools())
            ->filter(function (array $school) use ($divisionFilter): bool {
                return $divisionFilter === '' || $this->divisionMatches($school['division'] ?? '', $divisionFilter);
            })
            ->pluck('conference')
            ->filter()
            ->unique(fn ($conference): string => strtolower(trim((string) $conference)))
            ->sort()
            ->values()
            ->all();
    }

    public function clearSchoolFilters(): void
    {
        $this->search = '';
        $this->divisionFilter = '';
        $this->conferenceFilter = '';
        $this->sort = 'name';
    }


    public function toggleSchoolSelection(string $schoolId): void
    {
        $schoolId = trim($schoolId);
        if ($schoolId === '') {
            return;
        }

        $ids = collect($this->selectedSchoolIds)
            ->map(fn ($id): string => (string) $id)
            ->filter()
            ->values();

        if ($ids->contains($schoolId)) {
            $this->selectedSchoolIds = $ids->reject(fn (string $id): bool => $id === $schoolId)->values()->all();
            return;
        }

        $this->selectedSchoolIds = $ids->push($schoolId)->unique()->values()->all();
    }

    public function clearSelectedSchools(): void
    {
        $this->selectedSchoolIds = [];
    }

    public function toggleVisibleSchoolsSelection(): void
    {
        $visibleIds = collect($this->filteredSchools)
            ->map(fn (array $school): string => (string) ($school['id'] ?? $school['business_id'] ?? md5(strtolower(trim((string) ($school['name'] ?? ''))))))
            ->filter()
            ->values();

        if ($visibleIds->isEmpty()) {
            return;
        }

        $current = collect($this->selectedSchoolIds)
            ->map(fn ($id): string => (string) $id)
            ->filter()
            ->values();

        $allVisibleSelected = $visibleIds->every(fn (string $id): bool => $current->contains($id));

        if ($allVisibleSelected) {
            $this->selectedSchoolIds = $current
                ->reject(fn (string $id): bool => $visibleIds->contains($id))
                ->values()
                ->all();
            return;
        }

        $this->selectedSchoolIds = $current
            ->merge($visibleIds)
            ->unique()
            ->values()
            ->all();
    }

    public function getSelectedSchoolCountProperty(): int
    {
        return collect($this->selectedSchoolIds)->filter()->unique()->count();
    }

    public function getVisibleSchoolsSelectedProperty(): bool
    {
        $visibleIds = collect($this->filteredSchools)
            ->map(fn (array $school): string => (string) ($school['id'] ?? $school['business_id'] ?? md5(strtolower(trim((string) ($school['name'] ?? ''))))))
            ->filter()
            ->values();

        if ($visibleIds->isEmpty()) {
            return false;
        }

        $current = collect($this->selectedSchoolIds)
            ->map(fn ($id): string => (string) $id)
            ->filter();

        return $visibleIds->every(fn (string $id): bool => $current->contains($id));
    }

    public function emailSelectedSchools(): void
    {
        $selectedIds = collect($this->selectedSchoolIds)->filter()->unique()->values();

        if ($selectedIds->isEmpty()) {
            Notification::make()->title('Recruiting Center')->body('Select at least one school first.')->danger()->send();
            return;
        }

        $schools = collect($this->allSchools())
            ->filter(function (array $school) use ($selectedIds): bool {
                $id = (string) ($school['id'] ?? $school['business_id'] ?? md5(strtolower(trim((string) ($school['name'] ?? '')))));
                return $selectedIds->contains($id);
            })
            ->values();

        $coachIds = $schools
            ->flatMap(fn (array $school): array => $this->contactIdsForSchool((string) ($school['id'] ?? $school['business_id'] ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->campaignCoachIds = $coachIds;
        $this->campaignTargetMode = 'coaches';
        $this->section = 'compose';

        Notification::make()->title('Recruiting Center')->body('Selected schools were added to Compose Email.')->success()->send();
    }

    public function addSelectedSchoolsToList(string $listKey): void
    {
        $listKey = trim($listKey);
        if ($listKey === '') {
            return;
        }

        foreach (collect($this->selectedSchoolIds)->filter()->unique()->values() as $schoolId) {
            $this->addSchoolToListById((string) $schoolId, $listKey);
        }

        Notification::make()->title('Recruiting Center')->body('Selected schools were added to the list.')->success()->send();
    }

    public function setDivisionFilter(string $division): void
    {
        $this->divisionFilter = $this->divisionFilter === $division ? '' : $division;
        $this->conferenceFilter = '';
        $this->schoolDisplayLimit = 24;
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
        $schoolName = trim((string) ($school['name'] ?? ''));
        $normalizedSchoolName = $this->normalizeSchoolMatchKey($schoolName);

        if ($businessId !== '') {
            $keys[] = 'business:' . $businessId;
        }

        if ($schoolName !== '') {
            $keys[] = 'school:' . strtolower($schoolName);
        }

        $index = $this->schoolCoachSearchIndex();
        $coaches = [];

        foreach (array_unique($keys) as $key) {
            foreach (($index[$key] ?? []) as $coachId => $coach) {
                $coaches[$coachId] = $coach;
            }
        }

        // Fallback: GHL business names and contact company names are not always
        // identical, so exact indexing can miss the coaches that contain the
        // contact.school_logo URL. Match by a normalized school name too.
        if (empty($coaches) && $normalizedSchoolName !== '') {
            foreach ($this->allCoaches() as $coach) {
                if (! is_array($coach)) {
                    continue;
                }

                $coachSchoolKey = $this->normalizeSchoolMatchKey((string) ($coach['school'] ?? $coach['company_name'] ?? $coach['school_or_company'] ?? ''));

                if ($coachSchoolKey === '' || $coachSchoolKey !== $normalizedSchoolName) {
                    continue;
                }

                $coachId = (string) ($coach['id'] ?? md5(json_encode($coach)));
                $coaches[$coachId] = $coach;
            }
        }

        return array_values($coaches);
    }

    protected function normalizeSchoolMatchKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\b(the|university|college|school|of|at)\b/i', ' ', $value) ?: $value;
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?: $value;
        $value = preg_replace('/\s+/', ' ', $value) ?: $value;

        return trim($value);
    }

    protected function listTokensForSchool(array $school): array
    {
        $keys = collect($school['list_keys'] ?? [])
            ->map(fn ($key): string => trim((string) $key))
            ->filter()
            ->values();

        if ($keys->isEmpty()) {
            return [];
        }

        return collect($this->lists)
            ->filter(fn (array $list): bool => $keys->contains((string) ($list['key'] ?? '')))
            ->flatMap(fn (array $list): array => [
                $list['label'] ?? '',
                $list['name'] ?? '',
                $list['key'] ?? '',
                $list['tag'] ?? '',
            ])
            ->filter()
            ->values()
            ->all();
    }

    protected function listTokensForCoach(array $coach): array
    {
        $tags = collect($coach['tags'] ?? [])
            ->map(fn ($tag): string => strtolower(trim((string) $tag)))
            ->filter()
            ->values();

        if ($tags->isEmpty()) {
            return [];
        }

        return collect($this->lists)
            ->filter(fn (array $list): bool => $tags->contains(strtolower(trim((string) ($list['tag'] ?? '')))))
            ->flatMap(fn (array $list): array => [
                $list['label'] ?? '',
                $list['name'] ?? '',
                $list['key'] ?? '',
                $list['tag'] ?? '',
            ])
            ->filter()
            ->values()
            ->all();
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
            $this->listTokensForCoach($coach),
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
            $this->listTokensForSchool($school),
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
        if (isset($snapshot['dashboard_recent_activity']) && is_array($snapshot['dashboard_recent_activity'])) {
            $this->dashboardRecentActivity = array_values($snapshot['dashboard_recent_activity']);
        }
        if (isset($snapshot['dashboard_activity_summary']) && is_array($snapshot['dashboard_activity_summary'])) {
            $this->dashboardActivitySummary = $snapshot['dashboard_activity_summary'];
        }
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