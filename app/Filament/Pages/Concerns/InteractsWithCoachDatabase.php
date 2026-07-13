<?php

namespace App\Filament\Pages\Concerns;

use App\Services\CoachDatabaseService;
use App\Services\CoachDatabaseActionQueueService;
use App\Services\CoachDatabaseBackgroundSyncLauncher;
use App\Services\CoachDatabaseUiSyncService;
use App\Services\CoachDatabaseWebFallbackSyncService;
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
    protected ?array $allSchoolsMemo = null;
    protected ?array $allCoachesMemo = null;
    protected ?array $trackingCoachesMemo = null;

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
    public array $templateAttachmentUploads = [];
    public array $templateAttachments = [];
    public string $composeGraphicUrl = '';
    public $composeGraphicUpload = null;
    public array $composeAttachmentUploads = [];
    public array $composeAttachments = [];
    public ?string $selectedTemplateId = null;
    public bool $templateIsNew = true;
    public bool $isSavingTemplate = false;
    public bool $templateEditorOpen = false;
    public string $templateSearch = '';
    public int $templateEditorRefreshKey = 0;

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
    public bool $isRecruitingSyncRunning = false;
    public ?string $recruitingSyncMode = null;
    public ?string $recruitingSyncStatus = null;
    public ?string $recruitingSyncStartedAt = null;
    public ?string $recruitingSyncFinishedAt = null;
    public ?string $recruitingSyncMessage = null;
    public bool $showNewConversationComposer = false;
    public string $newConversationCoachSearch = '';
    public bool $showScheduleForm = false;
    public ?int $editingScheduleId = null;
    public string $scheduleEventType = 'Game';
    public string $scheduleDate = '';
    public string $scheduleTime = '';
    public string $scheduleOpponent = '';
    public string $scheduleLocation = '';
    public string $scheduleVenue = '';
    public array $notificationSettings = [
        'profile_views' => true,
        'email_opens' => true,
        'coach_replies' => true,
        'weekly_digest' => false,
        'product_news' => false,
    ];
    public ?string $tagSyncedAt = null;

    /**
     * UI-only async state. Remote reads are deferred until after the page is
     * visible so Livewire never blocks the first paint of a tab or modal.
     */
    public bool $isBootingRemoteSection = false;
    public bool $isLoadingConversations = false;
    public bool $isLoadingConversationMessages = false;
    public bool $isLoadingTemplates = false;
    public bool $isLoadingTemplateDetail = false;
    public bool $isRefreshingRemoteData = false;
    public ?string $activeUiOperation = null;
    public ?string $pendingTemplateAction = null;
    public ?string $pendingTemplateActionId = null;

    public function mount(CoachDatabaseService $coachDatabaseService): void
    {
        $requestedSection = trim((string) request()->query('section', ''));
        $allowedSections = ['dashboard','schools','coaches','favorites','lists','conversations','campaigns','compose','schedule','settings','profile'];
        $this->section = in_array($requestedSection, $allowedSections, true) ? $requestedSection : $this->coachDatabaseSection();
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

        // Hydrate the last successful remote UI payload first. The page paints
        // immediately with stale-while-revalidate data, then wire:init refreshes
        // only the active section in a second request.
        $this->hydrateDeferredUiCache();
        $this->refreshRecruitingSyncStatus();

        if (in_array($this->section, ['favorites', 'lists'], true) && $this->allowed && ! $this->locked) {
            // Favorites and Lists must be cache-only during the HTTP request. A slow
            // contacts/search call here previously caused the entire page to fail with
            // cURL error 28. Refresh tag data in a detached CLI process instead.
            $this->syncTagsIfStale(false);
        }

        if ($this->section === 'dashboard') {
            $this->loadDashboardActivity();
        }

        if ($this->section === 'conversations') {
            // Do not call the remote conversations endpoint during mount.
            // The cached rows are already visible and bootDeferredUiData()
            // refreshes them after first paint.
            $this->isLoadingConversations = empty($this->conversations);
        }

        if (in_array($this->section, ['campaigns', 'compose'], true)) {
            // Template retrieval is also deferred. Built-ins/cached templates
            // remain usable while the latest remote list loads.
            $this->isLoadingTemplates = empty($this->templates);
        }

        if ($this->section === 'compose') {
            $this->campaignTargetMode = $this->campaignTargetMode ?: 'list';

            $schoolId = trim((string) request()->query('school', ''));
            if ($schoolId !== '') {
                $this->selectComposeSchool($schoolId);
            }

            $coachId = trim((string) request()->query('coach', ''));
            if ($coachId !== '') {
                $this->selectComposeCoach($coachId);
            }
        }

        if ($this->section === 'settings') {
            $this->loadNotificationSettings();
        }

        if ($this->section === 'schedule') {
            $this->showScheduleForm = false;
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
            'schedule' => class_exists(\App\Filament\Pages\CoachDatabaseSchedule::class) ? \App\Filament\Pages\CoachDatabaseSchedule::getUrl() : \App\Filament\Pages\CoachDatabase::getUrl(['section' => 'schedule']),
            'settings' => class_exists(\App\Filament\Pages\CoachDatabaseSettings::class) ? \App\Filament\Pages\CoachDatabaseSettings::getUrl() : \App\Filament\Pages\CoachDatabase::getUrl(['section' => 'settings']),
            default => \App\Filament\Pages\CoachDatabase::getUrl(),
        };
    }

    /**
     * Called once by wire:init after the HTML is already on screen. Only the
     * active section is refreshed, which keeps navigation and modal opening
     * responsive even when the upstream service is slow.
     */
    public function bootDeferredUiData(): void
    {
        if ($this->isBootingRemoteSection || ! $this->allowed || $this->locked) {
            return;
        }

        $this->isBootingRemoteSection = true;

        try {
            if ($this->section === 'conversations') {
                $this->loadConversations();
            }

            if (in_array($this->section, ['campaigns', 'compose'], true)) {
                $this->loadTemplates();
            }
        } finally {
            $this->isBootingRemoteSection = false;
        }
    }

    public function pollDeferredUiData(): void
    {
        $user = Auth::user();
        if (! $user || ! $this->allowed || $this->locked) {
            return;
        }

        if (! $this->isRefreshingRemoteData
            && ! $this->isLoadingConversations
            && ! $this->isLoadingConversationMessages
            && ! $this->isLoadingTemplates
            && ! $this->isLoadingTemplateDetail) {
            return;
        }

        $this->hydrateDeferredUiCache();

        $conversationStatus = Cache::get(CoachDatabaseUiSyncService::statusKey($user, 'conversations'), []);
        $templateStatus = Cache::get(CoachDatabaseUiSyncService::statusKey($user, 'templates'), []);
        $messageStatus = $this->selectedConversationId
            ? Cache::get(CoachDatabaseUiSyncService::statusKey($user, 'messages', $this->selectedConversationId), [])
            : [];
        $templateDetailStatus = $this->selectedTemplateId
            ? Cache::get(CoachDatabaseUiSyncService::statusKey($user, 'template-detail', $this->selectedTemplateId), [])
            : [];

        $this->isLoadingConversations = $this->deferredUiStatusIsRunning($conversationStatus, $user, 'conversations');
        $this->isLoadingTemplates = $this->deferredUiStatusIsRunning($templateStatus, $user, 'templates');
        $this->isLoadingConversationMessages = $this->selectedConversationId
            ? $this->deferredUiStatusIsRunning($messageStatus, $user, 'messages', $this->selectedConversationId)
            : false;
        $this->isLoadingTemplateDetail = $this->selectedTemplateId
            ? $this->deferredUiStatusIsRunning($templateDetailStatus, $user, 'template-detail', $this->selectedTemplateId)
            : false;

        if ($this->selectedConversationId) {
            $cached = Cache::get($this->deferredUiCacheKey('messages', $this->selectedConversationId), []);
            if (is_array($cached['rows'] ?? null)) {
                $this->messages = collect($cached['rows'])->filter(fn ($row): bool => is_array($row))->values()->all();
                $this->messageLastId = $cached['last_message_id'] ?? $this->messageLastId;
                $this->hasMoreMessages = (bool) ($cached['has_more'] ?? $this->hasMoreMessages);
            }
        }

        if ($this->pendingTemplateAction && $this->pendingTemplateActionId
            && isset($this->templateDetails[$this->pendingTemplateActionId])
            && ! $this->isLoadingTemplateDetail) {
            $action = $this->pendingTemplateAction;
            $templateId = $this->pendingTemplateActionId;
            $this->pendingTemplateAction = null;
            $this->pendingTemplateActionId = null;

            if ($action === 'duplicate') {
                $this->duplicateTemplate($templateId);
            } elseif ($action === 'use-compose') {
                $this->useTemplateForCompose($templateId);
            }
        }

        if ($this->selectedTemplateId && isset($this->templateDetails[$this->selectedTemplateId]) && ! $this->isLoadingTemplateDetail) {
            $template = $this->templateDetails[$this->selectedTemplateId];
            if (is_array($template)) {
                $this->templateName = trim((string) ($template['name'] ?? $this->templateName)) ?: 'Untitled Template';
                $this->templateSubject = $this->templateSubject($template);
                $this->templatePreviewText = $this->templatePreviewText($template);
                $this->templateAttachments = $this->extractPlyrcardAttachmentLinks($this->templateHtml($template));
                $newBody = $this->canonicalizeTemplateEditorHtml($this->templateHtmlForNativeEditor($template));
                if ($newBody !== '' && $newBody !== $this->templateBody) {
                    $this->templateBody = $newBody;
                    $this->templateEditorRefreshKey++;
                    $this->dispatch('rc-template-editor-refresh', body: base64_encode($this->templateBody), key: $this->templateEditorRefreshKey);
                }
            }
        }

        $this->isRefreshingRemoteData = $this->isLoadingConversations
            || $this->isLoadingTemplates
            || $this->isLoadingConversationMessages
            || $this->isLoadingTemplateDetail;

        if (! $this->isRefreshingRemoteData) {
            $this->activeUiOperation = null;
        }
    }

    protected function deferredUiStatusIsRunning(array $status, $user, string $type, ?string $reference = null): bool
    {
        if (Cache::has(CoachDatabaseUiSyncService::lockKey($user, $type, $reference))) {
            return true;
        }

        $state = strtolower((string) ($status['status'] ?? ''));
        if (! in_array($state, ['queued', 'running', 'already_running'], true)) {
            return false;
        }

        $startedAt = $status['started_at'] ?? $status['queued_at'] ?? null;
        if ($startedAt) {
            try {
                return \Illuminate\Support\Carbon::parse($startedAt)->greaterThan(now()->subMinutes(2));
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    protected function startDeferredUiSync(string $type, ?string $reference = null, bool $force = false): void
    {
        $user = Auth::user();
        if (! $user || ! $this->allowed || $this->locked) {
            return;
        }

        $type = trim(strtolower($type));
        $reference = filled($reference) ? trim((string) $reference) : null;
        $lockKey = CoachDatabaseUiSyncService::lockKey($user, $type, $reference);
        $statusKey = CoachDatabaseUiSyncService::statusKey($user, $type, $reference);
        $launchKey = CoachDatabaseUiSyncService::launchKey($user, $type, $reference);

        if (! $force && (Cache::has($lockKey) || ! Cache::add($launchKey, true, now()->addSeconds(30)))) {
            return;
        }

        Cache::put($statusKey, [
            'status' => 'queued',
            'type' => $type,
            'reference' => $reference,
            'user_id' => $user->id,
            'queued_at' => now()->toIso8601String(),
            'message' => 'Refresh queued.',
        ], now()->addHours(2));

        $this->isRefreshingRemoteData = true;
        $this->activeUiOperation = match ($type) {
            'conversations' => 'Loading conversations',
            'messages' => 'Loading messages',
            'templates' => 'Loading templates',
            'template-detail' => 'Loading template',
            default => 'Loading data',
        };

        try {
            $php = (new PhpExecutableFinder())->find(false) ?: PHP_BINARY;
            $artisan = base_path('artisan');
            $arguments = ' --user=' . escapeshellarg((string) $user->id)
                . ' --type=' . escapeshellarg($type);

            if ($reference !== null) {
                $arguments .= ' --reference=' . escapeshellarg($reference);
            }
            if ($force) {
                $arguments .= ' --force --release-lock';
            }

            $logPath = storage_path('logs/recruiting-ui-sync-' . $user->id . '-' . preg_replace('/[^a-z0-9_-]+/i', '-', $type) . '.log');

            if (PHP_OS_FAMILY === 'Windows') {
                $command = 'start /B "" ' . escapeshellarg($php) . ' ' . escapeshellarg($artisan)
                    . ' recruiting:sync-ui' . $arguments . ' > ' . escapeshellarg($logPath) . ' 2>&1';
                Process::fromShellCommandline($command, base_path())->run();
                return;
            }

            $command = 'nohup ' . escapeshellarg($php) . ' ' . escapeshellarg($artisan)
                . ' recruiting:sync-ui' . $arguments . ' > ' . escapeshellarg($logPath) . ' 2>&1 &';
            Process::fromShellCommandline($command, base_path())->run();
        } catch (\Throwable $exception) {
            Cache::forget($launchKey);
            Cache::put($statusKey, [
                'status' => 'failed_to_start',
                'type' => $type,
                'reference' => $reference,
                'user_id' => $user->id,
                'failed_at' => now()->toIso8601String(),
                'message' => 'Unable to start the refresh. Cached data was kept.',
                'error' => $exception->getMessage(),
            ], now()->addHours(2));
            Log::warning('Unable to start Recruiting Center deferred UI sync.', [
                'user_id' => $user->id,
                'type' => $type,
                'reference' => $reference,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function deferredUiCachePrefix(): string
    {
        $user = Auth::user();
        return $user ? CoachDatabaseUiSyncService::cachePrefix($user) : 'coach-database:ui:guest';
    }

    protected function deferredUiCacheKey(string $type, ?string $suffix = null): string
    {
        $user = Auth::user();
        if (! $user) {
            return $this->deferredUiCachePrefix() . ':' . trim($type);
        }

        return CoachDatabaseUiSyncService::cacheKey($user, $type, $suffix);
    }

    protected function hydrateDeferredUiCache(): void
    {
        $conversationCache = Cache::get($this->deferredUiCacheKey('conversations'), []);
        if (is_array($conversationCache) && ! empty($conversationCache['rows'])) {
            $this->conversations = collect($conversationCache['rows'])
                ->filter(fn ($row): bool => is_array($row))
                ->take((int) config('coach-database-sync.ui.conversation_row_cap', 25))
                ->values()
                ->all();
        }

        $templateCache = Cache::get($this->deferredUiCacheKey('templates'), []);
        if (is_array($templateCache)) {
            $rows = $templateCache['rows'] ?? [];
            if (is_array($rows)) {
                $this->templates = collect($rows)
                    ->filter(fn ($row): bool => is_array($row))
                    ->merge($this->hardcodedEmailTemplates())
                    ->unique(fn (array $row): string => (string) ($row['id'] ?? md5(json_encode($row))))
                    ->take((int) config('coach-database-sync.ui.template_row_cap', 100))
                    ->values()
                    ->all();
            }

            if (is_array($templateCache['details'] ?? null)) {
                $this->templateDetails = $templateCache['details'];
            }

            $this->templateSourceSummary = (string) ($templateCache['summary'] ?? $this->templateSourceSummary);
            $this->templateSourceDebug = is_array($templateCache['debug'] ?? null) ? $templateCache['debug'] : $this->templateSourceDebug;
            $this->templateConnectionKey = $templateCache['connection_key'] ?? $this->templateConnectionKey;
        }

        if (empty($this->templates) && in_array($this->section, ['campaigns', 'compose'], true)) {
            $this->templates = $this->hardcodedEmailTemplates();
        }
    }

    public function startBackgroundLoad(bool $force = false): void
    {
        if (! $this->allowed || $this->locked) {
            return;
        }

        // Compatibility entry point. Large Recruiting Center datasets must never be fetched from
        // a Livewire request. A forced load starts the detached Artisan sync; a
        // normal call only refreshes the cache/status already produced by it.
        if ($force) {
            $this->refreshCoachDatabase(false);
            return;
        }

        $this->refreshRecruitingSyncStatus();
        $snapshot = Cache::get($this->activeCacheKey(), $this->emptySnapshot());
        $snapshot = is_array($snapshot) ? $snapshot : $this->emptySnapshot();

        if (($snapshot['cached_at'] ?? null) !== $this->cachedAt) {
            $this->hydrateFromSnapshot($snapshot);
        }

        $needsInitialDataset = empty($snapshot['schools'] ?? [])
            || empty($snapshot['coaches'] ?? [])
            || (bool) ($snapshot['has_more_data'] ?? false)
            || ! (bool) ($snapshot['dataset_reconciled'] ?? false);

        if ($needsInitialDataset && ! $this->isRecruitingSyncRunning) {
            $this->refreshCoachDatabase(false);
        }
    }

    public function loadNextBatch(): void
    {
        // Kept for older Blade/JavaScript listeners. No Recruiting Center API work is allowed in
        // this Livewire method because two sequential network calls plus a full
        // snapshot rebuild can exceed PHP's 30-second web request limit.
        $this->refreshRecruitingSyncStatus();

        $snapshot = Cache::get($this->activeCacheKey(), $this->emptySnapshot());
        if (is_array($snapshot) && ($snapshot['cached_at'] ?? null) !== $this->cachedAt) {
            $this->hydrateFromSnapshot($snapshot);
        }

        $this->isLoadingDataset = $this->isRecruitingSyncRunning
            && $this->recruitingSyncMode === 'full_database_reload';
        $this->hasMoreData = false;
    }

    public function pollRealtime(): void
    {
        if (! $this->allowed || $this->locked) {
            return;
        }

        // Most environments only read cached progress here. When auto mode has selected the
        // browser-assisted compatibility runner, this passive poll performs one bounded API page
        // (maximum a few seconds) and persists its checkpoint. A cache lock prevents duplicate
        // work from multiple tabs. The expensive final read-model swap still happens atomically.
        $this->refreshRecruitingSyncStatus();

        $user = Auth::user();
        if ($user && $this->recruitingSyncMode === 'full_database_reload') {
            $rawStatus = Cache::get($this->recruitingStatsSyncStatusKey($user), []);
            $rawStatus = is_array($rawStatus) ? $rawStatus : [];
            $launchDriver = strtolower((string) ($rawStatus['launch_driver'] ?? ''));
            $syncState = strtolower((string) ($rawStatus['status'] ?? ''));

            if (in_array($launchDriver, ['web_tick', 'incremental_livewire'], true) && in_array($syncState, ['running', 'queued', 'starting', 'waiting_for_worker', 'stalled'], true)) {
                try {
                    app(CoachDatabaseWebFallbackSyncService::class)->tick($user);
                } catch (\Throwable $exception) {
                    Log::warning('Coach Database incremental background poll tick failed safely.', [
                        'user_id' => $user->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
                $this->refreshRecruitingSyncStatus();
            }
        }

        $this->refreshContactTagSyncStatus();
        $snapshot = Cache::get($this->activeCacheKey(), $this->emptySnapshot());

        if (is_array($snapshot) && ($snapshot['cached_at'] ?? null) !== $this->cachedAt) {
            $this->hydrateFromSnapshot($snapshot);
        }

        $this->isLoadingDataset = $this->isRecruitingSyncRunning
            && $this->recruitingSyncMode === 'full_database_reload';
        $this->hasMoreData = false;

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
        $businessId = trim((string) ($school['business_id'] ?? $school['company_id'] ?? $school['id'] ?? ''));
        $schoolName = trim((string) ($school['name'] ?? $school['school_name'] ?? $school['company_name'] ?? ''));
        $normalizedSchoolName = $this->normalizeSchoolMatchKey($schoolName);

        if ($businessId === '' && $normalizedSchoolName === '') {
            return $school;
        }

        $result = [
            'success' => true,
            'coaches' => [],
            'count' => 0,
            'total' => 0,
        ];

        // Use the authoritative business roster when a Recruiting Center Business ID exists.
        if ($businessId !== '') {
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

                $result = [
                    'success' => false,
                    'coaches' => [],
                    'count' => 0,
                    'total' => 0,
                    'error' => 'Recruiting Center timed out while loading this school coaches.',
                ];
            }
        }

        // Cross-reference the already-loaded generic contacts by Business / Company /
        // School Name. These rows recover contacts whose Recruiting Center business association is
        // missing even though their Business Name field is correct.
        $nameMatchedCoaches = collect($snapshot['coaches'] ?? [])
            ->filter(fn ($coach): bool => is_array($coach))
            ->filter(fn (array $coach): bool => $this->coachBelongsToSchool($coach, $businessId, $schoolName, $normalizedSchoolName))
            ->values()
            ->all();

        $businessCoaches = collect($result['coaches'] ?? [])
            ->filter(fn ($coach): bool => is_array($coach))
            ->values()
            ->all();

        $resolvedSchoolCoaches = $this->mergeCoachRowsById($businessCoaches, $nameMatchedCoaches);
        $snapshot['coaches'] = $this->mergeCoachRowsById($snapshot['coaches'] ?? [], $resolvedSchoolCoaches);

        $normalizedBusinessId = strtolower($businessId);
        $nameOnlyCount = collect($nameMatchedCoaches)
            ->filter(function (array $coach) use ($normalizedBusinessId): bool {
                if ($normalizedBusinessId === '') {
                    return true;
                }

                return ! in_array($normalizedBusinessId, $this->coachBusinessIdCandidates($coach), true);
            })
            ->map(fn (array $coach): string => $this->coachTrackingIdentity($coach))
            ->unique()
            ->count();

        $associatedCount = max(
            count($businessCoaches),
            (int) ($result['count'] ?? 0),
            (int) ($result['total'] ?? 0),
        );
        $crossReferencedCount = max($associatedCount + $nameOnlyCount, count($resolvedSchoolCoaches));

        $snapshot['schools'] = collect($snapshot['schools'] ?? [])->map(function (array $existing) use (
            $school,
            $schoolName,
            $normalizedSchoolName,
            $businessId,
            $result,
            $resolvedSchoolCoaches,
            $associatedCount,
            $nameOnlyCount,
            $crossReferencedCount
        ): array {
            $targetIds = collect([$school['id'] ?? null, $school['business_id'] ?? null, $businessId])
                ->map(fn ($value): string => strtolower(trim((string) $value)))
                ->filter()
                ->values();
            $existingIds = collect([$existing['id'] ?? null, $existing['business_id'] ?? null, $existing['company_id'] ?? null])
                ->map(fn ($value): string => strtolower(trim((string) $value)))
                ->filter()
                ->values();

            $existingNameKey = $this->normalizeSchoolMatchKey((string) ($existing['name'] ?? $existing['school_name'] ?? $existing['company_name'] ?? ''));
            $matchesById = $targetIds->intersect($existingIds)->isNotEmpty();
            $matchesByName = $normalizedSchoolName !== '' && $existingNameKey === $normalizedSchoolName;

            if (! $matchesById && ! $matchesByName) {
                return $existing;
            }

            $logoUrl = collect($resolvedSchoolCoaches)
                ->map(fn (array $coach): ?string => $coach['school_logo_url'] ?? $coach['business_logo_url'] ?? $coach['logo_url'] ?? null)
                ->filter(fn (?string $url): bool => filled($url))
                ->first();

            $firstCoach = collect($resolvedSchoolCoaches)->first(fn ($coach): bool => is_array($coach)) ?: [];

            $existing['coaches_loaded'] = true;
            $existing['coaches_loaded_from_business'] = $businessId !== '' && (bool) ($result['success'] ?? false);
            $existing['coaches_loaded_from'] = $nameOnlyCount > 0
                ? 'ghl_business_contacts+company_name_cross_reference'
                : ($businessId !== '' ? 'ghl_business_contacts' : 'company_name_cross_reference');
            $existing['coach_count_loaded'] = true;
            $existing['coach_count_associated'] = $associatedCount;
            $existing['coach_count_name_only'] = $nameOnlyCount;
            $existing['coach_count_cross_referenced'] = $crossReferencedCount;
            $existing['coach_count_source'] = $nameOnlyCount > 0
                ? 'ghl_business_plus_company_name_cross_reference'
                : ($businessId !== '' ? 'ghl_business_contacts' : 'company_name_cross_reference');
            $existing['coach_count'] = max((int) ($existing['coach_count'] ?? 0), $crossReferencedCount);
            $existing['coaches_count'] = $existing['coach_count'];
            $existing['logo_url'] = $existing['logo_url'] ?? $logoUrl;
            $existing['school_logo_url'] = $existing['school_logo_url'] ?? $logoUrl;
            $existing['business_logo_url'] = $existing['business_logo_url'] ?? $logoUrl;
            $existing['conference'] = $existing['conference'] ?? ($firstCoach['conference'] ?? null);
            $existing['division'] = $existing['division'] ?? ($firstCoach['division'] ?? null);

            if (! ($result['success'] ?? true) && empty($resolvedSchoolCoaches)) {
                $existing['coaches_load_failed'] = true;
                $existing['coaches_load_error'] = $result['error'] ?? 'Unable to load coaches for this school.';
            } else {
                unset($existing['coaches_load_failed'], $existing['coaches_load_error']);
            }

            return $existing;
        })->values()->all();

        return $school;
    }

    /**
     * Backward-compatible default refresh action.
     *
     * The header reload button now exposes two actions:
     * - refreshStatsOnly(): lightweight one-pass Recruiting Center stats sync
     * - refreshCoachDatabase(): full Coach Database dataset reload
     *
     * Keep refreshData() as the default/stats action so older buttons, keyboard
     * shortcuts, or links still perform the safer lightweight refresh.
     */
    public function refreshData(bool $notify = true, string $message = 'Syncing recruiting stats from Recruiting Center.'): void
    {
        $this->refreshStatsOnly($notify, $message);
    }

    public function refreshStatsOnly(bool $notify = true, string $message = 'Syncing recruiting stats from Recruiting Center.'): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $this->error = null;
        $syncStatus = $this->startRecruitingStatsSyncInBackground($user, 'stats_sync');
        $this->refreshRecruitingSyncStatus($syncStatus);

        $snapshot = Cache::get($this->activeCacheKey(), $this->emptySnapshot());
        if (is_array($snapshot)) {
            $this->hydrateFromSnapshot($snapshot);
        }

        $this->isLoadingDataset = false;
        $this->hasMoreData = (bool) ($snapshot['has_more_data'] ?? false);

        if ($notify) {
            Notification::make()
                ->title('Recruiting Center')
                ->body('Stats sync started in the background. The dashboard will use the latest cached stats now; refresh again in a moment to see the newly exported Recruiting Center values.')
                ->success()
                ->send();
        }
    }

    protected function recruitingStatsSyncLockKey($user): string
    {
        return 'recruiting:stats-sync-running:' . ($user?->id ?? 'guest');
    }

    protected function recruitingStatsSyncStatusKey($user): string
    {
        return 'recruiting:stats-sync-status:' . ($user?->id ?? 'guest');
    }

    protected function recruitingSyncModeLabel(?string $mode): string
    {
        return match ($mode) {
            'full_database_reload' => 'Full Coach Database reload',
            'livewire_dataset_load' => 'Coach Database dataset load',
            'stats_sync' => 'Recruiting stats sync',
            default => 'Recruiting Center sync',
        };
    }

    public function refreshRecruitingSyncStatus(?array $statusOverride = null): void
    {
        $user = Auth::user();
        if (! $user) {
            $this->isRecruitingSyncRunning = false;
            $this->recruitingSyncStatus = null;
            $this->recruitingSyncMode = null;
            $this->recruitingSyncStartedAt = null;
            $this->recruitingSyncFinishedAt = null;
            $this->recruitingSyncMessage = null;
            return;
        }

        $statusKey = $this->recruitingStatsSyncStatusKey($user);
        $lockKey = $this->recruitingStatsSyncLockKey($user);
        $status = is_array($statusOverride) && ! empty($statusOverride)
            ? $statusOverride
            : Cache::get($statusKey, []);
        $status = is_array($status) ? $status : [];
        $lockStartedAt = Cache::get($lockKey);
        $rawStatus = strtolower((string) ($status['status'] ?? ''));

        $activeStatuses = ['queued', 'starting', 'waiting_for_worker', 'running', 'already_running', 'stalled'];

        if (in_array($rawStatus, ['running', 'already_running'], true) && ! $lockStartedAt) {
            // The background worker releases the lock after finishing. If the status still
            // says running but the lock is gone, stop the visible loader instead of leaving
            // the page permanently busy.
            $status['status'] = 'completed';
            $status['finished_at'] = $status['finished_at'] ?? now()->toDateTimeString();
            $status['message'] = $status['message'] ?? 'Recruiting sync completed. Latest cached rows are available.';
            Cache::put($statusKey, $status, now()->addHours(1));
            $rawStatus = 'completed';
        }

        $heartbeatAt = $status['worker_heartbeat_at'] ?? null;
        $startedAt = $status['launch_attempted_at'] ?? $status['queued_at'] ?? $status['started_at'] ?? null;
        $startGrace = max(15, (int) config('coach-database-sync.background.worker_start_grace_seconds', 45));
        $staleAfter = max(60, (int) config('coach-database-sync.background.worker_stale_seconds', 180));
        $startedAge = $startedAt ? max(0, time() - (strtotime((string) $startedAt) ?: time())) : 0;
        $heartbeatAge = $heartbeatAt ? max(0, time() - (strtotime((string) $heartbeatAt) ?: time())) : null;

        if (in_array($rawStatus, ['queued', 'starting'], true) && ! $heartbeatAt && $startedAge >= $startGrace) {
            $rawStatus = 'waiting_for_worker';
            $status['status'] = $rawStatus;
            $status['waiting_since'] = $status['waiting_since'] ?? now()->toDateTimeString();
            $status['message'] = 'The reload is queued and waiting for the server background worker. Existing Coach Database rows remain available.';
            Cache::put($statusKey, $status, now()->addHours(6));
        } elseif ($rawStatus === 'running' && $heartbeatAge !== null && $heartbeatAge >= $staleAfter) {
            $rawStatus = 'stalled';
            $status['status'] = $rawStatus;
            $status['message'] = 'The background worker stopped reporting progress. Existing Coach Database rows remain available while the server worker is checked.';
            Cache::put($statusKey, $status, now()->addHours(6));
        }

        $this->isRecruitingSyncRunning = (bool) $lockStartedAt && in_array($rawStatus, $activeStatuses, true);
        $this->recruitingSyncStatus = $rawStatus !== '' ? $rawStatus : null;
        $this->recruitingSyncMode = $status['mode'] ?? null;
        $this->recruitingSyncStartedAt = $status['started_at'] ?? (is_string($lockStartedAt) ? $lockStartedAt : null);
        $this->recruitingSyncFinishedAt = $status['finished_at'] ?? null;
        $this->recruitingSyncMessage = $status['message']
            ?? ($this->isRecruitingSyncRunning
                ? $this->recruitingSyncModeLabel($this->recruitingSyncMode) . ' is running. Existing schools/coaches stay visible while the background service works.'
                : null);
    }

    public function getRecruitingReloadStatusProperty(): array
    {
        $this->refreshRecruitingSyncStatus();

        $user = Auth::user();
        $status = $user ? Cache::get($this->recruitingStatsSyncStatusKey($user), []) : [];
        $status = is_array($status) ? $status : [];
        $snapshot = Cache::get($this->activeCacheKey(), $this->emptySnapshot());
        $snapshot = is_array($snapshot) ? $snapshot : [];

        $rawStatus = strtolower((string) ($status['status'] ?? $this->recruitingSyncStatus ?? ''));
        $loadedSchools = (int) ($status['loaded_schools'] ?? $snapshot['loaded_schools_count'] ?? $this->loadedSchoolsCount ?? 0);
        $loadedContacts = (int) ($status['loaded_contacts'] ?? $snapshot['loaded_contacts_count'] ?? $this->loadedContactsCount ?? 0);
        $loadedPages = (int) ($status['loaded_pages'] ?? $snapshot['loaded_pages'] ?? $this->loadedPages ?? 0);
        $remoteSchools = (int) ($status['remote_total_schools'] ?? $snapshot['remote_total_schools'] ?? $this->remoteTotalSchools ?? 0);
        $remoteContacts = (int) ($status['remote_total_contacts'] ?? $snapshot['remote_total_contacts'] ?? 0);
        $progress = (int) ($status['progress'] ?? 0);

        if ($progress <= 0 && ($loadedSchools > 0 || $loadedContacts > 0)) {
            $remoteTotal = $remoteSchools + $remoteContacts;
            if ($remoteTotal > 0) {
                $progress = min(99, (int) round((($loadedSchools + $loadedContacts) / max(1, $remoteTotal)) * 100));
            } elseif ($loadedPages > 0) {
                $progress = min(94, max(2, 2 + ($loadedPages * 2)));
            }
        }

        if (! $this->isRecruitingSyncRunning && in_array($rawStatus, ['completed', 'cleared'], true)) {
            $progress = 100;
        }

        $heartbeatAt = $status['worker_heartbeat_at'] ?? null;
        $startedAt = $status['started_at'] ?? $status['queued_at'] ?? $status['launch_attempted_at'] ?? $this->recruitingSyncStartedAt;
        $finishedAt = $status['finished_at'] ?? $status['failed_at'] ?? $this->recruitingSyncFinishedAt;
        $heartbeatTimestamp = $heartbeatAt ? strtotime((string) $heartbeatAt) : false;
        $startedTimestamp = $startedAt ? strtotime((string) $startedAt) : false;
        $heartbeatAge = $heartbeatTimestamp ? max(0, time() - $heartbeatTimestamp) : null;
        $elapsedSeconds = $startedTimestamp ? max(0, time() - $startedTimestamp) : null;

        $formatAge = static function (?int $seconds): ?string {
            if ($seconds === null) {
                return null;
            }
            if ($seconds < 5) {
                return 'just now';
            }
            if ($seconds < 60) {
                return $seconds . 's ago';
            }
            if ($seconds < 3600) {
                return intdiv($seconds, 60) . 'm ago';
            }
            return intdiv($seconds, 3600) . 'h ago';
        };

        $formatDuration = static function (?int $seconds): ?string {
            if ($seconds === null) {
                return null;
            }
            if ($seconds < 60) {
                return $seconds . 's';
            }
            if ($seconds < 3600) {
                return intdiv($seconds, 60) . 'm ' . ($seconds % 60) . 's';
            }
            return intdiv($seconds, 3600) . 'h ' . intdiv($seconds % 3600, 60) . 'm';
        };

        $statusLabel = match ($rawStatus) {
            'queued' => 'Queued',
            'starting' => 'Starting worker',
            'waiting_for_worker' => 'Waiting for worker',
            'running', 'already_running' => 'Syncing now',
            'stalled' => 'Worker stalled',
            'failed', 'failed_to_start' => 'Sync failed',
            'completed' => 'Completed',
            'cleared' => 'Status cleared',
            default => 'Checking status',
        };

        $stage = match ($rawStatus) {
            'queued' => 'The reload request has been accepted.',
            'starting' => 'The server is starting the background worker.',
            'waiting_for_worker' => 'The request is waiting for the configured queue worker or scheduled task.',
            'running', 'already_running' => $loadedPages > 0
                ? "Processing API page {$loadedPages}."
                : 'The background worker has checked in and is preparing the first page.',
            'stalled' => 'The worker started but its heartbeat is no longer current.',
            'failed', 'failed_to_start' => 'The previous cached database remains available.',
            'completed' => 'The refreshed database is ready.',
            default => 'Reading the latest background status.',
        };

        $tone = match ($rawStatus) {
            'running', 'already_running', 'completed' => 'success',
            'queued', 'starting' => 'info',
            'waiting_for_worker' => 'warning',
            'stalled', 'failed', 'failed_to_start' => 'danger',
            default => 'neutral',
        };

        $launchDriver = strtolower((string) ($status['launch_driver'] ?? ''));
        $launchDriverLabel = match ($launchDriver) {
            'queue' => 'Queue worker',
            'scheduler' => 'Scheduled worker',
            'detached_shell' => 'Background process',
            'web_tick', 'incremental_livewire' => 'Incremental background worker',
            default => $launchDriver !== '' ? str_replace('_', ' ', ucfirst($launchDriver)) : 'Automatic',
        };

        $workerHint = null;
        if ($rawStatus === 'waiting_for_worker') {
            $workerHint = match ($launchDriver) {
                'queue' => 'No queue worker heartbeat has been received yet. The server queue worker must be running for progress to begin.',
                'scheduler' => 'The scheduled worker has not picked up the request yet. Check the server cron or scheduler entry.',
                'detached_shell' => 'The detached PHP process did not check in. Automatic mode will use compatibility processing instead.',
                'web_tick', 'incremental_livewire' => 'Small checkpointed pages are loading in the background. Keep any Recruiting Center tab open until the reload completes.',
                default => 'The server has not started a worker yet. Run the sync doctor to confirm the production worker configuration.',
            };
        } elseif ($rawStatus === 'stalled') {
            $workerHint = 'The worker stopped reporting progress. Check its process/log before starting another reload.';
        } elseif (in_array($rawStatus, ['failed', 'failed_to_start'], true)) {
            $workerHint = (string) ($status['error'] ?? $status['launch_error'] ?? 'Check the application log for the background-sync error.');
        }

        $activeStatuses = ['queued', 'starting', 'waiting_for_worker', 'running', 'already_running', 'stalled'];
        $problemStatuses = ['failed', 'failed_to_start'];
        $visible = in_array($rawStatus, array_merge($activeStatuses, $problemStatuses), true);
        $workerConfirmed = filled($heartbeatAt);
        $indeterminate = in_array($rawStatus, ['queued', 'starting', 'waiting_for_worker'], true)
            && $loadedPages === 0
            && ! $workerConfirmed;

        return [
            'visible' => $visible,
            'active' => in_array($rawStatus, $activeStatuses, true),
            'status' => $rawStatus !== '' ? $rawStatus : null,
            'status_label' => $statusLabel,
            'tone' => $tone,
            'stage' => $stage,
            'mode' => $this->recruitingSyncMode,
            'title' => $this->recruitingSyncModeLabel($this->recruitingSyncMode),
            'message' => $this->recruitingSyncMessage ?: ($snapshot['last_refresh_notice'] ?? 'Coach Database is syncing in the background.'),
            'percent' => max(1, min(100, $progress > 0 ? $progress : 1)),
            'indeterminate' => $indeterminate,
            'loaded_schools' => $loadedSchools,
            'loaded_contacts' => $loadedContacts,
            'loaded_pages' => $loadedPages,
            'remote_total_schools' => $remoteSchools,
            'remote_total_contacts' => $remoteContacts,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'elapsed_label' => $formatDuration($elapsedSeconds),
            'heartbeat_at' => $heartbeatAt,
            'heartbeat_age_seconds' => $heartbeatAge,
            'heartbeat_label' => $workerConfirmed ? $formatAge($heartbeatAge) : 'No heartbeat yet',
            'worker_confirmed' => $workerConfirmed,
            'worker_host' => $status['worker_host'] ?? null,
            'launch_driver' => $launchDriver,
            'launch_driver_label' => $launchDriverLabel,
            'worker_hint' => $workerHint,
            'can_clear' => in_array($rawStatus, ['stalled', 'failed', 'failed_to_start'], true),
        ];
    }

    public function clearStuckRecruitingSync(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        Cache::forget($this->recruitingStatsSyncLockKey($user));
        try {
            app(CoachDatabaseWebFallbackSyncService::class)->cancel($user);
        } catch (\Throwable $exception) {
            Log::debug('Unable to remove compatibility-mode checkpoint during status clear.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
        $status = [
            'status' => 'cleared',
            'mode' => $this->recruitingSyncMode ?: 'manual_clear',
            'user_id' => $user->id,
            'progress' => 100,
            'finished_at' => now()->toDateTimeString(),
            'message' => 'The stale background-sync lock was cleared. Existing cached data was not deleted.',
        ];
        Cache::put($this->recruitingStatsSyncStatusKey($user), $status, now()->addMinutes(120));
        $this->refreshRecruitingSyncStatus($status);
        $this->isLoadingDataset = false;
        $this->hasMoreData = false;

        Notification::make()
            ->title('Recruiting Center')
            ->body('Stale sync status cleared. You can start the full background reload again.')
            ->success()
            ->send();
    }

    protected function startRecruitingStatsSyncInBackground($user, string $mode = 'stats_sync'): array
    {
        $lockKey = $this->recruitingStatsSyncLockKey($user);
        $statusKey = $this->recruitingStatsSyncStatusKey($user);
        $modeLabel = $this->recruitingSyncModeLabel($mode);

        if (! Cache::add($lockKey, now()->toDateTimeString(), now()->addHours(3))) {
            $existing = Cache::get($statusKey, []);
            $status = array_merge(is_array($existing) ? $existing : [], [
                'status' => $existing['status'] ?? 'already_running',
                'mode' => $existing['mode'] ?? $mode,
                'started_at' => $existing['started_at'] ?? Cache::get($lockKey),
                'user_id' => $user->id,
                'message' => $existing['message'] ?? ($modeLabel . ' is already running. Existing cached rows remain available.'),
            ]);
            Cache::put($statusKey, $status, now()->addHours(6));
            return $status;
        }

        $status = [
            'status' => 'queued',
            'mode' => $mode,
            'progress' => 1,
            'loaded_schools' => 0,
            'loaded_contacts' => 0,
            'loaded_pages' => 0,
            'started_at' => now()->toDateTimeString(),
            'user_id' => $user->id,
            'message' => $modeLabel . ' is queued for background processing. Existing Coach Database rows remain visible.',
        ];
        Cache::put($statusKey, $status, now()->addHours(6));

        if ($mode === 'full_database_reload') {
            try {
                return app(CoachDatabaseBackgroundSyncLauncher::class)->launchDataset($user, $status);
            } catch (\Throwable $exception) {
                Cache::forget($lockKey);
                $status = [
                    'status' => 'failed_to_start',
                    'mode' => $mode,
                    'error' => $exception->getMessage(),
                    'user_id' => $user->id,
                    'failed_at' => now()->toDateTimeString(),
                    'message' => 'Unable to queue the full Coach Database reload: ' . $exception->getMessage(),
                ];
                Cache::put($statusKey, $status, now()->addHours(6));
                Log::warning('Unable to queue Coach Database background reload.', [
                    'user_id' => $user->id,
                    'error' => $exception->getMessage(),
                ]);
                return $status;
            }
        }

        // Keep the existing lightweight stats command compatible. The full database reload
        // above uses the production-safe queue/scheduler launcher instead of trusting nohup.
        $php = (new PhpExecutableFinder())->find(false) ?: PHP_BINARY;
        $artisan = base_path('artisan');
        $logPath = storage_path('logs/recruiting-stats-sync-' . $user->id . '.log');
        $arguments = ' --user=' . (int) $user->id . ' --force --release-lock';

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $command = 'start /B "" ' . escapeshellarg($php) . ' ' . escapeshellarg($artisan)
                    . ' recruiting:sync-stats' . $arguments . ' > ' . escapeshellarg($logPath) . ' 2>&1';
                pclose(popen($command, 'r'));
            } else {
                $command = 'nohup ' . escapeshellarg($php) . ' ' . escapeshellarg($artisan)
                    . ' recruiting:sync-stats' . $arguments . ' > ' . escapeshellarg($logPath) . ' 2>&1 < /dev/null &';
                Process::fromShellCommandline($command, base_path())->setTimeout(10)->run();
            }

            $status['status'] = 'starting';
            $status['launch_driver'] = 'detached_shell';
            $status['launch_attempted_at'] = now()->toDateTimeString();
            $status['worker_log'] = $logPath;
            Cache::put($statusKey, $status, now()->addHours(6));
        } catch (\Throwable $exception) {
            Cache::forget($lockKey);
            $status = [
                'status' => 'failed_to_start',
                'mode' => $mode,
                'error' => $exception->getMessage(),
                'user_id' => $user->id,
                'failed_at' => now()->toDateTimeString(),
                'message' => 'Unable to start ' . strtolower($modeLabel) . ': ' . $exception->getMessage(),
            ];
            Cache::put($statusKey, $status, now()->addHours(6));

            Log::warning('Unable to start Recruiting Center background stats sync.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return $status;
    }

    public function refreshCoachDatabase(bool $notify = true): void
    {
        if (! $this->allowed || $this->locked) {
            return;
        }

        $user = Auth::user();
        if (! $user) {
            return;
        }

        $this->refreshRecruitingSyncStatus();
        if ($this->isRecruitingSyncRunning) {
            if ($notify) {
                Notification::make()
                    ->title('Recruiting Center')
                    ->body($this->recruitingSyncMessage ?: 'A Recruiting Center sync is already running. Existing cached data remains available.')
                    ->warning()
                    ->send();
            }
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
        $this->hasMoreData = false;

        $status = $this->startRecruitingStatsSyncInBackground($user, 'full_database_reload');
        $this->refreshRecruitingSyncStatus($status);
        $this->isLoadingDataset = in_array(strtolower((string) ($status['status'] ?? '')), ['queued', 'starting', 'waiting_for_worker', 'running', 'already_running', 'stalled'], true);

        if ($notify) {
            $launchStatus = strtolower((string) ($status['status'] ?? 'queued'));
            $launchDriver = strtolower((string) ($status['launch_driver'] ?? ''));
            $body = match ($launchStatus) {
                'running', 'already_running' => in_array($launchDriver, ['web_tick', 'incremental_livewire'], true)
                    ? 'Background processing started. Keep a Recruiting Center tab open while small checkpointed pages load safely.'
                    : 'The background worker is running. Live progress will appear at the top of the page.',
                'starting' => 'The server is starting the background worker. The progress monitor will confirm when its first heartbeat arrives.',
                'waiting_for_worker' => $launchDriver === 'scheduler'
                    ? 'The reload is queued for the scheduled server worker. The progress monitor will show when it is picked up.'
                    : 'The reload is waiting for the configured server worker. The progress monitor will identify whether a worker heartbeat is received.',
                'failed', 'failed_to_start' => 'The reload could not be started. The existing cached database was preserved.',
                default => 'The reload request is queued. Live worker status, page counts, and heartbeat details will appear at the top of the page.',
            };

            $notification = Notification::make()
                ->title('Coach Database reload')
                ->body($body);

            if (in_array($launchStatus, ['failed', 'failed_to_start'], true)) {
                $notification->danger();
            } elseif ($launchStatus === 'waiting_for_worker') {
                $notification->warning();
            } else {
                $notification->info();
            }

            $notification->send();
        }
    }

    public function loadMoreSchools(): void
    {
        $cap = max(24, (int) config('coach-database-sync.ui.school_row_cap', 96));
        $this->schoolDisplayLimit = min($cap, $this->schoolDisplayLimit + 24);
    }

    public function loadMoreCoaches(): void
    {
        $cap = max(40, (int) config('coach-database-sync.ui.coach_row_cap', 120));
        $this->coachDisplayLimit = min($cap, $this->coachDisplayLimit + 40);
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
            ->body('List removed. Existing Recruiting Center contact tags are left untouched.')
            ->success()
            ->send();
    }

    public function selectSchoolById(string $schoolId): void
    {
        $schoolId = trim($schoolId);
        if ($schoolId === '') {
            return;
        }

        // Keep the drawer open action instant. Do not call Recruiting Center or rebuild the
        // whole snapshot inside the Livewire click request. The selectedSchool
        // computed property hydrates coaches from the already cached dashboard /
        // discover coach indexes, which is the same data source that already
        // works on the dashboard school cards.
        $this->selectedSchoolId = $schoolId;
    }

    public function openSchoolDashboardModal(string $schoolId): void
    {
        $schoolId = trim($schoolId);
        if ($schoolId === '') {
            return;
        }

        // Drawer opens are cache-only. The complete roster is reconciled during the
        // detached dataset sync, so a click never calls Recruiting Center or rebuilds the full
        // snapshot inside a Livewire request.
        $school = collect($this->allSchools())->first(function (array $item) use ($schoolId): bool {
            $nameHash = md5(strtolower(trim((string) ($item['name'] ?? ''))));
            return (string) ($item['id'] ?? '') === $schoolId
                || (string) ($item['business_id'] ?? '') === $schoolId
                || $nameHash === $schoolId
                || strcasecmp(trim((string) ($item['name'] ?? '')), $schoolId) === 0;
        });

        $this->selectedSchoolId = is_array($school)
            ? (string) ($school['id'] ?? $school['business_id'] ?? $schoolId)
            : $schoolId;
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

    /**
     * Close only the school that initiated the close request. This prevents an
     * older delayed close response from dismissing a different school that the
     * user opened while background favorite/list work was still finishing.
     */
    public function closeSchoolIfSelected(string $schoolId): void
    {
        $schoolId = trim($schoolId);

        if ($schoolId === '' || (string) $this->selectedSchoolId === $schoolId) {
            $this->selectedSchoolId = null;
        }
    }

    public function loadSchoolCoachesById(string $schoolId): void
    {
        // Intentionally no-op for UI clicks. The old implementation called the
        // slow Recruiting Center /contacts/business endpoint and rebuilt the snapshot during
        // the Livewire request, which left the drawer stuck on the loading
        // overlay. Coaches are resolved from the cached coach index in
        // getSelectedSchoolProperty().
        $this->selectedSchoolId = trim($schoolId) !== '' ? trim($schoolId) : $this->selectedSchoolId;
    }


    public function saveSchoolById(string $schoolId): void { $this->runSchoolContactTagAction($schoolId, app(CoachDatabaseService::class)->savedSchoolTag(), 'add'); }
    public function unsaveSchoolById(string $schoolId): void { $this->runSchoolContactTagAction($schoolId, app(CoachDatabaseService::class)->savedSchoolTag(), 'remove'); }
    public function favoriteSchoolById(string $schoolId): void { $this->runSchoolContactTagAction($schoolId, app(CoachDatabaseService::class)->favoriteSchoolTag(), 'add'); }
    public function unfavoriteSchoolById(string $schoolId): void { $this->runSchoolContactTagAction($schoolId, app(CoachDatabaseService::class)->favoriteSchoolTag(), 'remove'); }

    /**
     * Queue a favorite change without morphing the active Livewire component.
     * This prevents an older favorite response from replacing a newer school
     * drawer that the user opened while the background action was finishing.
     */
    public function queueSchoolFavoriteState(string $schoolId, bool $favorite): array
    {
        if (method_exists($this, 'skipRender')) {
            $this->skipRender();
        }

        $user = Auth::user();
        $schoolId = trim($schoolId);

        if (! $user || $schoolId === '' || ! $this->allowed || $this->locked) {
            return ['success' => false, 'error' => 'This action is not available.'];
        }

        $contactIds = $this->contactIdsForSchool($schoolId);
        if (empty($contactIds)) {
            return ['success' => false, 'error' => 'No coaches were found for this school.'];
        }

        $tag = app(CoachDatabaseService::class)->favoriteSchoolTag();
        $type = $favorite ? 'add' : 'remove';

        $this->applyTagToCachedContacts($contactIds, $tag, $type, false);

        $queued = app(CoachDatabaseActionQueueService::class)->enqueue($user, [[
            'school_id' => $schoolId,
            'contact_ids' => $contactIds,
            'tag' => $tag,
            'type' => $type,
        ]]);

        if (! ($queued['success'] ?? false)) {
            // Revert the optimistic cache mutation when the queue itself failed.
            $this->applyTagToCachedContacts($contactIds, $tag, $favorite ? 'remove' : 'add', false);
            return ['success' => false, 'error' => $queued['error'] ?? 'Unable to queue the favorite change.'];
        }

        $this->startCoachDatabaseActionWorker($user);

        return [
            'success' => true,
            'favorite' => $favorite,
            'queued' => (int) ($queued['queued'] ?? 1),
        ];
    }

    public function pollCoachDatabaseActionStatus(): array
    {
        if (method_exists($this, 'skipRender')) {
            $this->skipRender();
        }

        $user = Auth::user();
        if (! $user) {
            return ['status' => 'idle'];
        }

        $status = Cache::get(CoachDatabaseActionQueueService::statusKey($user), []);
        $status = is_array($status) ? $status : ['status' => 'idle'];
        $state = strtolower(trim((string) ($status['status'] ?? 'idle')));

        if (in_array($state, ['completed', 'completed_with_errors'], true)) {
            $completedAt = trim((string) ($status['completed_at'] ?? $status['updated_at'] ?? ''));
            $notificationToken = sha1(implode('|', [
                (string) $user->id,
                $state,
                $completedAt,
                (string) ($status['processed'] ?? 0),
                (string) ($status['failed'] ?? 0),
            ]));

            $notificationKey = CoachDatabaseActionQueueService::statusKey($user) . ':filament-notification:' . $notificationToken;
            if (Cache::add($notificationKey, true, now()->addDay())) {
                $processed = (int) ($status['processed'] ?? 0);
                $failed = (int) ($status['failed'] ?? 0);

                $notification = Notification::make()
                    ->title($state === 'completed_with_errors' ? 'Update finished with issues' : 'Update complete')
                    ->body($state === 'completed_with_errors'
                        ? number_format($processed) . ' background update(s) completed and ' . number_format($failed) . ' failed.'
                        : number_format($processed) . ' background update(s) completed successfully.');

                if ($state === 'completed_with_errors') {
                    $notification->warning();
                } else {
                    $notification->success();
                }

                $notification->send();
            }
        }

        return $status;
    }

    public function notifyRecruitingUi(string $message, string $type = 'success', ?string $title = null): array
    {
        if (method_exists($this, 'skipRender')) {
            $this->skipRender();
        }

        $message = trim($message);
        if ($message === '') {
            return ['success' => false];
        }

        $notification = Notification::make()
            ->title(trim((string) $title) !== '' ? trim((string) $title) : 'Recruiting Center')
            ->body($message);

        match (strtolower(trim($type))) {
            'danger', 'error' => $notification->danger(),
            'warning', 'warn' => $notification->warning(),
            'info' => $notification->info(),
            default => $notification->success(),
        };

        $notification->send();

        return ['success' => true];
    }
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

    /**
     * Optimistically apply several list checkbox changes in one lightweight
     * Livewire request, then send the remote contact updates in a detached
     * worker. This keeps the drawer interactive during stress/rapid clicking.
     */
    public function queueSchoolListMemberships(string $schoolId, array $memberships): array
    {
        if (method_exists($this, 'skipRender')) {
            $this->skipRender();
        }

        $user = Auth::user();
        $schoolId = trim($schoolId);

        if (! $user || $schoolId === '' || ! $this->allowed || $this->locked) {
            return ['success' => false, 'error' => 'This action is not available.'];
        }

        $desired = collect($memberships)
            ->mapWithKeys(function ($value, $key): array {
                $listKey = trim((string) $key);
                return $listKey === '' ? [] : [$listKey => filter_var($value, FILTER_VALIDATE_BOOL)];
            })
            ->all();

        if (empty($desired)) {
            return ['success' => true, 'queued' => 0, 'states' => []];
        }

        $contactIds = $this->contactIdsForSchool($schoolId);
        if (empty($contactIds)) {
            return ['success' => false, 'error' => 'No coaches were found for this school.'];
        }

        $actions = [];
        $resolvedStates = [];
        $service = app(CoachDatabaseService::class);

        foreach ($desired as $listKey => $inList) {
            $tag = $service->listTagForKey($listKey, $user);
            if (! $tag) {
                continue;
            }

            $resolvedStates[$listKey] = (bool) $inList;
            $actions[] = [
                'school_id' => $schoolId,
                'list_key' => $listKey,
                'contact_ids' => $contactIds,
                'tag' => $tag,
                'type' => $inList ? 'add' : 'remove',
            ];
        }

        if (empty($actions)) {
            return ['success' => false, 'error' => 'No valid list changes were found.'];
        }

        $this->applySchoolListMembershipsToCache($schoolId, $contactIds, $actions, false);
        $queued = app(CoachDatabaseActionQueueService::class)->enqueue($user, $actions);

        if (! ($queued['success'] ?? false)) {
            return ['success' => false, 'error' => $queued['error'] ?? 'Unable to queue list changes.'];
        }

        $this->startCoachDatabaseActionWorker($user);

        return [
            'success' => true,
            'queued' => (int) ($queued['queued'] ?? count($actions)),
            'states' => $resolvedStates,
        ];
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


    protected function applySchoolListMembershipsToCache(string $schoolId, array $contactIds, array $actions, bool $hydrateComponent = true): void
    {
        $selectedSchoolId = $this->selectedSchoolId;
        $snapshot = Cache::get($this->activeCacheKey(), $this->emptySnapshot());
        $contactLookup = array_flip(collect($contactIds)->map(fn ($id): string => trim((string) $id))->filter()->unique()->all());

        $changes = collect($actions)
            ->filter(fn ($action): bool => is_array($action) && filled($action['tag'] ?? null) && filled($action['list_key'] ?? null))
            ->mapWithKeys(function (array $action): array {
                $listKey = trim((string) $action['list_key']);
                return [$listKey => [
                    'tag' => trim((string) $action['tag']),
                    'in_list' => strtolower((string) ($action['type'] ?? 'add')) !== 'remove',
                ]];
            });

        $snapshot['coaches'] = collect($snapshot['coaches'] ?? [])
            ->filter(fn ($coach): bool => is_array($coach))
            ->map(function (array $coach) use ($contactLookup, $changes): array {
                $coachId = trim((string) ($coach['id'] ?? $coach['contact_id'] ?? ''));
                if ($coachId === '' || ! isset($contactLookup[$coachId])) {
                    return $coach;
                }

                $tags = collect($coach['tags'] ?? [])
                    ->map(fn ($tag): string => trim((string) $tag))
                    ->filter()
                    ->unique(fn (string $tag): string => strtolower($tag))
                    ->values();

                foreach ($changes as $change) {
                    $tag = (string) ($change['tag'] ?? '');
                    $lowerTag = strtolower($tag);
                    if ((bool) ($change['in_list'] ?? false)) {
                        if (! $tags->contains(fn (string $existing): bool => strtolower($existing) === $lowerTag)) {
                            $tags->push($tag);
                        }
                    } else {
                        $tags = $tags->reject(fn (string $existing): bool => strtolower($existing) === $lowerTag)->values();
                    }
                }

                $coach['tags'] = $tags->values()->all();
                return $coach;
            })
            ->values()
            ->all();

        $snapshot['schools'] = collect($snapshot['schools'] ?? [])
            ->filter(fn ($school): bool => is_array($school))
            ->map(function (array $school) use ($schoolId, $changes): array {
                $id = trim((string) ($school['id'] ?? ''));
                $businessId = trim((string) ($school['business_id'] ?? $school['company_id'] ?? ''));
                if ($id !== $schoolId && $businessId !== $schoolId) {
                    return $school;
                }

                $listKeys = collect($school['list_keys'] ?? [])
                    ->merge($school['lists'] ?? [])
                    ->map(fn ($key): string => trim((string) $key))
                    ->filter()
                    ->unique()
                    ->values();

                foreach ($changes as $listKey => $change) {
                    if ((bool) ($change['in_list'] ?? false)) {
                        if (! $listKeys->contains($listKey)) {
                            $listKeys->push($listKey);
                        }
                    } else {
                        $listKeys = $listKeys->reject(fn (string $key): bool => $key === $listKey)->values();
                    }
                }

                $school['list_keys'] = $listKeys->values()->all();
                $school['lists'] = $school['list_keys'];
                return $school;
            })
            ->values()
            ->all();

        $schools = collect($snapshot['schools'] ?? []);
        $snapshot['lists'] = collect($snapshot['lists'] ?? [])
            ->map(function ($row) use ($schools, $changes) {
                if (! is_array($row)) {
                    return $row;
                }

                $rowKey = trim((string) ($row['key'] ?? ''));
                if (! $changes->has($rowKey)) {
                    return $row;
                }

                $items = $schools
                    ->filter(fn (array $school): bool => in_array($rowKey, $school['list_keys'] ?? [], true))
                    ->values();

                $row['schools_count'] = $items->count();
                $row['coaches_count'] = $items->sum(fn (array $school): int => (int) ($school['coach_count'] ?? $school['coaches_count'] ?? 0));
                $row['schools'] = $items
                    ->map(fn (array $school): array => [
                        'id' => $school['id'] ?? $school['business_id'] ?? null,
                        'name' => $school['name'] ?? null,
                        'logo_url' => $school['logo_url'] ?? $school['school_logo_url'] ?? $school['business_logo_url'] ?? null,
                        'conference' => $school['conference'] ?? null,
                        'division' => $school['division'] ?? null,
                        'coach_count' => (int) ($school['coach_count'] ?? $school['coaches_count'] ?? 0),
                    ])
                    ->all();

                return $row;
            })
            ->values()
            ->all();

        $snapshot['tag_synced_at'] = now()->toDateTimeString();
        $this->storeSnapshot($snapshot);

        if ($hydrateComponent) {
            $this->hydrateFromSnapshot($snapshot);
            $this->selectedSchoolId = $selectedSchoolId ?: $schoolId;
            $this->dispatch('rc-school-list-cache-updated', schoolId: $schoolId);
        }
    }

    protected function startCoachDatabaseActionWorker($user): void
    {
        $launchKey = CoachDatabaseActionQueueService::launchKey($user);
        if (! Cache::add($launchKey, true, now()->addMinutes(10))) {
            return;
        }

        $php = (new PhpExecutableFinder())->find(false) ?: PHP_BINARY;
        $artisan = base_path('artisan');
        $logPath = storage_path('logs/recruiting-actions-' . $user->id . '.log');

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $command = 'start /B "" ' . escapeshellarg($php) . ' ' . escapeshellarg($artisan)
                    . ' recruiting:process-actions --user=' . (int) $user->id
                    . ' > ' . escapeshellarg($logPath) . ' 2>&1';
                pclose(popen($command, 'r'));
                return;
            }

            $command = 'nohup ' . escapeshellarg($php) . ' ' . escapeshellarg($artisan)
                . ' recruiting:process-actions --user=' . (int) $user->id
                . ' > ' . escapeshellarg($logPath) . ' 2>&1 &';
            Process::fromShellCommandline($command, base_path())->run();
        } catch (\Throwable $exception) {
            Cache::forget($launchKey);
            Log::warning('Unable to start Recruiting Center action worker.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
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
        $syncEveryMinutes = max(1, (int) config('coach-database-sync.tags.sync_minutes', config('ghl.coach_database.tag_sync_minutes', 5)));

        if (! $force && $lastSyncedAt) {
            try {
                if (now()->diffInMinutes(\Illuminate\Support\Carbon::parse($lastSyncedAt)) < $syncEveryMinutes) {
                    $this->hydrateFromSnapshot($snapshot);
                    $this->refreshContactTagSyncStatus();
                    return;
                }
            } catch (\Throwable) {
                // Invalid legacy timestamps are treated as stale and refreshed below.
            }
        }

        $user = Auth::user();
        if (! $user) {
            return;
        }

        $this->startContactTagSyncInBackground($user, $force);
        $this->hydrateFromSnapshot($snapshot);
        $this->refreshContactTagSyncStatus();
    }

    /**
     * Start a tag-only refresh outside Livewire. This method never calls Recruiting Center directly.
     */
    public function syncLatestContactTags(bool $force = true): void
    {
        if (! $this->allowed || $this->locked) {
            return;
        }

        $user = Auth::user();
        if (! $user) {
            return;
        }

        $status = $this->startContactTagSyncInBackground($user, $force);
        $this->refreshContactTagSyncStatus($status);

        if ($force) {
            $state = strtolower((string) ($status['status'] ?? ''));
            $message = in_array($state, ['running', 'already_running'], true)
                ? 'Favorites and list tags are refreshing in the background. Cached results remain available while it runs.'
                : (string) ($status['message'] ?? 'Unable to start the background tag refresh.');

            $notification = Notification::make()
                ->title('Recruiting Center')
                ->body($message);

            if (in_array($state, ['running', 'already_running'], true)) {
                $notification->success();
            } else {
                $notification->danger();
            }

            $notification->send();
        }
    }

    protected function startContactTagSyncInBackground($user, bool $force = false): array
    {
        $lockKey = $this->contactTagSyncLockKey($user);
        $statusKey = $this->contactTagSyncStatusKey($user);

        if (! Cache::add($lockKey, now()->toDateTimeString(), now()->addMinutes(30))) {
            $existing = Cache::get($statusKey, []);
            return array_merge(is_array($existing) ? $existing : [], [
                'status' => 'already_running',
                'user_id' => $user->id,
                'message' => $existing['message'] ?? 'Favorites and list tags are already refreshing in the background.',
            ]);
        }

        $status = [
            'status' => 'running',
            'mode' => 'contact_tag_sync',
            'user_id' => $user->id,
            'started_at' => now()->toDateTimeString(),
            'message' => 'Refreshing Favorites, Saved items, and custom lists in a detached process.',
        ];
        Cache::put($statusKey, $status, now()->addMinutes(60));

        $php = (new PhpExecutableFinder())->find(false) ?: PHP_BINARY;
        $artisan = base_path('artisan');
        $logPath = storage_path('logs/recruiting-tag-sync-' . $user->id . '.log');
        $arguments = ' --user=' . (int) $user->id . ($force ? ' --force' : '') . ' --release-lock';

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $command = 'start /B "" ' . escapeshellarg($php) . ' ' . escapeshellarg($artisan)
                    . ' recruiting:sync-tags' . $arguments . ' > ' . escapeshellarg($logPath) . ' 2>&1';
                pclose(popen($command, 'r'));
                return $status;
            }

            $command = 'nohup ' . escapeshellarg($php) . ' ' . escapeshellarg($artisan)
                . ' recruiting:sync-tags' . $arguments . ' > ' . escapeshellarg($logPath) . ' 2>&1 &';
            Process::fromShellCommandline($command, base_path())->run();
        } catch (\Throwable $exception) {
            Cache::forget($lockKey);
            $status = [
                'status' => 'failed_to_start',
                'mode' => 'contact_tag_sync',
                'user_id' => $user->id,
                'failed_at' => now()->toDateTimeString(),
                'error' => $exception->getMessage(),
                'message' => 'Unable to start the background Favorites/List refresh: ' . $exception->getMessage(),
            ];
            Cache::put($statusKey, $status, now()->addMinutes(60));
            Log::warning('Unable to start Recruiting Center tag sync.', $status);
        }

        return $status;
    }

    protected function refreshContactTagSyncStatus(?array $status = null): void
    {
        $user = Auth::user();
        if (! $user) {
            $this->isSyncingTags = false;
            return;
        }

        $status ??= Cache::get($this->contactTagSyncStatusKey($user), []);
        $running = Cache::has($this->contactTagSyncLockKey($user));
        $state = strtolower((string) (is_array($status) ? ($status['status'] ?? '') : ''));
        $this->isSyncingTags = $running || in_array($state, ['running', 'already_running'], true);
    }

    protected function contactTagSyncLockKey($user): string
    {
        return 'recruiting:tag-sync-running:' . $user->id;
    }

    protected function contactTagSyncStatusKey($user): string
    {
        return 'recruiting:tag-sync-status:' . $user->id;
    }

    public function loadConversations(): void
    {
        $this->isLoadingConversations = true;
        $this->startDeferredUiSync('conversations');
    }

    public function updatedConversationSearch(): void
    {
        // Search the already loaded/cached conversation rows. Remote searching
        // on every keystroke made the whole component wait behind the upstream
        // request and delayed unrelated clicks.
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

        // Poll only local cache. When data becomes stale, launch a detached CLI
        // refresh and return immediately so no network request can hold the UI.
        $conversationCache = Cache::get($this->deferredUiCacheKey('conversations'), []);
        if (is_array($conversationCache['rows'] ?? null)) {
            $this->conversations = collect($conversationCache['rows'])
                ->filter(fn ($row): bool => is_array($row))
                ->take((int) config('coach-database-sync.ui.conversation_row_cap', 25))
                ->values()
                ->all();
        }

        $conversationCachedAt = is_array($conversationCache) ? ($conversationCache['cached_at'] ?? null) : null;
        $conversationIsStale = ! $conversationCachedAt;
        if ($conversationCachedAt) {
            try {
                $conversationIsStale = \Illuminate\Support\Carbon::parse($conversationCachedAt)->lessThan(now()->subSeconds(60));
            } catch (\Throwable) {
                $conversationIsStale = true;
            }
        }

        $user = Auth::user();
        if ($conversationIsStale && $user
            && ! Cache::has(CoachDatabaseUiSyncService::lockKey($user, 'conversations'))) {
            $this->startDeferredUiSync('conversations');
            $this->isLoadingConversations = true;
        }

        if ($this->selectedConversationId) {
            $messageCache = Cache::get($this->deferredUiCacheKey('messages', $this->selectedConversationId), []);
            if (is_array($messageCache['rows'] ?? null)) {
                $this->messages = collect($messageCache['rows'])
                    ->filter(fn ($row): bool => is_array($row))
                    ->values()
                    ->all();
                $this->messageLastId = $messageCache['last_message_id'] ?? $this->messageLastId;
                $this->hasMoreMessages = (bool) ($messageCache['has_more'] ?? $this->hasMoreMessages);
            }

            $messageCachedAt = is_array($messageCache) ? ($messageCache['cached_at'] ?? null) : null;
            $messageIsStale = ! $messageCachedAt;
            if ($messageCachedAt) {
                try {
                    $messageIsStale = \Illuminate\Support\Carbon::parse($messageCachedAt)->lessThan(now()->subSeconds(30));
                } catch (\Throwable) {
                    $messageIsStale = true;
                }
            }

            if ($messageIsStale && $user
                && ! Cache::has(CoachDatabaseUiSyncService::lockKey($user, 'messages', $this->selectedConversationId))) {
                $this->startDeferredUiSync('messages', $this->selectedConversationId);
                $this->isLoadingConversationMessages = true;
            }
        }
    }


    protected function recruitingDashboardActivityCacheKey($user): string
    {
        return 'coach-database:dashboard-activity:' . ($user?->id ?? 'guest') . ':' . md5((string) ($user?->ghl_location_id ?? '') . '|' . substr((string) ($user?->ghl_api_key ?? ''), -12));
    }

    protected function recruitingDashboardActivityHistoryCacheKey($user): string
    {
        return 'coach-database:dashboard-activity-history:' . ($user?->id ?? 'guest') . ':' . md5((string) ($user?->ghl_location_id ?? ''));
    }

    protected function normalizeDashboardActivityRow(array $item): ?array
    {
        $title = trim((string) ($item['title'] ?? ''));
        $copy = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($item['copy'] ?? $item['snippet'] ?? ''))) ?? '');

        if ($title === '' && $copy === '') {
            return null;
        }

        $time = $item['time'] ?? $item['last_message_at'] ?? $item['updated_at'] ?? null;
        $timestamp = 0;

        if ($time) {
            try {
                $timestamp = \Carbon\Carbon::parse($time)->getTimestamp();
            } catch (\Throwable $exception) {
                $timestamp = 0;
            }
        }

        return array_merge($item, [
            'type' => trim((string) ($item['type'] ?? 'activity')) ?: 'activity',
            'title' => $title !== '' ? $title : 'Recruiting activity',
            'copy' => $copy !== '' ? $copy : 'Recruiting activity recorded.',
            'time' => $time,
            '_timestamp' => $timestamp,
            'url' => $item['url'] ?? \App\Filament\Pages\CoachDatabaseConversations::getUrl(),
        ]);
    }

    protected function dashboardActivityIdentity(array $item): string
    {
        $contact = strtolower(trim((string) ($item['coach_id'] ?? $item['contact_id'] ?? $item['conversation_id'] ?? '')));
        $platform = strtolower(trim((string) ($item['platform_key'] ?? $item['platform'] ?? '')));
        $time = trim((string) ($item['time'] ?? ''));

        return md5(
            strtolower(trim((string) ($item['type'] ?? 'activity'))) . '|' .
            $contact . '|' . $platform . '|' .
            strtolower(trim((string) ($item['title'] ?? ''))) . '|' .
            strtolower(trim((string) ($item['copy'] ?? ''))) . '|' . $time
        );
    }

    protected function cachedDashboardActivityRows($user): array
    {
        if (! $user) {
            return [];
        }

        $rows = Cache::get($this->recruitingDashboardActivityHistoryCacheKey($user), []);

        return collect(is_array($rows) ? $rows : [])
            ->filter(fn ($row): bool => is_array($row))
            ->map(fn (array $row): ?array => $this->normalizeDashboardActivityRow($row))
            ->filter()
            ->sortByDesc(fn (array $row): int => (int) ($row['_timestamp'] ?? 0))
            ->values()
            ->all();
    }

    protected function persistDashboardActivityRows($user, array $rows): void
    {
        if (! $user) {
            return;
        }

        $existing = $this->cachedDashboardActivityRows($user);
        $merged = collect($rows)
            ->merge($existing)
            ->filter(fn ($row): bool => is_array($row))
            ->map(fn (array $row): ?array => $this->normalizeDashboardActivityRow($row))
            ->filter()
            ->unique(fn (array $row): string => $this->dashboardActivityIdentity($row))
            ->sortByDesc(fn (array $row): int => (int) ($row['_timestamp'] ?? 0))
            ->take(200)
            ->map(function (array $row): array {
                unset($row['_timestamp']);
                return $row;
            })
            ->values()
            ->all();

        Cache::forever($this->recruitingDashboardActivityHistoryCacheKey($user), $merged);
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

        // Page rendering is cache-only. Remote activity refreshes are performed by
        // the detached background sync so opening the dashboard cannot time out.
        $summary = Cache::get($this->recruitingDashboardActivityCacheKey($user), []);
        $summary = is_array($summary) ? $summary : [];

        $remoteStats = $summary['stats'] ?? [];
        if (is_array($remoteStats)) {
            $this->stats = $this->mergeDashboardTrackingStats($this->stats ?? [], $remoteStats);
        }

        $recent = collect(is_array($summary['recent_activity'] ?? null) ? $summary['recent_activity'] : [])
            ->filter(fn ($row): bool => is_array($row))
            ->values()
            ->all();

        if (empty($this->conversations) && ! empty($summary['conversations']) && is_array($summary['conversations'])) {
            $this->conversations = collect($summary['conversations'])
                ->filter(fn ($row): bool => is_array($row))
                ->take((int) config('coach-database-sync.ui.conversation_row_cap', 25))
                ->values()
                ->all();
        }

        $activityRows = collect($recent)
            ->merge($this->dashboardRecentActivity ?? [])
            ->merge($this->localCoachDashboardActivityRows())
            ->merge($this->conversationDashboardActivityRows())
            ->filter(fn ($row): bool => is_array($row))
            ->values()
            ->all();

        $this->persistDashboardActivityRows($user, $activityRows);
        $this->dashboardRecentActivity = collect($this->cachedDashboardActivityRows($user))
            ->take(30)
            ->values()
            ->all();
        $this->dashboardActivitySummary = $summary;

        $this->persistDashboardStatsAndActivity($user);
    }

    protected function mergeDashboardTrackingStats(array $baseStats, array $remoteStats): array
    {
        $numeric = function ($value): int {
            return is_scalar($value) && is_numeric($value) ? (int) $value : 0;
        };

        $merged = array_merge($baseStats, array_filter($remoteStats, fn ($value) => $value !== null));

        foreach ($this->dashboardTrackingStatKeys() as $key) {
            $merged[$key] = max($numeric($baseStats[$key] ?? 0), $numeric($remoteStats[$key] ?? 0), $numeric($merged[$key] ?? 0));
        }

        $profileBreakdown = (int) ($merged['view_profile_website'] ?? 0)
            + (int) ($merged['view_profile_instagram'] ?? 0)
            + (int) ($merged['view_profile_youtube'] ?? 0)
            + (int) ($merged['view_profile_x'] ?? 0)
            + (int) ($merged['view_profile_email_link'] ?? 0);

        $merged['view_profile_total'] = max((int) ($merged['view_profile_total'] ?? 0), $profileBreakdown);
        $merged['profile_views'] = max((int) ($merged['profile_views'] ?? 0), (int) ($merged['view_profile_total'] ?? 0));
        $merged['profile_view_school_click_count'] = max((int) ($merged['profile_view_school_click_count'] ?? 0), (int) ($merged['school_profile_view_count'] ?? 0), (int) ($merged['view_profile_total'] ?? 0));
        $merged['profile_view_unique_contact_count'] = max((int) ($merged['profile_view_unique_contact_count'] ?? 0), (int) ($merged['unique_profile_view_contacts'] ?? 0), (int) ($merged['unique_profile_view_count'] ?? 0), (int) ($merged['profile_views'] ?? 0) > 0 ? 1 : 0);
        $merged['profile_view_unique_school_count'] = max((int) ($merged['profile_view_unique_school_count'] ?? 0), (int) ($merged['schools_with_profile_views'] ?? 0));
        $merged['emails_sent'] = max((int) ($merged['emails_sent'] ?? 0), (int) ($merged['email_sent_count'] ?? 0));
        $merged['email_sent_count'] = max((int) ($merged['email_sent_count'] ?? 0), (int) ($merged['emails_sent'] ?? 0));
        $merged['email_opens'] = max((int) ($merged['email_opens'] ?? 0), (int) ($merged['email_open_count'] ?? 0));
        $socialClicks = (int) ($merged['website_click_count'] ?? 0)
            + (int) ($merged['instagram_click_count'] ?? 0)
            + (int) ($merged['youtube_click_count'] ?? 0)
            + (int) ($merged['x_click_count'] ?? 0);
        $merged['link_clicks'] = max((int) ($merged['link_clicks'] ?? 0), (int) ($merged['email_click_count'] ?? 0) + $socialClicks);
        $merged['trigger_link_clicks'] = max((int) ($merged['trigger_link_clicks'] ?? 0), (int) ($merged['link_clicks'] ?? 0));
        $merged['unique_profile_views'] = max((int) ($merged['unique_profile_views'] ?? 0), (int) ($merged['unique_profile_view_contacts'] ?? 0), (int) ($merged['unique_profile_view_count'] ?? 0));
        $merged['unique_link_click_contacts'] = max((int) ($merged['unique_link_click_contacts'] ?? 0), (int) ($merged['unique_link_click_count'] ?? 0));
        $merged['unique_clicks'] = max((int) ($merged['unique_clicks'] ?? 0), (int) ($merged['unique_contact_clicks'] ?? 0), (int) ($merged['unique_link_click_contacts'] ?? 0), (int) ($merged['unique_profile_views'] ?? 0));
        $merged['contact_link_clicks'] = max((int) ($merged['contact_link_clicks'] ?? 0), (int) ($merged['ghl_contact_clicks'] ?? 0), (int) ($merged['contact_clicks'] ?? 0));
        $merged['school_clicks_total'] = max((int) ($merged['school_clicks_total'] ?? 0), (int) ($merged['overall_school_clicks'] ?? 0), (int) ($merged['school_click_count'] ?? 0));
        $merged['school_link_clicks'] = max((int) ($merged['school_link_clicks'] ?? 0), (int) ($merged['school_link_click_count'] ?? 0));

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
            'unique_contact_clicks',
            'unique_profile_view_contacts',
            'unique_profile_views',
            'unique_link_click_contacts',
            'unique_clicks',
            'contact_link_clicks',
            'ghl_contact_clicks',
            'overall_school_clicks',
            'school_clicks_total',
            'school_link_clicks',
            'schools_with_clicks',
            'school_profile_views',
            'coach_replies',
        ];
    }

    protected function persistDashboardStatsAndActivity($user = null): void
    {
        $user ??= Auth::user();
        $history = $this->cachedDashboardActivityRows($user);
        $mergedActivity = collect($this->dashboardRecentActivity ?? [])
            ->merge($history)
            ->filter(fn ($row): bool => is_array($row))
            ->map(fn (array $row): ?array => $this->normalizeDashboardActivityRow($row))
            ->filter()
            ->unique(fn (array $row): string => $this->dashboardActivityIdentity($row))
            ->sortByDesc(fn (array $row): int => (int) ($row['_timestamp'] ?? 0))
            ->take(30)
            ->map(function (array $row): array {
                unset($row['_timestamp']);
                return $row;
            })
            ->values()
            ->all();

        $this->dashboardRecentActivity = $mergedActivity;
        $this->persistDashboardActivityRows($user, $mergedActivity);

        $snapshot = Cache::get($this->activeCacheKey(), $this->emptySnapshot());
        $snapshot['stats'] = $this->mergeDashboardTrackingStats($snapshot['stats'] ?? [], $this->stats ?? []);
        $snapshot['dashboard_recent_activity'] = $mergedActivity;
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
        $conversationId = trim($conversationId);
        if ($conversationId === '') {
            return;
        }

        $this->selectedConversationId = $conversationId;
        $this->messageLastId = null;
        $this->hasMoreMessages = false;

        $cached = Cache::get($this->deferredUiCacheKey('messages', $conversationId), []);
        $this->messages = is_array($cached['rows'] ?? null)
            ? collect($cached['rows'])->filter(fn ($row): bool => is_array($row))->values()->all()
            : [];
        $this->messageLastId = $cached['last_message_id'] ?? null;
        $this->hasMoreMessages = (bool) ($cached['has_more'] ?? false);
        $this->isLoadingConversationMessages = true;

        // The conversation pane is now rendered immediately. JavaScript starts
        // the slower message request after the DOM morph completes.
        $this->dispatch('rc-load-conversation-messages');
    }

    public function loadConversationMessages(): void
    {
        if (! $this->selectedConversationId) {
            $this->isLoadingConversationMessages = false;
            return;
        }

        $this->isLoadingConversationMessages = true;
        $this->startDeferredUiSync('messages', $this->selectedConversationId);
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
        $coachForTokens = is_array($coach) ? $coach : array_filter([
            'id' => $contactId,
            'email' => $to,
            'name' => $conversation['contact_name'] ?? $conversation['name'] ?? null,
            'school' => $conversation['school'] ?? $conversation['company_name'] ?? null,
            'business_id' => $conversation['business_id'] ?? $conversation['ghl_business_id'] ?? null,
        ], fn ($value): bool => ! is_null($value) && $value !== '');
        $subject = $this->replaceCampaignTokens($subject, $coachForTokens);
        $body = $this->replaceCampaignTokens($body, $coachForTokens);
        $plainBody = trim(strip_tags($body));

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

        $trackedBody = $this->ensurePlyrcardEmailSignature($body);
        $conversationForTokens = is_array($conversation ?? null) ? $conversation : [];
        $tokenCoach = is_array($coach) ? $coach : [
            'id' => $contactId,
            'name' => $conversationForTokens['contact_name'] ?? $conversationForTokens['name'] ?? 'Coach',
            'email' => $to,
            'school' => $conversationForTokens['school'] ?? $conversationForTokens['company_name'] ?? null,
            'business_id' => $conversationForTokens['business_id'] ?? $conversationForTokens['ghl_business_id'] ?? null,
        ];
        $trackedBody = $this->replaceCampaignTokens($trackedBody, $tokenCoach);

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
            $this->prependDashboardActivity([
                'type' => 'email_sent',
                'title' => 'Email sent to ' . (string) ($coachForTokens['name'] ?? $to ?? 'coach'),
                'copy' => trim(($coachForTokens['school'] ?? '') . (($coachForTokens['school'] ?? '') !== '' ? ' • ' : '') . $subject),
                'time' => now()->toIso8601String(),
                'school_id' => $this->trackingSchoolIdForCoach(is_array($coachForTokens) ? $coachForTokens : []),
                'url' => \App\Filament\Pages\CoachDatabaseConversations::getUrl(),
            ]);
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
            $this->isLoadingConversationMessages = true;
            $this->dispatch('rc-load-conversation-messages');
        }

        $this->loadDashboardActivity();

        Notification::make()->title('Recruiting Center')->body('Email sent.')->success()->send();
    }

    public function loadTemplates(): void
    {
        $user = Auth::user();

        if (! $user || ! $this->allowed || $this->locked) {
            $this->isLoadingTemplates = false;
            return;
        }

        $this->isLoadingTemplates = true;
        $this->error = null;

        try {
            $result = app(CoachDatabaseService::class)->getEmailTemplatesForUser($user);
            $remoteTemplates = collect($result['templates'] ?? [])
                ->filter(fn ($template): bool => is_array($template))
                ->map(function (array $template): array {
                    $id = trim((string) ($template['id'] ?? $template['_id'] ?? $template['templateId'] ?? ''));
                    if ($id !== '') {
                        $template['id'] = $id;
                    }

                    $template['source_type'] = $template['source_type'] ?? 'ghl';
                    return $template;
                })
                ->filter(fn (array $template): bool => filled($template['id'] ?? null))
                ->values();

            $this->templates = $remoteTemplates
                ->merge($this->hardcodedEmailTemplates())
                ->unique(fn (array $template): string => (string) ($template['id'] ?? md5(json_encode($template))))
                ->values()
                ->all();

            $this->templateSourceSummary = (string) ($result['source'] ?? 'GoHighLevel');
            $this->templateSourceDebug = is_array($result['debug'] ?? null) ? $result['debug'] : [];

            if (! ($result['success'] ?? false) && $remoteTemplates->isEmpty()) {
                $this->error = $result['error'] ?? 'Unable to load GoHighLevel templates.';
            }
        } catch (\Throwable $exception) {
            Log::error('Unable to load GoHighLevel email templates.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            $this->templates = collect($this->templates)
                ->merge($this->hardcodedEmailTemplates())
                ->unique(fn (array $template): string => (string) ($template['id'] ?? md5(json_encode($template))))
                ->values()
                ->all();

            $this->error = 'Unable to load GoHighLevel templates.';
        } finally {
            $this->isLoadingTemplates = false;
            $this->isRefreshingRemoteData = false;
            $this->activeUiOperation = null;
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
        $this->templateAttachmentUploads = [];
        $this->templateAttachments = [];
        $this->templateBody = $this->starterTemplateHtml();
        $this->templateEditorRefreshKey++;
        $this->dispatch('rc-template-editor-refresh', body: base64_encode($this->templateBody), key: $this->templateEditorRefreshKey);
    }

    public function selectTemplate(string $templateId): void
    {
        $templateId = trim($templateId);

        if ($templateId === '') {
            return;
        }

        // Open immediately from the local summary/built-in record. Remote HTML
        // is fetched in a second request after the editor is visible.
        $template = $this->templateDetails[$templateId] ?? null;
        if (! is_array($template)) {
            $template = collect($this->templates)->firstWhere('id', $templateId)
                ?: collect($this->hardcodedEmailTemplates())->firstWhere('id', $templateId);
        }

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
        $this->templateAttachmentUploads = [];
        $this->templateAttachments = $this->extractPlyrcardAttachmentLinks($this->templateHtml($template));
        $this->templateBody = $this->templateHtml($template);
        if ($this->templateBody === '') {
            $this->templateBody = $this->coerceTemplateHtml($template);
        }
        $this->templateEditorRefreshKey++;
        $this->dispatch('rc-template-editor-refresh', body: base64_encode($this->templateBody), key: $this->templateEditorRefreshKey);

        $isRemoteSummary = ! $this->templateIsNew && (($template['source_type'] ?? null) !== 'built_in');
        $hasFullBody = isset($this->templateDetails[$templateId]);
        $this->isLoadingTemplateDetail = $isRemoteSummary && ! $hasFullBody;

        if ($this->isLoadingTemplateDetail) {
            $this->dispatch('rc-load-template-detail');
        }
    }

    public function loadSelectedTemplateDetail(): void
    {
        $user = Auth::user();
        $templateId = trim((string) $this->selectedTemplateId);

        if (! $user || $templateId === '' || $this->isBuiltInTemplateId($templateId)) {
            $this->isLoadingTemplateDetail = false;
            return;
        }

        $this->isLoadingTemplateDetail = true;

        try {
            $result = app(CoachDatabaseService::class)->getEmailTemplateForUser($user, $templateId);

            if (! ($result['success'] ?? false) || ! is_array($result['template'] ?? null)) {
                Notification::make()
                    ->title('Templates')
                    ->body($this->templateErrorMessage($result, 'Unable to load template content.'))
                    ->danger()
                    ->send();
                return;
            }

            $template = $result['template'];
            $template['id'] = $templateId;
            $this->templateDetails[$templateId] = $template;

            $this->templateName = trim((string) ($template['name'] ?? $template['title'] ?? 'Untitled Template')) ?: 'Untitled Template';
            $this->templateSubject = $this->templateSubject($template);
            $this->templatePreviewText = $this->templatePreviewText($template);

            $html = $this->templateHtml($template);
            if ($html === '') {
                $html = $this->coerceTemplateHtml($template);
            }

            $this->templateBody = $html;
            $this->templateAttachments = $this->extractPlyrcardAttachmentLinks($html);
            $this->templateEditorRefreshKey++;

            $this->dispatch(
                'rc-template-editor-refresh',
                body: base64_encode($this->templateBody),
                key: $this->templateEditorRefreshKey
            );
        } catch (\Throwable $exception) {
            Log::error('Unable to load GoHighLevel template detail.', [
                'user_id' => $user->id,
                'template_id' => $templateId,
                'error' => $exception->getMessage(),
            ]);

            Notification::make()
                ->title('Templates')
                ->body('Unable to load template content.')
                ->danger()
                ->send();
        } finally {
            $this->isLoadingTemplateDetail = false;
        }
    }

    protected function starterTemplateHtml(): string
    {
        $body = <<<'HTML'
<div style="max-width:680px;margin:0 auto;background:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#111827;line-height:1.65;font-size:15px;">
    <div style="padding:26px 28px 18px;border:1px solid #e5e7eb;border-radius:18px;background:#ffffff;">
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
</div>
HTML;

        return $this->canonicalizeTemplateEditorHtml($body);
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
        // Preserve the original GoHighLevel HTML, inline CSS, style tags,
        // tables, and document structure returned by the visual editor.
        $html = $this->appendAttachmentLinksToHtml(
            trim($bodyText),
            $this->templateAttachments
        );

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
                // Some Recruiting Center HTML-builder templates expose one id for loading and a different/internal
                // id for editing. If none of the known ids can be updated, save the edited version as
                // a new Recruiting Center template instead of failing with "Template not found" and losing the work.
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
            ? 'Template saved as a new edited copy because Recruiting Center would not update the original template id.'
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

        $hasFullDetail = $this->isBuiltInTemplateId($templateId) || isset($this->templateDetails[$templateId]);
        $template = $this->loadTemplateDetail($templateId);

        if (! $hasFullDetail && ! $this->isBuiltInTemplateId($templateId) && Auth::user()) {
            $detailResult = app(CoachDatabaseService::class)->getEmailTemplateForUser(Auth::user(), $templateId);

            if (($detailResult['success'] ?? false) && is_array($detailResult['template'] ?? null)) {
                $template = $detailResult['template'];
                $template['id'] = $templateId;
                $this->templateDetails[$templateId] = $template;
                $hasFullDetail = true;
            }
        }

        $template = $template ?: collect($this->templates)->firstWhere('id', $templateId);

        if (! is_array($template)) {
            Notification::make()->title('Templates')->body('Template could not be duplicated.')->danger()->send();
            return;
        }

        $this->templateEditorOpen = true;
        $this->selectedTemplateId = $hasFullDetail ? null : $templateId;
        $this->previewTemplateId = null;
        $this->campaignTemplateId = null;
        $this->templateIsNew = true;
        $this->templateName = trim((string) ($template['name'] ?? 'Email Template')) . ' Copy';
        $this->templateSubject = $this->templateSubject($template);
        $this->templatePreviewText = $this->templatePreviewText($template);
        $this->templateGraphicUrl = '';
        $this->templateGraphicUpload = null;
        $this->templateInlineImageUpload = null;
        $this->templateAttachmentUploads = [];
        $this->templateAttachments = $this->extractPlyrcardAttachmentLinks($this->templateHtml($template));
        $this->templateBody = $this->canonicalizeTemplateEditorHtml($this->templateHtmlForNativeEditor($template));
        $this->templateEditorRefreshKey++;
        $this->dispatch('rc-template-editor-refresh', body: base64_encode($this->templateBody), key: $this->templateEditorRefreshKey);

        if (! $hasFullDetail) {
            $this->pendingTemplateAction = 'duplicate';
            $this->pendingTemplateActionId = $templateId;
            $this->isLoadingTemplateDetail = true;
            $this->dispatch('rc-load-template-detail');
        }
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

        $hasFullDetail = $this->isBuiltInTemplateId($templateId) || isset($this->templateDetails[$templateId]);
        $template = $this->loadTemplateDetail($templateId)
            ?: collect($this->templates)->firstWhere('id', $templateId);

        if (! is_array($template)) {
            Notification::make()->title('Compose Email')->body('Template could not be opened.')->danger()->send();
            return;
        }

        $this->campaignTemplateId = $templateId;
        $this->selectedTemplateId = $templateId;
        $this->previewTemplateId = $templateId;
        $this->campaignName = trim((string) ($template['name'] ?? 'Recruiting Email')) ?: 'Recruiting Email';
        $this->campaignSubject = $this->templateSubject($template);
        $this->campaignPreviewText = $this->templatePreviewText($template);
        $this->composeGraphicUrl = '';
        $this->composeAttachments = $this->extractPlyrcardAttachmentLinks($this->templateHtml($template));
        $this->campaignBody = $this->templateHtml($template);
        if ($this->campaignBody === '') {
            $this->campaignBody = $this->coerceTemplateHtml($template);
        }

        if (trim($this->campaignBody) === '') {
            $this->campaignBody = $this->canonicalizeTemplateEditorHtml($this->templateTextToHtml(trim(strip_tags((string) ($template['body'] ?? $template['html'] ?? '')))));
        }

        $this->section = 'compose';
        $this->activeSubpage = 'compose-email';
        $this->composeTemplateAppliedRecently = true;
        $this->composeTemplateMenuOpen = false;
        $this->dispatch('rc-compose-editor-refresh', body: base64_encode($this->campaignBody));
        if (! $hasFullDetail) {
            $this->pendingTemplateAction = 'use-compose';
            $this->pendingTemplateActionId = $templateId;
            $this->isLoadingTemplateDetail = true;
            $this->dispatch('rc-load-template-detail');
        }

        Notification::make()->title('Compose Email')->body($hasFullDetail ? 'Template loaded in Compose Email.' : 'Template opened. The latest content is loading.')->success()->send();
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
                        ->body($this->templateErrorMessage($result, 'Unable to upload one attachment to Recruiting Center media.'))
                        ->danger()
                        ->send();
                    continue;
                }

                $name = method_exists($file, 'getClientOriginalName')
                    ? (string) $file->getClientOriginalName()
                    : basename((string) ($result['url'] ?? 'attachment'));

                $this->composeAttachments[] = [
                    'id' => $result['id'] ?? null,
                    'name' => $name,
                    'url' => trim((string) $result['url']),
                    'mime_type' => method_exists($file, 'getMimeType') ? (string) $file->getMimeType() : null,
                    'size' => method_exists($file, 'getSize') ? (int) $file->getSize() : null,
                ];
            } catch (\Throwable $exception) {
                Notification::make()->title('Attachments')->body('Unable to upload one attachment to Recruiting Center media.')->danger()->send();
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


    public function updatedTemplateAttachmentUploads(): void
    {
        $this->addTemplateAttachments();
    }

    public function addTemplateAttachments(): void
    {
        if (empty($this->templateAttachmentUploads)) {
            return;
        }

        $user = Auth::user();
        if (! $user) {
            $this->templateAttachmentUploads = [];
            return;
        }

        $files = collect($this->templateAttachmentUploads)
            ->filter(fn ($file): bool => is_object($file) && method_exists($file, 'getRealPath'))
            ->values();

        if ($files->isEmpty()) {
            $this->templateAttachmentUploads = [];
            return;
        }

        try {
            $this->validate([
                'templateAttachmentUploads.*' => ['file', 'max:25600'],
            ]);
        } catch (\Throwable $exception) {
            $this->templateAttachmentUploads = [];
            Notification::make()->title('Template attachments')->body('Each attachment must be 25MB or smaller.')->danger()->send();
            return;
        }

        foreach ($files as $file) {
            try {
                $result = app(CoachDatabaseService::class)->uploadMediaForUser($user, $file);

                if (! ($result['success'] ?? false) || blank($result['url'] ?? null)) {
                    Notification::make()
                        ->title('Template attachments')
                        ->body($this->templateErrorMessage($result, 'Unable to upload one attachment to Recruiting Center media.'))
                        ->danger()
                        ->send();
                    continue;
                }

                $name = method_exists($file, 'getClientOriginalName')
                    ? (string) $file->getClientOriginalName()
                    : basename((string) ($result['url'] ?? 'attachment'));

                $this->templateAttachments[] = [
                    'id' => $result['id'] ?? null,
                    'name' => $name,
                    'url' => trim((string) $result['url']),
                    'mime_type' => method_exists($file, 'getMimeType') ? (string) $file->getMimeType() : null,
                    'size' => method_exists($file, 'getSize') ? (int) $file->getSize() : null,
                ];
            } catch (\Throwable $exception) {
                Notification::make()->title('Template attachments')->body('Unable to upload one attachment to Recruiting Center media.')->danger()->send();
            }
        }

        $this->templateAttachmentUploads = [];
    }

    public function removeTemplateAttachment(int $index): void
    {
        if (! array_key_exists($index, $this->templateAttachments)) {
            return;
        }

        unset($this->templateAttachments[$index]);
        $this->templateAttachments = array_values($this->templateAttachments);
    }

    protected function appendAttachmentLinksToHtml(string $html, array $attachments): string
    {
        $attachments = collect($attachments)
            ->filter(fn ($attachment): bool => is_array($attachment) && filled($attachment['url'] ?? null))
            ->values();

        if ($attachments->isEmpty() || str_contains($html, 'data-plyrcard-attachments="1"') || str_contains($html, 'data-plyrcard-attachments="template"')) {
            return $html;
        }

        $links = $attachments->map(function (array $attachment): string {
            $name = e((string) ($attachment['name'] ?? 'Attachment'));
            $url = e((string) ($attachment['url'] ?? ''));
            return '<li style="margin:6px 0"><a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $name . '</a></li>';
        })->implode('');

        if ($links === '') {
            return $html;
        }

        return rtrim($html) . '<div data-plyrcard-attachments="1" style="margin-top:22px;padding-top:14px;border-top:1px solid #e5e7eb;font-family:Arial,Helvetica,sans-serif"><div style="font-weight:700;margin-bottom:8px;color:#111827">Attachments</div><ul style="margin:0;padding-left:18px">' . $links . '</ul></div>';
    }

    protected function extractPlyrcardAttachmentLinks(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $document = $this->loadTemplateDom($html);
        if (! $document) {
            return [];
        }

        $attachments = [];
        foreach ($document->getElementsByTagName('div') as $div) {
            if ((string) $div->getAttribute('data-plyrcard-attachments') === '') {
                continue;
            }

            foreach ($div->getElementsByTagName('a') as $a) {
                $url = trim((string) $a->getAttribute('href'));
                if ($url === '' || ! preg_match('/^https?:\/\//i', $url)) {
                    continue;
                }
                $attachments[] = [
                    'name' => trim((string) $a->textContent) ?: basename(parse_url($url, PHP_URL_PATH) ?: 'Attachment'),
                    'url' => $url,
                    'mime_type' => null,
                    'size' => null,
                ];
            }
        }

        return collect($attachments)->unique('url')->values()->all();
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
        $html = $this->ensurePlyrcardEmailSignature($this->normalizeTemplateLinksForCurrentTracking($this->buildComposeHtml($bodyText)));
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
                    $this->prependDashboardActivity([
                        'type' => 'email_sent',
                        'title' => 'Campaign email sent to ' . (string) ($coach['name'] ?? $coach['email'] ?? 'coach'),
                        'copy' => trim((string) ($coach['school'] ?? $coach['company_name'] ?? '') . (filled($coach['school'] ?? $coach['company_name'] ?? null) ? ' • ' : '') . $personalizedSubject),
                        'time' => now()->toIso8601String(),
                        'url' => \App\Filament\Pages\CoachDatabaseConversations::getUrl(),
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

        if (isset($this->templateDetails[$templateId]) && is_array($this->templateDetails[$templateId])) {
            return $this->templateDetails[$templateId];
        }

        $summary = collect($this->templates)->firstWhere('id', $templateId)
            ?: collect($this->hardcodedEmailTemplates())->firstWhere('id', $templateId);

        return is_array($summary) ? $summary : null;
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
    /**
     * Rebuilds template/compose HTML from scratch around one canonical signature.
     * This is the actual signature pipeline: old footer blocks are removed first,
     * then exactly one current footer is appended. No previous social logo/footer
     * markup is trusted or kept.
     */
    protected function canonicalizeTemplateEditorHtml(string $html): string
    {
        $html = trim($html);

        $html = $this->repairBrokenTemplateLinkFragments($html);
        $html = $this->normalizeTemplateLinksForCurrentTracking($html);
        $html = $this->stripPlyrcardEmailSignatures($html);
        $html = $this->stripLoosePlyrcardSocialFooter($html);
        $html = $this->stripAllPlyrcardSocialAnchors($html);
        $html = trim($html);

        if ($html === '') {
            return $this->dedupePlyrcardSocialIconAnchors($this->plyrcardEmailSignatureHtml());
        }

        return $this->dedupePlyrcardSocialIconAnchors($html . "\n" . $this->plyrcardEmailSignatureHtml());
    }

    protected function normalizeTemplateLinksForCurrentTracking(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $normalized = preg_replace_callback('/<a\b(?=[^>]*\bhref\s*=)([^>]*)>(.*?)<\/a>/is', function (array $matches): string {
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

        return $this->ensureTemplateSocialLogoAnchors($normalized);
    }


    protected function socialIconUrl(string $platform): string
    {
        return match (strtolower(trim($platform))) {
            'instagram' => 'https://img.icons8.com/color/48/instagram-new--v1.png',
            'x', 'twitter' => 'https://img.icons8.com/ios-filled/50/twitterx--v1.png',
            'youtube', 'yt' => 'https://img.icons8.com/color/48/youtube-play.png',
            default => '',
        };
    }

    protected function socialIconAnchor(string $token, string $label, string $platform): string
    {
        $icon = $this->socialIconUrl($platform);

        if ($icon === '') {
            return '<a href="{{' . $token . '}}" target="_blank" style="color:#ff5b32;text-decoration:none;font-weight:700;">' . e($label) . '</a>';
        }

        return '<a href="{{' . $token . '}}" data-plyrcard-link="' . e($platform) . '" target="_blank" style="display:inline-block;text-decoration:none;margin-right:8px;margin-bottom:6px;vertical-align:middle;">'
            . '<span style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:999px;background:#ffffff;border:1px solid #e5e7eb;vertical-align:middle;">'
            . '<img src="' . e($icon) . '" width="20" height="20" alt="' . e($label) . '" style="display:block;width:20px;height:20px;border:0;outline:none;text-decoration:none;" />'
            . '</span></a>';
    }

    protected function plyrcardEmailSignatureHtml(): string
    {
        return <<<'HTML'
<div class="plyrcard-email-signature" data-plyrcard-signature="1" style="margin-top:24px;padding:18px 20px;border:1px solid #e5e7eb;border-radius:16px;background:#f9fafb;font-family:Arial,Helvetica,sans-serif;color:#111827;line-height:1.5;">
    <div style="font-size:16px;font-weight:800;color:#111827;margin-bottom:2px;">{{AthleteName}}</div>
    <div style="font-size:13px;color:#4b5563;margin-bottom:2px;">{{GraduationYear}} • {{Position}} • {{ClubTeam}}</div>
    <div style="font-size:13px;color:#4b5563;margin-bottom:12px;">{{AthleteEmail}} • {{AthletePhone}}</div>
    <div data-plyrcard-social-row="1" style="font-size:0;line-height:0;">
        <a href="{{InstagramLink}}" data-plyrcard-link="instagram" data-plyrcard-signature-social="instagram" target="_blank" style="display:inline-block;text-decoration:none;margin-right:8px;margin-bottom:6px;vertical-align:middle;"><span style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:999px;background:#ffffff;border:1px solid #e5e7eb;vertical-align:middle;"><img src="https://img.icons8.com/color/48/instagram-new--v1.png" width="20" height="20" alt="Instagram" style="display:block;width:20px;height:20px;border:0;outline:none;text-decoration:none;" /></span></a>
        <a href="{{XLink}}" data-plyrcard-link="x" data-plyrcard-signature-social="x" target="_blank" style="display:inline-block;text-decoration:none;margin-right:8px;margin-bottom:6px;vertical-align:middle;"><span style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:999px;background:#ffffff;border:1px solid #e5e7eb;vertical-align:middle;"><img src="https://img.icons8.com/ios-filled/50/twitterx--v1.png" width="20" height="20" alt="X" style="display:block;width:20px;height:20px;border:0;outline:none;text-decoration:none;" /></span></a>
        <a href="{{YoutubeLink}}" data-plyrcard-link="youtube" data-plyrcard-signature-social="youtube" target="_blank" style="display:inline-block;text-decoration:none;margin-right:8px;margin-bottom:6px;vertical-align:middle;"><span style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:999px;background:#ffffff;border:1px solid #e5e7eb;vertical-align:middle;"><img src="https://img.icons8.com/color/48/youtube-play.png" width="22" height="22" alt="YouTube" style="display:block;width:22px;height:22px;border:0;outline:none;text-decoration:none;" /></span></a>
    </div>
</div>
HTML;
    }

    protected function hasPlyrcardEmailSignature(string $html): bool
    {
        $lower = strtolower($html);

        if (str_contains($lower, 'data-plyrcard-signature') || str_contains($lower, 'plyrcard-email-signature')) {
            return true;
        }

        $hasContactLine = str_contains($lower, '{{athleteemail}}') || str_contains($lower, '{{athletephone}}') || str_contains($lower, 'athleteemail') || str_contains($lower, 'athletephone');
        $hasSocials = str_contains($lower, '{{instagramlink}}') || str_contains($lower, '{{xlink}}') || str_contains($lower, '{{youtubelink}}') || str_contains($lower, 'data-plyrcard-link="instagram"') || str_contains($lower, "data-plyrcard-link='instagram'");

        return $hasContactLine && $hasSocials;
    }

    protected function ensurePlyrcardEmailSignature(string $html): string
    {
        return $this->canonicalizeTemplateEditorHtml($html);
    }

    protected function stripPlyrcardEmailSignatures(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        // Remove older footer/signature blocks that were saved before the
        // canonical data-plyrcard-signature marker existed. These older blocks
        // are why social logos can duplicate after a template is loaded and
        // the current footer is appended.
        $html = $this->stripLegacyPlyrcardSignatureFooters($html);

        $needles = [
            'data-plyrcard-signature',
            'plyrcard-email-signature',
        ];

        $offset = 0;
        while ($offset < strlen($html)) {
            $lower = strtolower($html);
            $positions = [];

            foreach ($needles as $needle) {
                $pos = strpos($lower, $needle, $offset);
                if ($pos !== false) {
                    $positions[] = $pos;
                }
            }

            if ($positions === []) {
                break;
            }

            $marker = min($positions);
            $start = strripos(substr($html, 0, $marker), '<div');

            if ($start === false) {
                $offset = $marker + 1;
                continue;
            }

            $end = $this->findClosingDivOffset($html, $start);

            if ($end === null || $end <= $start) {
                $offset = $marker + 1;
                continue;
            }

            $html = substr($html, 0, $start) . substr($html, $end);
            $offset = max(0, $start - 1);
        }

        // Remove any leftover empty paragraphs created where duplicate
        // signatures were stripped.
        $html = preg_replace('/(?:<p[^>]*>\s*(?:&nbsp;)?\s*<\/p>\s*){2,}/i', '', $html) ?: $html;

        return trim($html);
    }

    protected function stripLegacyPlyrcardSignatureFooters(string $html): string
    {
        $offset = 0;

        while ($offset < strlen($html)) {
            if (preg_match('/<div\b[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE, $offset) !== 1) {
                break;
            }

            $start = $match[0][1];
            $end = $this->findClosingDivOffset($html, $start);

            if ($end === null || $end <= $start) {
                $offset = $start + 4;
                continue;
            }

            $block = substr($html, $start, $end - $start);
            $lowerBlock = strtolower($block);

            $looksLikePlyrcardFooter = (
                (str_contains($lowerBlock, '{{athletename}}') || str_contains($lowerBlock, 'athleteemail') || str_contains($lowerBlock, 'athletephone'))
                && (
                    str_contains($lowerBlock, '{{instagramlink}}')
                    || str_contains($lowerBlock, '{{xlink}}')
                    || str_contains($lowerBlock, '{{twitterlink}}')
                    || str_contains($lowerBlock, '{{youtubelink}}')
                    || str_contains($lowerBlock, 'data-plyrcard-link="instagram"')
                    || str_contains($lowerBlock, "data-plyrcard-link='instagram'")
                )
            );

            if ($looksLikePlyrcardFooter) {
                $html = substr($html, 0, $start) . substr($html, $end);
                $offset = max(0, $start - 1);
                continue;
            }

            $offset = $end;
        }

        return trim($html);
    }

    /**
     * Remove legacy signature fragments that do not have data-plyrcard-signature.
     * This specifically removes the old table/footer section from starter
     * templates and any loose social icon clusters that were saved into Recruiting Center.
     */
    protected function stripLoosePlyrcardSocialFooter(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $html = preg_replace('/<table\b[^>]*>.*?\{\{\s*AthleteEmail\s*\}\}.*?\{\{\s*AthletePhone\s*\}\}.*?\{\{\s*(?:InstagramLink|XLink|TwitterLink|YoutubeLink|YouTubeLink)\s*\}\}.*?<\/table>/is', '', $html) ?: $html;

        $html = preg_replace('/<div\b[^>]*>\s*(?:<[^>]+>\s*)*\{\{\s*AthleteName\s*\}\}.*?\{\{\s*AthleteEmail\s*\}\}.*?\{\{\s*AthletePhone\s*\}\}.*?\{\{\s*(?:InstagramLink|XLink|TwitterLink|YoutubeLink|YouTubeLink)\s*\}\}.*?<\/div>\s*/is', '', $html) ?: $html;

        $html = preg_replace('/(?:\s*<a\b[^>]*(?:data-plyrcard-link\s*=\s*["\'](?:instagram|x|twitter|youtube)["\']|href\s*=\s*["\']\s*\{\{\s*(?:InstagramLink|XLink|TwitterLink|YoutubeLink|YouTubeLink)\s*\}\}\s*["\'])[^>]*>.*?<\/a>\s*){2,}/is', '', $html) ?: $html;
        $html = $this->stripAllPlyrcardSocialAnchors($html);

        return trim($html);
    }

    /**
     * The signature is the only allowed place for social logo anchors.
     * Remove every Instagram/X/YouTube token/icon anchor already present in
     * the body before appending the canonical footer. This prevents duplicates
     * even when old Recruiting Center templates already saved social icons outside the marked
     * signature block.
     */
    protected function stripAllPlyrcardSocialAnchors(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        // Remove social anchors by data attribute or merge-token href.
        $html = preg_replace(
            '/\s*<a\b[^>]*(?:data-plyrcard-link\s*=\s*(["\'])(?:instagram|x|twitter|youtube)\1|href\s*=\s*(["\'])\s*\{\{\s*(?:InstagramLink|XLink|TwitterLink|YoutubeLink|YouTubeLink)\s*\}\}\s*\2)[^>]*>.*?<\/a>\s*/is',
            ' ',
            $html
        ) ?: $html;

        // Remove anchors wrapping known social icon images even if attributes
        // were stripped or rewritten by the editor/Recruiting Center.
        $html = preg_replace(
            '/\s*<a\b[^>]*>\s*(?:<span\b[^>]*>\s*)?<img\b[^>]*(?:instagram-new--v1|twitterx--v1|youtube-play|youtube)[^>]*>\s*(?:<\/span>\s*)?<\/a>\s*/is',
            ' ',
            $html
        ) ?: $html;

        // Remove loose social icon images/spans left behind after anchors are
        // removed. This catches the black circles / duplicate image shells.
        $html = preg_replace('/\s*<img\b[^>]*(?:instagram-new--v1|twitterx--v1|youtube-play|youtube)[^>]*>\s*/is', ' ', $html) ?: $html;
        $html = preg_replace('/\s*<span\b[^>]*(?:border-radius\s*:\s*999px|background\s*:\s*#(?:000|ffffff)|background-color\s*:\s*#(?:000|ffffff))[^>]*>\s*<\/span>\s*/is', ' ', $html) ?: $html;

        // Remove empty social/icon containers created by the removals.
        $html = preg_replace('/<div\b[^>]*>\s*<\/div>/i', '', $html) ?: $html;
        $html = preg_replace('/<p\b[^>]*>\s*(?:&nbsp;)?\s*<\/p>/i', '', $html) ?: $html;

        return trim($html);
    }

    protected function dedupePlyrcardSocialIconAnchors(string $html): string
    {
        $seen = [];

        return preg_replace_callback('/<a\b[^>]*(?:data-plyrcard-link\s*=\s*(["\'])(instagram|x|twitter|youtube)\1|href\s*=\s*(["\'])\s*\{\{\s*(InstagramLink|XLink|TwitterLink|YoutubeLink|YouTubeLink)\s*\}\}\s*\3)[^>]*>.*?<\/a>/is', function (array $matches) use (&$seen): string {
            $platform = strtolower((string) ($matches[2] ?? ''));
            $token = strtolower((string) ($matches[4] ?? ''));

            if ($platform === '') {
                $platform = match ($token) {
                    'instagramlink' => 'instagram',
                    'xlink', 'twitterlink' => 'x',
                    'youtubelink', 'youtubelink' => 'youtube',
                    default => '',
                };
            }

            if ($platform === 'twitter') {
                $platform = 'x';
            }

            if ($platform === '') {
                return $matches[0];
            }

            if (isset($seen[$platform])) {
                return '';
            }

            $seen[$platform] = true;
            return $matches[0];
        }, $html) ?: $html;
    }

    protected function findClosingDivOffset(string $html, int $start): ?int
    {
        $length = strlen($html);
        $offset = $start;
        $depth = 0;

        while ($offset < $length) {
            if (preg_match('/<\/?div\b[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE, $offset) !== 1) {
                return null;
            }

            $tag = $match[0][0];
            $pos = $match[0][1];
            $offset = $pos + strlen($tag);

            if (preg_match('/^<\/div/i', $tag) === 1) {
                $depth--;

                if ($depth <= 0) {
                    return $offset;
                }

                continue;
            }

            if (str_ends_with(trim($tag), '/>')) {
                continue;
            }

            $depth++;
        }

        return null;
    }

    protected function ensureTemplateSocialLogoAnchors(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        return preg_replace_callback('/<a\b(?=[^>]*\bhref\s*=)([^>]*)>(.*?)<\/a>/is', function (array $matches): string {
            $anchor = $matches[0];
            $attributes = (string) ($matches[1] ?? '');
            $innerHtml = (string) ($matches[2] ?? '');
            $haystack = strtolower(html_entity_decode($anchor . ' ' . strip_tags($innerHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            $token = null;
            $platform = null;
            $label = null;

            if (str_contains($haystack, '{{instagramlink}}') || str_contains($haystack, 'data-plyrcard-link="instagram"') || str_contains($haystack, "data-plyrcard-link='instagram'")) {
                $token = 'InstagramLink';
                $platform = 'instagram';
                $label = 'Instagram';
            } elseif (str_contains($haystack, '{{xlink}}') || str_contains($haystack, '{{twitterlink}}') || str_contains($haystack, 'data-plyrcard-link="x"') || str_contains($haystack, "data-plyrcard-link='x'")) {
                $token = 'XLink';
                $platform = 'x';
                $label = 'X';
            } elseif (str_contains($haystack, '{{youtubelink}}') || str_contains($haystack, '{{youtubelink}}') || str_contains($haystack, 'data-plyrcard-link="youtube"') || str_contains($haystack, "data-plyrcard-link='youtube'")) {
                $token = 'YoutubeLink';
                $platform = 'youtube';
                $label = 'YouTube';
            }

            if (! $token || ! $platform || ! $label) {
                return $anchor;
            }

            return $this->socialIconAnchor($token, $label, $platform);
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

        // If a saved Recruiting Center template already contains an old /track/... URL, decode the
        // compact payload and convert it back to the stable merge token. This is the
        // important self-healing step for social icon templates: even if Recruiting Center stripped
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
        // links, buttons, lists, colors, and headings remain compatible with Recruiting Center.
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
                    ->body($this->templateErrorMessage($result, 'Unable to upload graphic to Recruiting Center media.'))
                    ->danger()
                    ->send();
                return;
            }

            $this->templateGraphicUrl = trim((string) $result['url']);
        } catch (\Throwable $e) {
            $this->templateGraphicUpload = null;
            Notification::make()->title('Templates')->body('Unable to upload graphic to Recruiting Center media.')->danger()->send();
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

        // Recruiting Center sometimes returns escaped or double-escaped HTML. Decode it a few
        // times before inserting it into contenteditable so users see rendered
        // content rather than literal <a>, <table>, or <div> code.
        for ($i = 0; $i < 3; $i++) {
            $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $html || trim($decoded) === '') {
                break;
            }
            $html = $decoded;
        }

        $html = $this->repairBrokenTemplateLinkFragments($html);

        if (! preg_match('/<\s*(p|div|h1|h2|h3|h4|h5|h6|ul|ol|li|blockquote|img|a|table|tr|td|span|strong|em|br|body|html)\b/i', $html)) {
            return $this->templateTextToHtml(trim(strip_tags($html)));
        }

        return $this->canonicalizeTemplateEditorHtml($this->simplifyEmailHtmlForEditor($html));
    }

    protected function repairBrokenTemplateLinkFragments(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        // Recruiting Center/editor round-trips can leave anchors as visible text fragments when
        // a merge token inside href was converted into a chip. Repair those before
        // DOM parsing so users see real links/buttons instead of raw HTML like:
        // {{ProfileLink}}" target="_blank">View PLYRCard Profile
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $buttonStyles = 'display:block;width:100%;box-sizing:border-box;text-align:center;text-decoration:none;font-weight:800;border-radius:10px;padding:12px 16px;margin:0 0 10px;';
        $linkRepairs = [
            'ProfileLink' => [
                'label' => 'View PLYRCard Profile',
                'style' => $buttonStyles . 'background:#ff5b32;color:#ffffff;',
                'class' => 'rc-email-button',
            ],
            'HighlightLink' => [
                'label' => 'Watch Highlights',
                'style' => $buttonStyles . 'background:#111827;color:#ffffff;',
                'class' => 'rc-email-button',
            ],
            'InstagramLink' => [
                'label' => 'Instagram',
                'style' => 'display:inline-block;text-decoration:none;margin-right:8px;margin-bottom:6px;color:#111827;font-weight:700;',
                'class' => '',
            ],
            'XLink' => [
                'label' => 'X',
                'style' => 'display:inline-block;text-decoration:none;margin-right:8px;margin-bottom:6px;color:#111827;font-weight:700;',
                'class' => '',
            ],
            'TwitterLink' => [
                'label' => 'X',
                'style' => 'display:inline-block;text-decoration:none;margin-right:8px;margin-bottom:6px;color:#111827;font-weight:700;',
                'class' => '',
            ],
            'YoutubeLink' => [
                'label' => 'YouTube',
                'style' => 'display:inline-block;text-decoration:none;margin-right:8px;margin-bottom:6px;color:#111827;font-weight:700;',
                'class' => '',
            ],
            'YouTubeLink' => [
                'label' => 'YouTube',
                'style' => 'display:inline-block;text-decoration:none;margin-right:8px;margin-bottom:6px;color:#111827;font-weight:700;',
                'class' => '',
            ],
        ];

        $socialIconReplacement = fn (string $token, string $label, string $platform): string => $this->socialIconAnchor($token, $label, $platform);

        foreach ($linkRepairs as $token => $config) {
            $tokenPattern = '\\{\\{\\s*' . preg_quote($token, '/') . '\\s*\\}\\}';
            $label = (string) $config['label'];
            $labelPattern = preg_quote($label, '/');
            $class = trim((string) $config['class']);
            $classAttribute = $class !== '' ? ' class="' . $class . '"' : '';
            $style = (string) $config['style'];
            $platform = in_array($token, ['InstagramLink'], true) ? 'instagram' : (in_array($token, ['XLink', 'TwitterLink'], true) ? 'x' : (in_array($token, ['YoutubeLink', 'YouTubeLink'], true) ? 'youtube' : ''));
            $replacement = $platform !== ''
                ? $socialIconReplacement($token, $label, $platform)
                : '<a' . $classAttribute . ' href="{{' . $token . '}}" target="_blank" style="' . $style . '">' . $label . '</a>';

            // Raw orphaned href token followed by the visible label.
            $html = preg_replace(
                '/' . $tokenPattern . '\s*(?:"|\'|&quot;|&#034;|&#39;)\s*(?:data-plyrcard-link\s*=\s*(?:"|\'|&quot;|&#034;|&#39;)[^"\' >]+(?:"|\'|&quot;|&#034;|&#39;)\s*)?(?:target\s*=\s*(?:"|\'|&quot;|&#034;|&#39;)?_blank(?:"|\'|&quot;|&#034;|&#39;)?\s*)?[^>\n\r]*>\s*' . $labelPattern . '/i',
                $replacement,
                $html
            ) ?? $html;

            // Broken social/icon anchors often have no readable label after the >.
            if (in_array($token, ['InstagramLink', 'XLink', 'TwitterLink', 'YoutubeLink', 'YouTubeLink'], true)) {
                $html = preg_replace(
                    '/' . $tokenPattern . '\s*(?:"|\'|&quot;|&#034;|&#39;)\s*data-plyrcard-link\s*=\s*(?:"|\'|&quot;|&#034;|&#39;)[^"\' >]+(?:"|\'|&quot;|&#034;|&#39;)\s*[^>\n\r]*>\s*/i',
                    $replacement . ' ',
                    $html
                ) ?? $html;
            }
        }

        // Repair escaped/broken complete anchors that lost the opening <a href=.
        $html = preg_replace('/(?:^|\s)href\s*=\s*("|\')({{[A-Za-z0-9_ .]+}})\1\s*target\s*=\s*("|\')_blank\3\s*>/i', ' <a href="$2" target="_blank">', $html) ?? $html;

        // Remove icon shells left after SVG stripping. Keep the repaired text links.
        $html = preg_replace('/<span\b[^>]*style="[^"]*(?:background\s*:\s*#?000|background-color\s*:\s*#?000)[^"]*"[^>]*>\s*(?:<\/span>|&nbsp;)?/i', '', $html) ?? $html;
        $html = preg_replace('/<span\b[^>]*class="[^"]*social[^\"]*"[^>]*>\s*<\/span>/i', '', $html) ?? $html;

        // Clean up orphan closing anchors from repaired fragments.
        $html = preg_replace('/<\/a>\s*(?=<a\b)/i', '', $html) ?? $html;

        return trim($html);
    }

    protected function simplifyEmailHtmlForEditor(string $html): string
    {
        $html = $this->sanitizeTemplateHtml($html);
        $html = $this->repairBrokenTemplateLinkFragments($html);
        // Do not create social-icon anchors here. The canonical signature is
        // the only place where social logos are rendered, so templates cannot
        // accumulate duplicated icon clusters while loading/saving.
        $html = $this->stripAllPlyrcardSocialAnchors($html);
        $html = preg_replace('/<\s*(style|script|head|title|meta|link|svg|noscript)[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $html) ?? $html;
        $html = preg_replace('/<\s*svg\b[^>]*>.*?<\s*\/\s*svg\s*>/is', '', $html) ?? $html;
        $html = preg_replace('/<\/?\s*(html|body)\b[^>]*>/i', '', $html) ?? $html;

        $document = $this->loadTemplateDom($html);
        if (! $document) {
            return $this->sanitizeTemplateHtml($html);
        }

        foreach (['style', 'script', 'svg', 'head', 'title', 'meta', 'link', 'noscript'] as $tag) {
            while (($nodes = $document->getElementsByTagName($tag))->length > 0) {
                $node = $nodes->item(0);
                $node?->parentNode?->removeChild($node);
            }
        }

        $allowed = ['p','div','br','ul','ol','li','blockquote','strong','b','em','i','u','a','img','h1','h2','h3','h4','span'];
        $walk = function ($node) use (&$walk, $allowed, $document): void {
            if ($node instanceof \DOMElement) {
                $tag = strtolower($node->tagName);
                if (! in_array($tag, $allowed, true)) {
                    $fragment = $document->createDocumentFragment();
                    while ($node->firstChild) {
                        $fragment->appendChild($node->firstChild);
                    }
                    $node->parentNode?->replaceChild($fragment, $node);
                    return;
                }

                foreach (iterator_to_array($node->attributes ?? []) as $attribute) {
                    $name = strtolower($attribute->name);
                    if (str_starts_with($name, 'on') || in_array($name, ['class','id','width','height'], true)) {
                        $node->removeAttribute($attribute->name);
                    }
                }

                if ($tag === 'a') {
                    $href = trim((string) $node->getAttribute('href'));
                    $node->setAttribute('href', $href !== '' ? $href : '#');
                    $node->setAttribute('target', '_blank');
                    $node->removeAttribute('style');
                    if (trim((string) $node->textContent) === '') {
                        $node->nodeValue = 'Open link';
                    }
                }

                if ($tag === 'img') {
                    $src = trim((string) $node->getAttribute('src'));
                    if ($src === '') {
                        $node->parentNode?->removeChild($node);
                        return;
                    }
                    $node->setAttribute('src', $src);
                    $node->setAttribute('alt', $node->getAttribute('alt') ?: 'Image');
                    $node->setAttribute('style', 'max-width:100%;height:auto;border-radius:12px;display:block;');
                }
            }

            if ($node->hasChildNodes()) {
                foreach (iterator_to_array($node->childNodes) as $child) {
                    $walk($child);
                }
            }
        };

        $root = $document->getElementsByTagName('body')->item(0) ?: $document->documentElement;
        if ($root) {
            $walk($root);
        }

        $out = '';
        $container = $document->getElementsByTagName('body')->item(0) ?: $document->documentElement;
        if ($container) {
            foreach ($container->childNodes as $child) {
                $out .= $document->saveHTML($child);
            }
        }

        $out = trim($out);
        return $out !== '' ? $out : $this->templateTextToHtml(trim(strip_tags($html)));
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
            'HighlightLink' => $this->userHighlightUrlForCoach($coach, '[Highlight Link]'),
            'ProfileLink' => $this->userProfileUrlForCoach($coach, '[Profile Link]'),
            'InstagramLink' => $this->userSocialUrlForCoach('instagram', $coach, '#'),
            'TwitterLink' => $this->userSocialUrlForCoach('x', $coach, '#'),
            'XLink' => $this->userSocialUrlForCoach('x', $coach, '#'),
            'YoutubeLink' => $this->userSocialUrlForCoach('youtube', $coach, '#'),
            'YouTubeLink' => $this->userSocialUrlForCoach('youtube', $coach, '#'),
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

    protected function userProfileUrlForCoach(array $coach, string $fallback = ''): string
    {
        return $this->appendRecruitingTrackingQuery(
            $this->userProfileUrl($fallback),
            'website',
            'profile_view',
            $coach,
            'profile'
        );
    }

    protected function userHighlightUrlForCoach(array $coach, string $fallback = ''): string
    {
        return $this->appendRecruitingTrackingQuery(
            $this->userHighlightUrl($fallback),
            'youtube',
            'profile_view',
            $coach,
            'highlights'
        );
    }

    protected function userSocialUrlForCoach(string $platform, array $coach, string $fallback = ''): string
    {
        $platform = strtolower(trim($platform));
        $platform = $platform === 'twitter' ? 'x' : $platform;

        return $this->appendRecruitingTrackingQuery(
            $this->userSocialUrl($platform, $fallback),
            $platform,
            'profile_view',
            $coach,
            $platform
        );
    }

    protected function appendRecruitingTrackingQuery(string $url, string $platform, string $eventType, array $coach = [], string $content = 'profile'): string
    {
        $url = trim($url);
        if ($url === '' || $url === '#' || str_starts_with($url, '[')) {
            return $url;
        }

        $user = Auth::user();
        $platform = strtolower(trim($platform));
        $platform = $platform === 'twitter' ? 'x' : $platform;
        $eventType = strtolower(trim($eventType)) ?: 'profile_view';

        $fragment = '';
        $fragmentPosition = strpos($url, '#');
        if ($fragmentPosition !== false) {
            $fragment = substr($url, $fragmentPosition);
            $url = substr($url, 0, $fragmentPosition);
        }

        $contactId = $coach['id'] ?? $coach['contact_id'] ?? $coach['ghl_contact_id'] ?? null;
        $businessId = $coach['business_id'] ?? $coach['ghl_business_id'] ?? null;
        $schoolName = $coach['school'] ?? $coach['school_name'] ?? $coach['company_name'] ?? null;
        $trackingPayload = array_filter([
            'u' => $user?->id,
            'c' => $contactId,
            'b' => $businessId,
            'school' => $schoolName,
            'p' => $platform,
            'e' => $eventType,
            's' => 'coach_database_email',
            'd' => $url,
            'ts' => now()->timestamp,
        ], fn ($value): bool => ! is_null($value) && trim((string) $value) !== '');
        $trackingContext = rtrim(strtr(base64_encode(json_encode($trackingPayload, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
        $trackingSignature = substr(hash_hmac('sha256', $trackingContext, (string) config('app.key', 'plyrcard')), 0, 24);

        $params = [
            'utm_source' => 'plyrcard_recruiting',
            'utm_medium' => 'email',
            'utm_campaign' => 'coach_database',
            'utm_content' => $content,
            'rc_event' => $eventType,
            'rc_platform' => $platform,
            'rc_ctx' => $trackingContext,
            'rc_sig' => $trackingSignature,
            'rc_athlete_id' => $user?->id,
            'rc_contact_id' => $contactId,
            'rc_ghl_contact_id' => $contactId,
            'rc_business_id' => $businessId,
            'rc_school' => $schoolName,
        ];

        $params = array_filter($params, fn ($value): bool => ! is_null($value) && trim((string) $value) !== '');
        if (empty($params)) {
            return $url . $fragment;
        }

        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . http_build_query($params, '', '&', PHP_QUERY_RFC3986) . $fragment;
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

    protected function applyTagToCachedContacts(array $contactIds, string $tag, string $type, bool $hydrateComponent = true): void
    {
        $selectedSchoolId = $this->selectedSchoolId;
        $snapshot = Cache::get($this->activeCacheKey(), $this->emptySnapshot());
        $tag = trim($tag);
        $lowerTag = strtolower($tag);
        $type = strtolower(trim($type)) === 'remove' ? 'remove' : 'add';

        if (str_starts_with($lowerTag, 'plyrcard:list:')) {
            $key = Str::after($lowerTag, 'plyrcard:list:');
            if ($key !== '') {
                $snapshot['custom_list_tags'][$key] = [
                    'key' => $key,
                    'label' => Str::of($key)->replace('-', ' ')->headline()->toString(),
                    'tag' => $tag,
                    'custom' => true,
                ];
            }
        }

        $contactIds = collect($contactIds)
            ->map(fn ($id): string => trim((string) $id))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $ids = array_flip($contactIds);

        $service = app(CoachDatabaseService::class);
        $favoriteTag = strtolower($service->favoriteSchoolTag());
        $savedTag = strtolower($service->savedSchoolTag());

        $snapshot['coaches'] = collect($snapshot['coaches'] ?? [])
            ->filter(fn ($coach): bool => is_array($coach))
            ->map(function (array $coach) use ($ids, $tag, $lowerTag, $type, $favoriteTag, $savedTag): array {
                $coachId = trim((string) ($coach['id'] ?? $coach['contact_id'] ?? ''));
                if ($coachId === '' || ! isset($ids[$coachId])) {
                    return $coach;
                }

                $tags = collect($coach['tags'] ?? [])
                    ->map(fn ($value): string => trim((string) $value))
                    ->filter()
                    ->values();

                if ($type === 'add') {
                    if (! $tags->contains(fn (string $existing): bool => strtolower($existing) === $lowerTag)) {
                        $tags->push($tag);
                    }
                } else {
                    $tags = $tags
                        ->reject(fn (string $existing): bool => strtolower($existing) === $lowerTag)
                        ->values();
                }

                $coach['tags'] = $tags->all();
                $hasTag = $type === 'add';

                if ($lowerTag === $savedTag) {
                    $coach['is_saved_school'] = $hasTag;
                } elseif ($lowerTag === $favoriteTag) {
                    $coach['is_favorite_school'] = $hasTag;
                }

                return $coach;
            })
            ->values()
            ->all();

        $coachById = collect($snapshot['coaches'] ?? [])
            ->filter(fn ($coach): bool => is_array($coach) && filled($coach['id'] ?? $coach['contact_id'] ?? null))
            ->keyBy(fn (array $coach): string => trim((string) ($coach['id'] ?? $coach['contact_id'] ?? '')));

        $listRow = collect($snapshot['lists'] ?? [])->first(function ($row) use ($lowerTag): bool {
            return is_array($row) && strtolower(trim((string) ($row['tag'] ?? ''))) === $lowerTag;
        });
        $listKey = is_array($listRow) ? trim((string) ($listRow['key'] ?? '')) : '';
        if ($listKey === '' && str_starts_with($lowerTag, 'plyrcard:list:')) {
            $listKey = Str::after($lowerTag, 'plyrcard:list:');
        }

        $snapshot['schools'] = collect($snapshot['schools'] ?? [])
            ->filter(fn ($school): bool => is_array($school))
            ->map(function (array $school) use ($coachById, $ids, $lowerTag, $favoriteTag, $savedTag, $listKey): array {
                $schoolCoachIds = collect($school['coach_ids'] ?? [])
                    ->map(fn ($id): string => trim((string) $id))
                    ->filter()
                    ->unique()
                    ->values();

                if ($schoolCoachIds->isEmpty() || ! $schoolCoachIds->contains(fn (string $id): bool => isset($ids[$id]))) {
                    return $school;
                }

                $schoolCoaches = $schoolCoachIds
                    ->map(fn (string $id) => $coachById->get($id))
                    ->filter(fn ($coach): bool => is_array($coach))
                    ->values();

                $schoolHasTag = fn (string $needle): bool => $schoolCoaches->contains(function (array $coach) use ($needle): bool {
                    return collect($coach['tags'] ?? [])->contains(
                        fn ($existing): bool => strtolower(trim((string) $existing)) === $needle
                    );
                });

                if ($lowerTag === $favoriteTag) {
                    $school['is_favorite'] = $schoolHasTag($favoriteTag);
                }

                if ($lowerTag === $savedTag) {
                    $school['is_saved'] = $schoolHasTag($savedTag);
                }

                if ($listKey !== '') {
                    $listKeys = collect($school['list_keys'] ?? [])
                        ->map(fn ($key): string => trim((string) $key))
                        ->filter()
                        ->unique()
                        ->values();

                    if ($schoolHasTag($lowerTag)) {
                        if (! $listKeys->contains($listKey)) {
                            $listKeys->push($listKey);
                        }
                    } else {
                        $listKeys = $listKeys->reject(fn (string $key): bool => $key === $listKey)->values();
                    }

                    $school['list_keys'] = $listKeys->all();
                }

                return $school;
            })
            ->values()
            ->all();

        if ($listKey !== '') {
            $schools = collect($snapshot['schools'] ?? []);
            $snapshot['lists'] = collect($snapshot['lists'] ?? [])
                ->map(function ($row) use ($schools, $listKey, $lowerTag) {
                    if (! is_array($row)) {
                        return $row;
                    }

                    $rowKey = trim((string) ($row['key'] ?? ''));
                    $rowTag = strtolower(trim((string) ($row['tag'] ?? '')));
                    if ($rowKey !== $listKey && $rowTag !== $lowerTag) {
                        return $row;
                    }

                    $items = $schools
                        ->filter(fn (array $school): bool => in_array($listKey, $school['list_keys'] ?? [], true))
                        ->values();

                    $row['schools_count'] = $items->count();
                    $row['coaches_count'] = $items->sum(fn (array $school): int => (int) ($school['coach_count'] ?? $school['coaches_count'] ?? 0));
                    $row['schools'] = $items
                        ->map(fn (array $school): array => [
                            'id' => $school['id'] ?? $school['business_id'] ?? null,
                            'name' => $school['name'] ?? null,
                            'logo_url' => $school['logo_url'] ?? $school['school_logo_url'] ?? $school['business_logo_url'] ?? null,
                            'conference' => $school['conference'] ?? null,
                            'division' => $school['division'] ?? null,
                            'coach_count' => (int) ($school['coach_count'] ?? $school['coaches_count'] ?? 0),
                        ])
                        ->all();

                    return $row;
                })
                ->values()
                ->all();
        }

        $schools = collect($snapshot['schools'] ?? []);
        $snapshot['stats']['saved_schools'] = $schools->filter(fn (array $school): bool => (bool) ($school['is_saved'] ?? false))->count();
        $snapshot['stats']['favorite_schools'] = $schools->filter(fn (array $school): bool => (bool) ($school['is_favorite'] ?? false))->count();
        $snapshot['tag_synced_at'] = now()->toDateTimeString();

        // Tag actions do not need a complete school/contact reconciliation. The
        // previous implementation rebuilt every school, list and dashboard stat
        // inside the click request, which caused drawer flicker and long waits.
        $this->storeSnapshot($snapshot);

        if ($hydrateComponent) {
            $this->hydrateFromSnapshot($snapshot);
            $this->selectedSchoolId = $selectedSchoolId;
            $this->dispatch('rc-school-action-complete', schoolId: $selectedSchoolId);
        }
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
        $school = collect($this->allSchools())->first(function (array $row) use ($schoolId): bool {
            return (string) ($row['id'] ?? '') === $schoolId
                || (string) ($row['business_id'] ?? '') === $schoolId;
        });

        if (! is_array($school)) {
            return [];
        }

        return collect($this->coachesForSchoolSearch($school))
            ->pluck('id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }


    protected function coachTrackedLinkClickTotal(array $coach): int
    {
        $platformTotal = (int) ($coach['email_click_count'] ?? 0)
            + (int) ($coach['website_click_count'] ?? 0)
            + (int) ($coach['instagram_click_count'] ?? 0)
            + (int) ($coach['youtube_click_count'] ?? 0)
            + (int) ($coach['x_click_count'] ?? 0);

        if ($platformTotal > 0) {
            return $platformTotal;
        }

        return max(
            (int) ($coach['trigger_link_click_count'] ?? 0),
            (bool) ($coach['trigger_link_clicked'] ?? false) ? 1 : 0,
        );
    }

    protected function coachTrackedClickTotal(array $coach): int
    {
        return $this->coachTrackedProfileViewTotal($coach) + $this->coachTrackedLinkClickTotal($coach);
    }

    protected function coachTrackedProfileViewTotal(array $coach): int
    {
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
    }

    protected function coachTrackingIdentity(array $coach): string
    {
        // Email is the strongest cross-source key because the same contact can arrive
        // once as a business association and once as a general contact row with
        // differently named id fields.
        $email = strtolower(trim((string) ($coach['email'] ?? '')));
        if ($email !== '') {
            return 'email:' . $email;
        }

        $contactId = strtolower(trim((string) ($coach['contact_id'] ?? $coach['id'] ?? $coach['ghl_contact_id'] ?? '')));
        if ($contactId !== '') {
            return 'contact:' . $contactId;
        }

        return 'fallback:' . md5(strtolower(trim((string) ($coach['name'] ?? ''))) . '|' . strtolower(trim((string) ($coach['school'] ?? $coach['company_name'] ?? ''))));
    }

    /**
     * Return one canonical tracking row per coach/contact. The dataset can contain
     * the same contact from both the school association feed and the full contacts
     * feed. We merge counters by MAX per field instead of summing duplicate source
     * rows, preventing inflated dashboard totals while preserving complementary
     * platform counters.
     */
    protected function trackingCoaches(): array
    {
        if (is_array($this->trackingCoachesMemo)) {
            return $this->trackingCoachesMemo;
        }

        $numericFields = [
            'view_profile_total', 'profile_view_count',
            'view_profile_website', 'view_profile_instagram', 'view_profile_youtube',
            'view_profile_x', 'view_profile_email_link', 'view_profile_qr',
            'website_click_count', 'instagram_click_count', 'youtube_click_count',
            'x_click_count', 'email_click_count', 'trigger_link_click_count',
            'coach_reply_count', 'email_sent_count', 'email_open_count',
        ];
        $booleanFields = ['viewed_profile', 'trigger_link_clicked', 'replied'];
        $fillFields = [
            'id', 'contact_id', 'ghl_contact_id', 'email', 'phone', 'name',
            'first_name', 'last_name', 'title', 'school', 'school_name',
            'company_name', 'business_name', 'business_id', 'company_id', 'school_id',
            'conference', 'division', 'school_logo_url', 'business_logo_url', 'logo_url',
            'last_clicked_platform', 'last_clicked_url',
        ];
        $dateFields = ['last_profile_view_at', 'last_clicked_at', 'last_reply_at', 'date_updated', 'updated_at'];

        $this->trackingCoachesMemo = collect($this->allCoaches())
            ->filter(fn ($coach): bool => is_array($coach))
            ->groupBy(fn (array $coach): string => $this->coachTrackingIdentity($coach))
            ->map(function (Collection $rows) use ($numericFields, $booleanFields, $fillFields, $dateFields): array {
                $rows = $rows->values();
                $base = (array) ($rows->first() ?? []);

                foreach ($fillFields as $field) {
                    $value = $rows->pluck($field)
                        ->first(fn ($candidate): bool => ! is_null($candidate) && trim((string) $candidate) !== '');
                    if (! is_null($value) && trim((string) $value) !== '') {
                        $base[$field] = $value;
                    }
                }

                foreach ($numericFields as $field) {
                    $base[$field] = (int) $rows->max(fn (array $row): int => max(0, (int) ($row[$field] ?? 0)));
                }

                foreach ($booleanFields as $field) {
                    $base[$field] = $rows->contains(fn (array $row): bool => (bool) ($row[$field] ?? false));
                }

                foreach ($dateFields as $field) {
                    $latestValue = null;
                    $latestTimestamp = 0;
                    foreach ($rows as $row) {
                        $candidate = $row[$field] ?? null;
                        if (! $candidate) {
                            continue;
                        }
                        try {
                            $timestamp = \Carbon\Carbon::parse($candidate)->getTimestamp();
                        } catch (\Throwable $exception) {
                            $timestamp = 0;
                        }
                        if ($timestamp >= $latestTimestamp) {
                            $latestTimestamp = $timestamp;
                            $latestValue = $candidate;
                        }
                    }
                    if ($latestValue) {
                        $base[$field] = $latestValue;
                    }
                }

                return $base;
            })
            ->values()
            ->all();

        return $this->trackingCoachesMemo;
    }

    protected function coachTrackingSchoolKey(array $coach): string
    {
        $businessId = strtolower(trim((string) ($coach['business_id'] ?? $coach['company_id'] ?? $coach['school_id'] ?? '')));
        if ($businessId !== '') {
            return 'business:' . $businessId;
        }

        $school = $this->normalizeSchoolMatchKey((string) ($coach['school'] ?? $coach['school_name'] ?? $coach['company_name'] ?? ''));
        return $school !== '' ? 'school:' . $school : '';
    }

    protected function trackingSchoolIdForCoach(array $coach): string
    {
        $businessId = trim((string) ($coach['business_id'] ?? $coach['company_id'] ?? $coach['school_id'] ?? ''));
        $schoolName = trim((string) ($coach['school'] ?? $coach['school_name'] ?? $coach['company_name'] ?? $coach['business_name'] ?? ''));
        $schoolKey = $this->normalizeSchoolMatchKey($schoolName);

        $school = collect($this->allSchools())->first(function (array $row) use ($businessId, $schoolKey): bool {
            $rowBusinessId = trim((string) ($row['business_id'] ?? $row['company_id'] ?? $row['id'] ?? ''));
            if ($businessId !== '' && $rowBusinessId !== '' && strcasecmp($businessId, $rowBusinessId) === 0) {
                return true;
            }

            return $schoolKey !== '' && $this->normalizeSchoolMatchKey((string) ($row['name'] ?? $row['school_name'] ?? $row['company_name'] ?? '')) === $schoolKey;
        });

        if (is_array($school)) {
            $resolved = trim((string) ($school['id'] ?? $school['business_id'] ?? ''));
            if ($resolved !== '') {
                return $resolved;
            }
        }

        if ($businessId !== '') {
            return $businessId;
        }

        return $schoolName !== '' ? md5(strtolower($schoolName)) : '';
    }

    public function getProfileViewRowsProperty(): array
    {
        return collect($this->trackingCoaches())
            ->map(function (array $coach): ?array {
                $views = $this->coachTrackedProfileViewTotal($coach);
                if ($views <= 0) {
                    return null;
                }

                $name = trim((string) ($coach['name'] ?? collect([$coach['first_name'] ?? null, $coach['last_name'] ?? null])->filter()->implode(' '))) ?: 'Known coach contact';
                $school = trim((string) ($coach['school'] ?? $coach['school_name'] ?? $coach['company_name'] ?? ''));
                $initials = strtoupper(collect(preg_split('/\s+/', $name) ?: [])->filter()->map(fn ($part) => mb_substr((string) $part, 0, 1))->take(2)->implode('') ?: 'PV');

                $platforms = collect([
                    'Website' => (int) ($coach['view_profile_website'] ?? 0),
                    'Instagram' => (int) ($coach['view_profile_instagram'] ?? 0),
                    'YouTube' => (int) ($coach['view_profile_youtube'] ?? 0),
                    'X' => (int) ($coach['view_profile_x'] ?? 0),
                    'Email Link' => (int) ($coach['view_profile_email_link'] ?? 0),
                    'QR' => (int) ($coach['view_profile_qr'] ?? 0),
                ])->filter(fn (int $count): bool => $count > 0);

                $primaryPlatform = (string) ($platforms->sortDesc()->keys()->first() ?? 'Profile');
                $breakdown = $platforms->map(fn (int $count, string $label): string => $label . ' ' . number_format($count))->values()->implode(' • ');
                $copy = collect([$school, $breakdown !== '' ? $breakdown : number_format($views) . ' tracked profile ' . Str::plural('view', $views)])
                    ->filter()
                    ->implode(' • ');
                $time = $coach['last_profile_view_at'] ?? $coach['date_updated'] ?? $coach['updated_at'] ?? null;

                try {
                    $timeLabel = $time ? \Carbon\Carbon::parse($time)->diffForHumans() : 'Synced';
                } catch (\Throwable $exception) {
                    $timeLabel = 'Synced';
                }

                return [
                    'coach_id' => $this->coachTrackingIdentity($coach),
                    'school_key' => $this->coachTrackingSchoolKey($coach),
                    'school_id' => $this->trackingSchoolIdForCoach($coach),
                    'school' => $school,
                    'title' => $name,
                    'copy' => $copy !== '' ? $copy : 'Tracked profile view from a known coach contact',
                    'views' => $views,
                    'type' => $primaryPlatform,
                    'logo' => $coach['school_logo_url'] ?? $coach['business_logo_url'] ?? $coach['logo_url'] ?? null,
                    'initials' => $initials,
                    'time' => $time,
                    'time_label' => $timeLabel,
                ];
            })
            ->filter()
            ->unique('coach_id')
            ->sortByDesc(fn (array $row): int => (int) ($row['views'] ?? 0))
            ->values()
            ->map(fn (array $row, int $index): array => array_merge($row, ['rank' => $index + 1]))
            ->all();
    }

    public function getDashboardMetricsProperty(): array
    {
        $stats = $this->stats ?? [];
        $schools = collect($this->allSchools());

        $savedSchools = max(
            (int) ($stats['saved_schools'] ?? 0),
            $schools->filter(fn (array $school): bool => $this->schoolRowHasSavedFlag($school))->count(),
            $this->schoolCountFromListLabels(['saved', 'saved schools']),
        );
        $favoriteSchools = max(
            (int) ($stats['favorite_schools'] ?? 0),
            $schools->filter(fn (array $school): bool => $this->schoolRowHasFavoriteFlag($school))->count(),
            $this->schoolCountFromListLabels(['favorite', 'favorites', 'favorite schools']),
        );

        $profileRows = collect($this->profileViewRows);
        $engagementRows = collect($this->coachEngagementRows);

        $trackedProfileTotal = $profileRows->sum('views');
        if ($trackedProfileTotal === 0) {
            $trackedProfileTotal = (int) ($stats['view_profile_total'] ?? $stats['profile_views'] ?? 0);
        }

        $trackedWebsiteViews = collect($this->trackingCoaches())->sum(fn (array $coach): int => (int) ($coach['view_profile_website'] ?? 0));
        $trackedInstagramViews = collect($this->trackingCoaches())->sum(fn (array $coach): int => (int) ($coach['view_profile_instagram'] ?? 0));
        $trackedYoutubeViews = collect($this->trackingCoaches())->sum(fn (array $coach): int => (int) ($coach['view_profile_youtube'] ?? 0));
        $trackedXViews = collect($this->trackingCoaches())->sum(fn (array $coach): int => (int) ($coach['view_profile_x'] ?? 0));
        $trackedEmailProfileLinks = collect($this->trackingCoaches())->sum(fn (array $coach): int => (int) ($coach['view_profile_email_link'] ?? 0));

        $websiteClicks = $engagementRows->where('platform_key', 'website')->sum('clicks');
        $instagramClicks = $engagementRows->where('platform_key', 'instagram')->sum('clicks');
        $youtubeClicks = $engagementRows->where('platform_key', 'youtube')->sum('clicks');
        $xClicks = $engagementRows->where('platform_key', 'x')->sum('clicks');
        $emailClicks = $engagementRows->where('platform_key', 'email')->sum('clicks');
        $linkClicks = $engagementRows->sum('clicks');

        $profileContactIds = $profileRows->pluck('coach_id')->filter()->unique();
        $linkContactIds = $engagementRows->pluck('coach_id')->filter()->unique();
        $uniqueProfileViewContacts = $profileContactIds->count();
        $uniqueLinkClickContacts = $linkContactIds->count();
        $uniqueContactClicks = $profileContactIds->merge($linkContactIds)->unique()->count();
        $profileViewUniqueSchools = $profileRows->where('school_key', '!=', '')->pluck('school_key')->unique()->count();
        $profileViewSchoolClicks = $profileRows->where('school_key', '!=', '')->sum('views');
        $overallSchoolClicks = $engagementRows->where('school_key', '!=', '')->sum('clicks');
        $schoolsWithClicks = $engagementRows->where('school_key', '!=', '')->pluck('school_key')->unique()->count();
        $ghlContactClicks = $trackedProfileTotal + $linkClicks;

        $emailSentCount = max((int) ($stats['email_sent_count'] ?? 0), (int) ($stats['emails_sent'] ?? 0), (int) ($stats['campaigns_sent'] ?? 0) + (int) ($stats['personal_emails_sent'] ?? 0));
        $emailOpenCount = (int) ($stats['email_open_count'] ?? $stats['email_opens'] ?? 0);
        $coachReplies = (int) ($stats['coach_replies'] ?? $stats['replies'] ?? 0);

        $engagedSchools = $schools->filter(function (array $school): bool {
            return ((int) ($school['replies'] ?? $school['coach_replies'] ?? 0) > 0)
                || ((int) ($school['link_clicks'] ?? $school['trigger_link_clicks'] ?? $school['trigger_clicks'] ?? 0) > 0)
                || ((int) ($school['profile_views'] ?? 0) + (int) ($school['highlight_views'] ?? 0) > 0)
                || ((int) ($school['engagement_score'] ?? 0) > 0);
        })->count();

        return [
            'saved_schools' => $savedSchools,
            'favorite_schools' => $favoriteSchools,
            'engaged_schools' => $engagedSchools,
            'emails_sent' => $emailSentCount,
            'email_sent_count' => $emailSentCount,
            'email_open_count' => $emailOpenCount,
            'email_click_count' => $emailClicks,
            'website_click_count' => $websiteClicks,
            'instagram_click_count' => $instagramClicks,
            'youtube_click_count' => $youtubeClicks,
            'x_click_count' => $xClicks,
            'profile_views' => $trackedProfileTotal,
            'view_profile_total' => $trackedProfileTotal,
            'view_profile_website' => $trackedWebsiteViews,
            'view_profile_instagram' => $trackedInstagramViews,
            'view_profile_youtube' => $trackedYoutubeViews,
            'view_profile_x' => $trackedXViews,
            'view_profile_email_link' => $trackedEmailProfileLinks,
            'profile_view_unique_contact_count' => $uniqueProfileViewContacts,
            'profile_view_unique_school_count' => $profileViewUniqueSchools,
            'profile_view_school_click_count' => $profileViewSchoolClicks,
            'link_clicks' => $linkClicks,
            'trigger_link_clicks' => $linkClicks,
            'unique_contact_clicks' => $uniqueContactClicks,
            'unique_profile_view_contacts' => $uniqueProfileViewContacts,
            'unique_profile_views' => $uniqueProfileViewContacts,
            'unique_link_click_contacts' => $uniqueLinkClickContacts,
            'unique_clicks' => $uniqueContactClicks,
            'contact_link_clicks' => $ghlContactClicks,
            'ghl_contact_clicks' => $ghlContactClicks,
            'overall_school_clicks' => $overallSchoolClicks,
            'school_clicks_total' => $overallSchoolClicks,
            'schools_with_clicks' => $schoolsWithClicks,
            'school_profile_views' => $profileViewSchoolClicks,
            'email_open_rate' => (int) ($stats['email_open_rate'] ?? 0),
            'coach_replies' => $coachReplies,
            'sparks' => $this->dashboardActivitySummary['sparks'] ?? $this->fallbackDashboardSparks($stats),
        ];
    }

    public function getCoachEngagementRowsProperty(): array
    {
        $platforms = [
            'website' => ['label' => 'Website', 'key' => 'website_click_count', 'class' => 'is-blue', 'icon_file' => 'website.png'],
            'instagram' => ['label' => 'Instagram', 'key' => 'instagram_click_count', 'class' => 'is-pink', 'icon_file' => 'instagram.png'],
            'youtube' => ['label' => 'YouTube', 'key' => 'youtube_click_count', 'class' => 'is-red', 'icon_file' => 'youtube.png'],
            'x' => ['label' => 'X', 'key' => 'x_click_count', 'class' => 'is-neutral', 'icon_file' => 'x.png'],
            'email' => ['label' => 'Email link', 'key' => 'email_click_count', 'class' => 'is-coral', 'icon_file' => 'email.png'],
        ];

        return collect($this->trackingCoaches())
            ->flatMap(function (array $coach) use ($platforms): array {
                $rows = [];
                $coachId = $this->coachTrackingIdentity($coach);
                $schoolKey = $this->coachTrackingSchoolKey($coach);
                $schoolId = $this->trackingSchoolIdForCoach($coach);
                $coachName = trim((string) ($coach['name'] ?? collect([$coach['first_name'] ?? null, $coach['last_name'] ?? null])->filter()->implode(' '))) ?: 'Known coach contact';
                $school = trim((string) ($coach['school'] ?? $coach['school_name'] ?? $coach['company_name'] ?? ''));
                $lastPlatform = strtolower(trim((string) ($coach['last_clicked_platform'] ?? '')));
                $lastPlatform = match ($lastPlatform) {
                    'twitter' => 'x',
                    'ig' => 'instagram',
                    'email_link' => 'email',
                    default => $lastPlatform,
                };
                $lastUrl = trim((string) ($coach['last_clicked_url'] ?? ''));
                $lastTime = $coach['last_clicked_at'] ?? $coach['date_updated'] ?? $coach['updated_at'] ?? $coach['last_profile_view_at'] ?? null;

                foreach ($platforms as $platform => $config) {
                    $count = (int) ($coach[$config['key']] ?? 0);
                    if ($count <= 0) {
                        continue;
                    }

                    try {
                        $timeLabel = $lastTime ? \Carbon\Carbon::parse($lastTime)->diffForHumans() : 'Synced';
                    } catch (\Throwable $exception) {
                        $timeLabel = 'Synced';
                    }

                    $rows[] = [
                        'coach_id' => $coachId,
                        'school_key' => $schoolKey,
                        'school_id' => $schoolId,
                        'coach_name' => $coachName,
                        'school' => $school,
                        'title' => $coachName,
                        'copy' => collect([
                            $coachName . ' clicked ' . $config['label'] . ' ' . number_format($count) . ' ' . Str::plural('time', $count),
                            $school,
                        ])->filter()->implode(' • '),
                        'platform' => $config['label'],
                        'platform_key' => $platform,
                        'platform_class' => $config['class'],
                        'platform_icon_file' => $config['icon_file'],
                        'clicks' => $count,
                        'url' => $lastPlatform === $platform ? $lastUrl : '',
                        'time' => $lastTime,
                        'time_label' => $timeLabel,
                    ];
                }

                if (empty($rows)) {
                    $fallback = max((int) ($coach['trigger_link_click_count'] ?? 0), (bool) ($coach['trigger_link_clicked'] ?? false) ? 1 : 0);
                    if ($fallback > 0) {
                        $rows[] = [
                            'coach_id' => $coachId,
                            'school_key' => $schoolKey,
                            'school_id' => $schoolId,
                            'coach_name' => $coachName,
                            'school' => $school,
                            'title' => $coachName,
                            'copy' => collect([$coachName . ' clicked a tracked recruiting link ' . number_format($fallback) . ' ' . Str::plural('time', $fallback), $school])->filter()->implode(' • '),
                            'platform' => 'Tracked link',
                            'platform_key' => 'tracked',
                            'platform_class' => 'is-blue',
                            'platform_icon_file' => 'link.png',
                            'clicks' => $fallback,
                            'url' => $lastUrl,
                            'time' => $lastTime,
                            'time_label' => 'Synced',
                        ];
                    }
                }

                return $rows;
            })
            ->sortByDesc(function (array $row): int {
                $timestamp = 0;
                try {
                    $timestamp = filled($row['time'] ?? null) ? \Carbon\Carbon::parse($row['time'])->getTimestamp() : 0;
                } catch (\Throwable $exception) {
                    $timestamp = 0;
                }

                return ($timestamp * 1000000) + min(999999, max(0, (int) ($row['clicks'] ?? 0)));
            })
            ->take(100)
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

    public function getDashboardMostInterestedSchoolsProperty(): array
    {
        $schools = collect($this->allSchools())
            ->filter(fn ($row): bool => is_array($row))
            ->values();

        $byBusinessId = $schools
            ->flatMap(function (array $school): array {
                $ids = collect([
                    $school['id'] ?? null,
                    $school['business_id'] ?? null,
                    $school['company_id'] ?? null,
                    $school['school_id'] ?? null,
                ])->map(fn ($id): string => strtolower(trim((string) $id)))->filter()->unique();

                return $ids->mapWithKeys(fn (string $id): array => [$id => $school])->all();
            });

        $byName = $schools
            ->filter(fn (array $school): bool => $this->normalizeSchoolMatchKey((string) ($school['name'] ?? $school['school_name'] ?? '')) !== '')
            ->keyBy(fn (array $school): string => $this->normalizeSchoolMatchKey((string) ($school['name'] ?? $school['school_name'] ?? '')));

        $interest = [];

        $resolve = function (array $row) use ($byBusinessId, $byName): ?array {
            $schoolId = strtolower(trim((string) ($row['school_id'] ?? $row['business_id'] ?? $row['company_id'] ?? '')));
            $schoolName = trim((string) ($row['school'] ?? $row['school_name'] ?? $row['company_name'] ?? $row['business_name'] ?? ''));
            $schoolKey = $this->normalizeSchoolMatchKey($schoolName !== '' ? $schoolName : (string) ($row['school_key'] ?? ''));

            $school = ($schoolId !== '' ? $byBusinessId->get($schoolId) : null)
                ?? ($schoolKey !== '' ? $byName->get($schoolKey) : null);

            if (! is_array($school) && $schoolName === '' && $schoolId === '') {
                return null;
            }

            if (! is_array($school)) {
                $school = [
                    'id' => $schoolId !== '' ? $schoolId : md5(strtolower($schoolName)),
                    'business_id' => $schoolId,
                    'name' => $schoolName !== '' ? $schoolName : 'School',
                    'conference' => '',
                    'division' => '',
                    'logo_url' => $row['logo'] ?? $row['logo_url'] ?? null,
                ];
            }

            $canonicalId = trim((string) ($school['id'] ?? $school['business_id'] ?? $school['company_id'] ?? ''));
            if ($canonicalId === '') {
                $canonicalId = md5(strtolower(trim((string) ($school['name'] ?? $schoolName))));
            }

            return ['key' => $canonicalId, 'school' => $school];
        };

        foreach (collect($this->profileViewRows)->filter(fn ($row): bool => is_array($row)) as $row) {
            $resolved = $resolve($row);
            if (! $resolved) {
                continue;
            }

            $key = (string) $resolved['key'];
            $interest[$key] ??= [
                'school' => $resolved['school'],
                'profile_views' => 0,
                'engagement_clicks' => 0,
            ];
            $interest[$key]['profile_views'] += max(0, (int) ($row['views'] ?? 0));
        }

        foreach (collect($this->coachEngagementRows)->filter(fn ($row): bool => is_array($row)) as $row) {
            $resolved = $resolve($row);
            if (! $resolved) {
                continue;
            }

            $key = (string) $resolved['key'];
            $interest[$key] ??= [
                'school' => $resolved['school'],
                'profile_views' => 0,
                'engagement_clicks' => 0,
            ];
            $interest[$key]['engagement_clicks'] += max(0, (int) ($row['clicks'] ?? 0));
        }

        return collect($interest)
            ->map(function (array $row): array {
                $school = is_array($row['school'] ?? null) ? $row['school'] : [];
                $views = max(0, (int) ($row['profile_views'] ?? 0));
                $clicks = max(0, (int) ($row['engagement_clicks'] ?? 0));

                $school['profile_views'] = $views;
                $school['interest_clicks'] = $clicks;
                // Ranking can use click activity as a secondary signal, while
                // the number rendered on the dashboard remains profile views.
                $school['interest_rank_score'] = ($views * 1000000) + $clicks;
                $school['lead_score'] = $school['interest_rank_score'];

                return $school;
            })
            ->filter(fn (array $school): bool => (int) ($school['profile_views'] ?? 0) > 0 || (int) ($school['interest_clicks'] ?? 0) > 0)
            ->sort(function (array $a, array $b): int {
                $views = (int) ($b['profile_views'] ?? 0) <=> (int) ($a['profile_views'] ?? 0);
                if ($views !== 0) {
                    return $views;
                }

                return (int) ($b['interest_clicks'] ?? 0) <=> (int) ($a['interest_clicks'] ?? 0);
            })
            ->take(5)
            ->values()
            ->all();
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

    protected function prependDashboardActivity(array $activity): void
    {
        $activity['time'] = $activity['time'] ?? now()->toIso8601String();
        $this->dashboardRecentActivity = collect([$activity])
            ->merge($this->dashboardRecentActivity ?? [])
            ->filter(fn ($item): bool => is_array($item))
            ->map(fn (array $item): ?array => $this->normalizeDashboardActivityRow($item))
            ->filter()
            ->unique(fn (array $item): string => $this->dashboardActivityIdentity($item))
            ->sortByDesc(fn (array $item): int => (int) ($item['_timestamp'] ?? 0))
            ->take(30)
            ->map(function (array $item): array {
                unset($item['_timestamp']);
                return $item;
            })
            ->values()
            ->all();

        $this->persistDashboardActivityRows(Auth::user(), $this->dashboardRecentActivity);
    }

    protected function conversationDashboardActivityRows(): array
    {
        return collect($this->conversations ?? [])
            ->filter(fn ($conversation): bool => is_array($conversation))
            ->map(function (array $conversation): array {
                $name = trim((string) ($conversation['contact_name'] ?? $conversation['name'] ?? $conversation['coach_name'] ?? 'Coach contact')) ?: 'Coach contact';
                $direction = strtolower(trim((string) (
                    $conversation['last_message_direction']
                    ?? $conversation['lastMessageDirection']
                    ?? $conversation['direction']
                    ?? $conversation['message_direction']
                    ?? $conversation['last_message_type']
                    ?? ''
                )));
                $outbound = in_array($direction, ['outbound', 'sent', 'outgoing', 'out'], true);
                $inbound = in_array($direction, ['inbound', 'received', 'incoming', 'in'], true);
                $title = $outbound
                    ? 'Email sent to ' . $name
                    : ($inbound ? 'Email received from ' . $name : 'Email activity with ' . $name);

                return [
                    'type' => $outbound ? 'email_outbound' : ($inbound ? 'email_inbound' : 'conversation'),
                    'title' => $title,
                    'copy' => $conversation['last_message'] ?? $conversation['snippet'] ?? 'Recruiting email activity',
                    'time' => $conversation['last_message_at'] ?? $conversation['updated_at'] ?? null,
                    'conversation_id' => (string) ($conversation['id'] ?? ''),
                    'url' => \App\Filament\Pages\CoachDatabaseConversations::getUrl(),
                ];
            })
            ->values()
            ->all();
    }

    protected function localCoachDashboardActivityRows(): array
    {
        $platforms = [
            'website' => ['label' => 'Website', 'key' => 'website_click_count'],
            'instagram' => ['label' => 'Instagram', 'key' => 'instagram_click_count'],
            'youtube' => ['label' => 'YouTube', 'key' => 'youtube_click_count'],
            'x' => ['label' => 'X', 'key' => 'x_click_count'],
            'email' => ['label' => 'Email link', 'key' => 'email_click_count'],
        ];

        return collect($this->trackingCoaches())
            ->flatMap(function (array $coach) use ($platforms): array {
                $rows = [];
                $name = trim((string) ($coach['name'] ?? collect([$coach['first_name'] ?? null, $coach['last_name'] ?? null])->filter()->implode(' '))) ?: 'Coach contact';
                $school = trim((string) ($coach['school'] ?? $coach['school_name'] ?? $coach['company_name'] ?? ''));
                $coachId = $this->coachTrackingIdentity($coach);
                $schoolId = $this->trackingSchoolIdForCoach($coach);
                $clickTime = $coach['last_clicked_at'] ?? $coach['date_updated'] ?? $coach['updated_at'] ?? null;
                $viewTime = $coach['last_profile_view_at'] ?? $coach['date_updated'] ?? $coach['updated_at'] ?? null;

                if ((bool) ($coach['replied'] ?? false) || (int) ($coach['coach_reply_count'] ?? 0) > 0) {
                    $rows[] = [
                        'type' => 'coach_reply',
                        'title' => $name . ' replied',
                        'copy' => collect([$school, 'Inbound coach reply'])->filter()->implode(' • '),
                        'time' => $coach['last_reply_at'] ?? $clickTime,
                        'coach_id' => $coachId,
                        'school_id' => $schoolId,
                        'url' => \App\Filament\Pages\CoachDatabaseConversations::getUrl(),
                    ];
                }

                $clickCounts = collect($platforms)->mapWithKeys(fn (array $config, string $key): array => [$key => (int) ($coach[$config['key']] ?? 0)]);
                $lastPlatform = strtolower(trim((string) ($coach['last_clicked_platform'] ?? '')));
                $lastPlatform = match ($lastPlatform) {
                    'twitter' => 'x',
                    'ig' => 'instagram',
                    'email_link' => 'email',
                    default => $lastPlatform,
                };
                if (! isset($platforms[$lastPlatform]) || (int) $clickCounts->get($lastPlatform, 0) <= 0) {
                    $lastPlatform = (string) ($clickCounts->sortDesc()->keys()->first() ?? '');
                }

                if ($lastPlatform !== '' && (int) $clickCounts->get($lastPlatform, 0) > 0) {
                    $count = (int) $clickCounts->get($lastPlatform);
                    $label = $platforms[$lastPlatform]['label'];
                    $rows[] = [
                        'type' => $lastPlatform === 'email' ? 'email_click' : 'social_click_' . $lastPlatform,
                        'title' => $name . ' clicked ' . $label,
                        'copy' => collect([$school, number_format($count) . ' total ' . $label . ' ' . Str::plural('click', $count)])->filter()->implode(' • '),
                        'time' => $clickTime,
                        'coach_id' => $coachId,
                        'school_id' => $schoolId,
                        'platform_key' => $lastPlatform,
                        'url' => $schoolId !== '' ? '#' : \App\Filament\Pages\CoachDatabaseCoaches::getUrl(),
                    ];
                }

                $views = $this->coachTrackedProfileViewTotal($coach);
                if ($views > 0) {
                    $rows[] = [
                        'type' => 'profile_view',
                        'title' => $name . ' viewed your profile',
                        'copy' => collect([$school, number_format($views) . ' total profile ' . Str::plural('view', $views)])->filter()->implode(' • '),
                        'time' => $viewTime,
                        'coach_id' => $coachId,
                        'school_id' => $schoolId,
                        'url' => $schoolId !== '' ? '#' : \App\Filament\Pages\CoachDatabaseCoaches::getUrl(),
                    ];
                }

                return $rows;
            })
            ->filter(fn ($row): bool => is_array($row))
            ->sortByDesc(function (array $item): int {
                try {
                    return filled($item['time'] ?? null) ? \Carbon\Carbon::parse($item['time'])->getTimestamp() : 0;
                } catch (\Throwable $exception) {
                    return 0;
                }
            })
            ->take(50)
            ->values()
            ->all();
    }

    public function getDashboardRecentActivityProperty(): array
    {
        $user = Auth::user();
        $baseRows = collect($this->dashboardRecentActivity ?? [])
            ->merge($this->cachedDashboardActivityRows($user))
            ->merge($this->localCoachDashboardActivityRows())
            ->merge($this->conversationDashboardActivityRows())
            ->filter(fn ($item): bool => is_array($item));

        $metrics = $this->dashboardMetrics;
        $hasSpecificEmailActivity = $baseRows->contains(fn (array $item): bool => str_contains(strtolower((string) ($item['type'] ?? '')), 'email'));
        $hasSpecificProfileActivity = $baseRows->contains(fn (array $item): bool => str_contains(strtolower((string) ($item['type'] ?? '')), 'profile'));

        if (! $hasSpecificEmailActivity && (int) ($metrics['emails_sent'] ?? 0) > 0) {
            $baseRows->push([
                'type' => 'email_outbound',
                'title' => 'Recruiting emails sent',
                'copy' => number_format((int) ($metrics['emails_sent'] ?? 0)) . ' outbound email ' . Str::plural('message', (int) ($metrics['emails_sent'] ?? 0)) . ' recorded.',
                'time' => $this->cachedAt,
                'url' => \App\Filament\Pages\CoachDatabaseConversations::getUrl(),
            ]);
        }

        if (! $hasSpecificProfileActivity && (int) ($metrics['profile_views'] ?? 0) > 0) {
            $baseRows->push([
                'type' => 'profile_view',
                'title' => 'Profile views recorded',
                'copy' => number_format((int) ($metrics['profile_views'] ?? 0)) . ' player website/profile views from ' . number_format((int) ($metrics['unique_contact_clicks'] ?? 0)) . ' unique coach contacts.',
                'time' => $this->cachedAt,
                'url' => \App\Filament\Pages\CoachDatabaseCoaches::getUrl(),
            ]);
        }

        return $baseRows
            ->map(fn (array $item): ?array => $this->normalizeDashboardActivityRow($item))
            ->filter()
            ->unique(fn (array $item): string => $this->dashboardActivityIdentity($item))
            ->sortByDesc(fn (array $item): int => (int) ($item['_timestamp'] ?? 0))
            ->take(8)
            ->map(function (array $item): array {
                unset($item['_timestamp']);
                $item['copy'] = Str::limit((string) ($item['copy'] ?? ''), 220);
                return $item;
            })
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
        // Lightweight display hydration only. Do not attach the full coach roster
        // to every school card/list option because Livewire serializes the result.
        $count = max(
            (int) ($school['coach_count'] ?? 0),
            (int) ($school['coaches_count'] ?? 0),
            $this->coachCountForSchoolSearch($school)
        );

        $school['coach_count'] = $count;
        $school['coaches_count'] = $count;

        if (blank(data_get($school, 'head_coach.name'))) {
            foreach ($this->coachesForSchoolSearch($school) as $coach) {
                if (! is_array($coach)) {
                    continue;
                }

                if ($this->isHeadCoachTitle((string) ($coach['title'] ?? $coach['position'] ?? ''))) {
                    $school['head_coach'] = $coach;
                    break;
                }

                $school['head_coach'] ??= $coach;
            }
        }

        unset($school['coaches'], $school['staff'], $school['coaching_staff'], $school['contacts']);

        return $school;
    }


    public function getFavoriteSchoolsProperty(): array { return $this->filterSchoolsForSearch(collect($this->allSchools())->filter(fn (array $school): bool => $this->schoolRowHasFavoriteFlag($school)), $this->favoriteSchoolSearch !== '' ? $this->favoriteSchoolSearch : $this->search)->take((int) config('coach-database-sync.ui.school_row_cap', 96))->values()->all(); }
    public function getFavoriteCoachesProperty(): array { return collect($this->allCoaches())->filter(fn (array $coach): bool => (bool) ($coach['is_favorite_coach'] ?? false))->take(80)->values()->all(); }


    public function getSavedSchoolsProperty(): array
    {
        return $this->filterSchoolsForSearch(collect($this->allSchools())->filter(fn (array $school): bool => $this->schoolRowHasSavedFlag($school)), $this->favoriteSchoolSearch !== '' ? $this->favoriteSchoolSearch : $this->search)->take((int) config('coach-database-sync.ui.school_row_cap', 96))->values()->all();
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
            ->take((int) config('coach-database-sync.ui.school_row_cap', 96))
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
            ->take((int) config('coach-database-sync.ui.coach_row_cap', 120))
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
                    ->body($this->templateErrorMessage($result, 'Unable to upload image to Recruiting Center media.'))
                    ->danger()
                    ->send();
                return;
            }

            $this->composeGraphicUrl = trim((string) $result['url']);
        } catch (\Throwable $exception) {
            $this->composeGraphicUpload = null;
            Notification::make()->title('Compose Email')->body('Unable to upload image to Recruiting Center media.')->danger()->send();
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

    public function composeEmailCoach(string $coachId): void
    {
        $coachId = trim($coachId);
        if ($coachId === '') {
            return;
        }

        $this->redirect($this->pageUrl('compose') . '?coach=' . urlencode($coachId), navigate: true);
    }

    public function selectComposeCoach(string $coachId): void
    {
        $coachId = trim($coachId);
        if ($coachId === '') {
            return;
        }

        $coach = collect($this->allCoaches())->first(function (array $row) use ($coachId): bool {
            return (string) ($row['id'] ?? '') === $coachId
                || (string) ($row['contact_id'] ?? '') === $coachId
                || (string) ($row['ghl_contact_id'] ?? '') === $coachId;
        });

        if (! is_array($coach)) {
            $this->campaignTargetMode = 'coaches';
            $this->campaignCoachIds = [$coachId];
            $this->campaignHeadCoachOnly = false;
            $this->composeChooseCoachesOpen = true;
            return;
        }

        $resolvedCoachId = (string) ($coach['id'] ?? $coachId);
        $this->campaignTargetMode = 'coaches';
        $this->campaignCoachIds = [$resolvedCoachId];
        $this->campaignHeadCoachOnly = false;
        $this->composeChooseCoachesOpen = true;

        $businessId = trim((string) ($coach['business_id'] ?? $coach['company_id'] ?? $coach['ghl_business_id'] ?? ''));
        $schoolName = trim((string) ($coach['school'] ?? $coach['company_name'] ?? $coach['school_name'] ?? ''));

        $school = collect($this->allSchools())->first(function (array $row) use ($businessId, $schoolName): bool {
            $rowBusinessId = trim((string) ($row['business_id'] ?? $row['company_id'] ?? $row['ghl_business_id'] ?? $row['id'] ?? ''));
            $rowName = trim((string) ($row['name'] ?? $row['school'] ?? $row['company_name'] ?? ''));

            return ($businessId !== '' && $rowBusinessId === $businessId)
                || ($schoolName !== '' && strcasecmp($rowName, $schoolName) === 0);
        });

        if (is_array($school) && filled($school['id'] ?? null)) {
            $this->campaignSchoolId = (string) $school['id'];
            $this->composeSchoolSearch = '';
        }

        $coachName = trim((string) ($coach['name'] ?? 'Coach'));
        $first = trim(explode(' ', $coachName)[0] ?? 'Coach') ?: 'Coach';
        if (trim((string) $this->campaignBody) === '') {
            $this->campaignBody = '<p>Hi ' . e($first) . ',</p><p><br></p>';
        }
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


    public function startAddScheduleEvent(): void
    {
        $this->resetScheduleForm();
        $this->showScheduleForm = true;
    }

    public function cancelScheduleEvent(): void
    {
        $this->resetScheduleForm();
        $this->showScheduleForm = false;
    }

    public function editScheduleEvent(int $scheduleId): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $schedule = \App\Models\Schedule::query()
            ->where(function ($query) use ($user) {
                $query->where('created_by_user_id', $user->id)
                    ->orWhereHas('users', fn ($q) => $q->where('users.id', $user->id));
            })
            ->find($scheduleId);

        if (! $schedule) {
            Notification::make()->title('My Schedule')->body('Schedule event was not found.')->warning()->send();
            return;
        }

        $this->editingScheduleId = $schedule->id;
        $this->scheduleEventType = (string) ($schedule->status ?: 'Game');
        $this->scheduleDate = optional($schedule->game_date)->format('Y-m-d') ?: '';
        $this->scheduleTime = $schedule->game_time ? \Illuminate\Support\Carbon::parse($schedule->game_time)->format('H:i') : '';
        $this->scheduleOpponent = (string) ($schedule->opponent ?: $schedule->title);
        $this->scheduleLocation = (string) $schedule->location;
        $this->scheduleVenue = (string) $schedule->venue;
        $this->showScheduleForm = true;
    }

    public function saveScheduleEvent(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        if (blank($this->scheduleDate) || blank($this->scheduleOpponent)) {
            Notification::make()->title('My Schedule')->body('Add a date and opponent/event name.')->danger()->send();
            return;
        }

        $payload = [
            'created_by_user_id' => $user->id,
            'title' => trim($this->scheduleOpponent),
            'opponent' => trim($this->scheduleOpponent),
            'game_date' => $this->scheduleDate,
            'game_time' => $this->scheduleTime ?: null,
            'location' => trim($this->scheduleLocation),
            'venue' => trim($this->scheduleVenue),
            'status' => trim($this->scheduleEventType) ?: 'Game',
        ];

        if ($this->editingScheduleId) {
            $schedule = \App\Models\Schedule::query()
                ->where(function ($query) use ($user) {
                    $query->where('created_by_user_id', $user->id)
                        ->orWhereHas('users', fn ($q) => $q->where('users.id', $user->id));
                })
                ->find($this->editingScheduleId);

            if (! $schedule) {
                Notification::make()->title('My Schedule')->body('Schedule event was not found.')->warning()->send();
                return;
            }

            $schedule->fill($payload)->save();
        } else {
            $schedule = \App\Models\Schedule::query()->create($payload);
            $schedule->users()->syncWithoutDetaching([$user->id]);
        }

        $this->resetScheduleForm();
        $this->showScheduleForm = false;
        Notification::make()->title('My Schedule')->body('Schedule event saved.')->success()->send();
    }

    public function deleteScheduleEvent(int $scheduleId): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $schedule = \App\Models\Schedule::query()
            ->where(function ($query) use ($user) {
                $query->where('created_by_user_id', $user->id)
                    ->orWhereHas('users', fn ($q) => $q->where('users.id', $user->id));
            })
            ->find($scheduleId);

        if (! $schedule) {
            Notification::make()->title('My Schedule')->body('Schedule event was not found.')->warning()->send();
            return;
        }

        $schedule->delete();
        Notification::make()->title('My Schedule')->body('Schedule event removed.')->success()->send();
    }

    protected function resetScheduleForm(): void
    {
        $this->editingScheduleId = null;
        $this->scheduleEventType = 'Game';
        $this->scheduleDate = '';
        $this->scheduleTime = '';
        $this->scheduleOpponent = '';
        $this->scheduleLocation = '';
        $this->scheduleVenue = '';
    }

    public function getMyScheduleEventsProperty(): array
    {
        $user = Auth::user();
        if (! $user) {
            return [];
        }

        return \App\Models\Schedule::query()
            ->where(function ($query) use ($user) {
                $query->where('created_by_user_id', $user->id)
                    ->orWhereHas('users', fn ($q) => $q->where('users.id', $user->id));
            })
            ->orderBy('game_date')
            ->orderBy('game_time')
            ->limit(50)
            ->get()
            ->map(function ($schedule): array {
                return [
                    'id' => $schedule->id,
                    'type' => $schedule->status ?: 'Game',
                    'title' => $schedule->title ?: $schedule->opponent,
                    'opponent' => $schedule->opponent ?: $schedule->title,
                    'date' => optional($schedule->game_date)->format('Y-m-d'),
                    'day' => optional($schedule->game_date)->format('D'),
                    'date_number' => optional($schedule->game_date)->format('j'),
                    'time' => $schedule->game_time ? \Illuminate\Support\Carbon::parse($schedule->game_time)->format('g:i A') : '',
                    'location' => $schedule->location,
                    'venue' => $schedule->venue,
                ];
            })
            ->all();
    }

    public function loadNotificationSettings(): void
    {
        $userId = Auth::id();
        if (! $userId) {
            return;
        }

        $stored = Cache::get('coach-database:notification-settings:' . $userId, []);
        if (is_array($stored)) {
            $this->notificationSettings = array_merge($this->notificationSettings, $stored);
        }
    }

    public function toggleNotificationSetting(string $key): void
    {
        if (! array_key_exists($key, $this->notificationSettings)) {
            return;
        }

        $this->notificationSettings[$key] = ! (bool) $this->notificationSettings[$key];
        if (Auth::id()) {
            Cache::put('coach-database:notification-settings:' . Auth::id(), $this->notificationSettings, now()->addYear());
        }
    }

    public function openProfileEditor(): mixed
    {
        if (! Auth::user()) {
            return null;
        }

        // Prefer the athlete/profile page when the app has one, instead of exposing the raw UsersResource edit link.
        foreach ([
            '\\App\\Filament\\Pages\\AthleteProfile',
            '\\App\\Filament\\Pages\\Profile',
            '\\App\\Filament\\Pages\\MyProfile',
        ] as $profilePage) {
            if (class_exists($profilePage) && method_exists($profilePage, 'getUrl')) {
                return redirect()->to($profilePage::getUrl());
            }
        }

        return redirect()->to('/admin/athlete-profile');
    }

    public function openNotificationSettings(): mixed
    {
        return redirect()->to($this->pageUrl('settings'));
    }

    public function openMySchedule(): mixed
    {
        return redirect()->to($this->pageUrl('schedule'));
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
        $normalizedSelectedId = strtolower(trim($selectedId));

        $school = collect($this->allSchools())->first(function (array $item) use ($selectedId, $normalizedSelectedId): bool {
            $name = strtolower(trim((string) ($item['name'] ?? '')));
            return (string) ($item['id'] ?? '') === $selectedId
                || (string) ($item['business_id'] ?? '') === $selectedId
                || md5($name) === $selectedId
                || $name === $normalizedSelectedId;
        });

        if (! $school) {
            $dashboardTopSchools = $this->dashboardTopEngagedSchools ?? [];
            if ($dashboardTopSchools instanceof \Illuminate\Support\Collection) {
                $dashboardTopSchools = $dashboardTopSchools->all();
            }

            $school = collect(is_array($dashboardTopSchools) ? $dashboardTopSchools : [])
                ->first(function ($item) use ($selectedId, $normalizedSelectedId): bool {
                    if (! is_array($item)) {
                        return false;
                    }

                    $name = strtolower(trim((string) ($item['name'] ?? '')));
                    return (string) ($item['id'] ?? '') === $selectedId
                        || (string) ($item['business_id'] ?? '') === $selectedId
                        || md5($name) === $selectedId
                        || $name === $normalizedSelectedId;
                });
        }

        if (! $school || ! is_array($school)) {
            return null;
        }

        $businessId = trim((string) ($school['business_id'] ?? $school['id'] ?? ''));
        $schoolName = trim((string) ($school['name'] ?? ''));
        $matchedCoaches = collect($this->coachesForSchoolSearch($school));

        $coaches = $matchedCoaches
            ->filter(fn ($coach): bool => is_array($coach))
            ->map(function (array $coach) use ($school, $businessId, $schoolName): array {
                $coach['school'] = $coach['school'] ?? $coach['school_name'] ?? $coach['company_name'] ?? $schoolName;
                $coach['school_name'] = $coach['school_name'] ?? $coach['school'] ?? $schoolName;
                $coach['company_name'] = $coach['company_name'] ?? $coach['school'] ?? $schoolName;
                $coach['business_id'] = $coach['business_id'] ?? $coach['company_id'] ?? $coach['ghl_business_id'] ?? $businessId;
                $coach['school_logo_url'] = $coach['school_logo_url'] ?? $school['school_logo_url'] ?? $school['logo_url'] ?? null;
                $coach['business_logo_url'] = $coach['business_logo_url'] ?? $school['business_logo_url'] ?? $school['logo_url'] ?? null;

                return $coach;
            })
            ->unique(function (array $coach): string {
                return strtolower(trim((string) ($coach['id'] ?? $coach['contact_id'] ?? $coach['email'] ?? $coach['name'] ?? serialize($coach))));
            })
            ->values();

        if ($coaches->isEmpty() && is_array($school['head_coach'] ?? null) && filled($school['head_coach']['name'] ?? null)) {
            $headCoach = $school['head_coach'];
            $headCoach['school'] = $headCoach['school'] ?? $schoolName;
            $headCoach['school_name'] = $headCoach['school_name'] ?? $schoolName;
            $headCoach['company_name'] = $headCoach['company_name'] ?? $schoolName;
            $headCoach['business_id'] = $headCoach['business_id'] ?? $businessId;
            $headCoach['school_logo_url'] = $headCoach['school_logo_url'] ?? $school['school_logo_url'] ?? $school['logo_url'] ?? null;
            $coaches = collect([$headCoach]);
        }

        $school['coaches'] = $coaches->values()->all();
        $school['coach_count'] = $coaches->count();
        $school['coaches_count'] = $coaches->count();
        $school['coach_count_cross_referenced'] = $coaches->count();

        if (blank(data_get($school, 'head_coach.name')) && $coaches->isNotEmpty()) {
            $school['head_coach'] = $coaches->first(function (array $coach): bool {
                return str_contains(strtolower((string) ($coach['title'] ?? '')), 'head');
            }) ?: $coaches->first();
        }

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

    public function setSelectedSchoolIds(array $schoolIds): array
    {
        $this->selectedSchoolIds = collect($schoolIds)
            ->map(fn ($id): string => trim((string) $id))
            ->filter()
            ->unique()
            ->take(500)
            ->values()
            ->all();

        return [
            'success' => true,
            'selected' => $this->selectedSchoolIds,
            'count' => count($this->selectedSchoolIds),
        ];
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

    public function queueSelectedSchoolsToList(string $listKey): array
    {
        if (method_exists($this, 'skipRender')) {
            $this->skipRender();
        }

        $user = Auth::user();
        $listKey = trim($listKey);
        $schoolIds = collect($this->selectedSchoolIds)
            ->map(fn ($id): string => trim((string) $id))
            ->filter()
            ->unique()
            ->take(500)
            ->values()
            ->all();

        if (! $user || ! $this->allowed || $this->locked) {
            return ['success' => false, 'error' => 'This action is not available.'];
        }

        if ($listKey === '') {
            return ['success' => false, 'error' => 'Choose a list first.'];
        }

        if (empty($schoolIds)) {
            return ['success' => false, 'error' => 'Select at least one school first.'];
        }

        $service = app(CoachDatabaseService::class);
        $tag = $service->listTagForKey($listKey, $user);
        if (blank($tag)) {
            return ['success' => false, 'error' => 'That list could not be found.'];
        }

        $queued = app(CoachDatabaseActionQueueService::class)->enqueue($user, [[
            'kind' => 'school_list_bulk',
            'school_ids' => $schoolIds,
            'list_key' => $listKey,
            'tag' => $tag,
            'type' => 'add',
        ]]);

        if (! ($queued['success'] ?? false)) {
            return ['success' => false, 'error' => $queued['error'] ?? 'Unable to queue the selected schools.'];
        }

        $this->applyBulkSchoolListMembershipToCache($schoolIds, $listKey, true);
        $this->startCoachDatabaseActionWorker($user);

        $listLabel = collect($this->lists)
            ->first(fn ($list): bool => is_array($list) && (string) ($list['key'] ?? '') === $listKey)['label']
            ?? \Illuminate\Support\Str::headline($listKey);

        return [
            'success' => true,
            'queued' => (int) ($queued['queued'] ?? 1),
            'school_count' => count($schoolIds),
            'list_key' => $listKey,
            'list_label' => (string) $listLabel,
        ];
    }

    /**
     * Compatibility alias for older Blade files. The actual work is queued and
     * processed outside Livewire so this method returns immediately.
     */
    public function addSelectedSchoolsToList(string $listKey): void
    {
        $result = $this->queueSelectedSchoolsToList($listKey);

        if (! ($result['success'] ?? false)) {
            Notification::make()
                ->title('Recruiting Center')
                ->body($result['error'] ?? 'Unable to queue the selected schools.')
                ->danger()
                ->send();
        }
    }

    protected function applyBulkSchoolListMembershipToCache(array $schoolIds, string $listKey, bool $inList): void
    {
        $schoolLookup = array_fill_keys(
            collect($schoolIds)->map(fn ($id): string => trim((string) $id))->filter()->unique()->all(),
            true,
        );

        if (empty($schoolLookup) || trim($listKey) === '') {
            return;
        }

        $snapshot = Cache::get($this->activeCacheKey(), $this->emptySnapshot());
        $snapshot['schools'] = collect($snapshot['schools'] ?? [])
            ->filter(fn ($school): bool => is_array($school))
            ->map(function (array $school) use ($schoolLookup, $listKey, $inList): array {
                $name = strtolower(trim((string) ($school['name'] ?? '')));
                $candidates = array_filter([
                    trim((string) ($school['id'] ?? '')),
                    trim((string) ($school['business_id'] ?? $school['company_id'] ?? '')),
                    $name !== '' ? md5($name) : '',
                ]);

                $selected = collect($candidates)->contains(fn (string $candidate): bool => isset($schoolLookup[$candidate]));
                if (! $selected) {
                    return $school;
                }

                $keys = collect($school['list_keys'] ?? $school['lists'] ?? [])
                    ->map(fn ($key): string => trim((string) $key))
                    ->filter()
                    ->unique()
                    ->values();

                if ($inList) {
                    if (! $keys->contains($listKey)) {
                        $keys->push($listKey);
                    }
                } else {
                    $keys = $keys->reject(fn (string $key): bool => $key === $listKey)->values();
                }

                $school['list_keys'] = $keys->values()->all();
                $school['lists'] = $school['list_keys'];

                return $school;
            })
            ->values()
            ->all();

        $schools = collect($snapshot['schools'] ?? []);
        $snapshot['lists'] = collect($snapshot['lists'] ?? [])
            ->map(function ($row) use ($schools, $listKey) {
                if (! is_array($row) || trim((string) ($row['key'] ?? '')) !== $listKey) {
                    return $row;
                }

                $items = $schools
                    ->filter(fn (array $school): bool => in_array($listKey, $school['list_keys'] ?? [], true))
                    ->values();

                $row['schools_count'] = $items->count();
                $row['coaches_count'] = $items->sum(fn (array $school): int => (int) ($school['coach_count'] ?? $school['coaches_count'] ?? 0));
                $row['schools'] = $items
                    ->map(fn (array $school): array => [
                        'id' => $school['id'] ?? $school['business_id'] ?? null,
                        'name' => $school['name'] ?? null,
                        'logo_url' => $school['logo_url'] ?? $school['school_logo_url'] ?? $school['business_logo_url'] ?? null,
                        'conference' => $school['conference'] ?? null,
                        'division' => $school['division'] ?? null,
                        'coach_count' => (int) ($school['coach_count'] ?? $school['coaches_count'] ?? 0),
                    ])
                    ->all();

                return $row;
            })
            ->values()
            ->all();

        $snapshot['tag_synced_at'] = now()->toDateTimeString();
        $this->storeSnapshot($snapshot);
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

    protected function lightweightCoachForSchoolIndex(array $coach, array $school = []): array
    {
        $schoolName = trim((string) ($school['name'] ?? $school['school_name'] ?? $school['company_name'] ?? ''));
        $businessId = trim((string) ($school['business_id'] ?? $school['company_id'] ?? $school['id'] ?? ''));
        $logo = $school['logo_url'] ?? $school['school_logo_url'] ?? $school['business_logo_url'] ?? null;

        if ($schoolName !== '') {
            $coach['school'] = $schoolName;
            $coach['school_name'] = $schoolName;
            $coach['company_name'] = $schoolName;
        }
        if ($businessId !== '') {
            $coach['business_id'] = $businessId;
            $coach['company_id'] = $businessId;
            $coach['school_id'] = $businessId;
        }

        $coach['conference'] = $coach['conference'] ?? $school['conference'] ?? null;
        $coach['division'] = $coach['division'] ?? $school['division'] ?? null;
        $coach['school_logo_url'] = $coach['school_logo_url'] ?? $logo;
        $coach['business_logo_url'] = $coach['business_logo_url'] ?? $logo;
        $coach['logo_url'] = $coach['logo_url'] ?? $logo;

        foreach (['raw_contact', 'raw_business', 'custom_fields_json', 'customFields', 'custom_fields', 'customFieldValues', 'contact', 'business', 'company'] as $heavyKey) {
            unset($coach[$heavyKey]);
        }

        return $coach;
    }

    protected function rawSnapshotSchoolCoachRows(): array
    {
        $snapshot = $this->activeSnapshotRows();
        $schoolRows = collect(is_array($snapshot['schools'] ?? null) ? $snapshot['schools'] : [])
            ->merge(is_array($snapshot['top_schools'] ?? null) ? $snapshot['top_schools'] : [])
            ->merge(is_array($this->topSchools ?? null) ? $this->topSchools : [])
            ->filter(fn ($school): bool => is_array($school));

        return $schoolRows->flatMap(function (array $school): array {
            $rows = [];
            foreach (['coaches', 'staff', 'coaching_staff', 'contacts', 'coaches_preview'] as $field) {
                foreach (is_array($school[$field] ?? null) ? $school[$field] : [] as $coach) {
                    if (is_array($coach)) {
                        $rows[] = $this->lightweightCoachForSchoolIndex($coach, $school);
                    }
                }
            }

            if (is_array($school['head_coach'] ?? null)) {
                $rows[] = $this->lightweightCoachForSchoolIndex($school['head_coach'], $school);
            }

            return $rows;
        })->values()->all();
    }

    protected function schoolCoachSearchIndex(): array
    {
        if (is_array($this->schoolCoachIndexMemo)) {
            return $this->schoolCoachIndexMemo;
        }

        $index = [];
        $coachRows = collect($this->allCoaches())
            ->merge($this->rawSnapshotSchoolCoachRows())
            ->filter(fn ($coach): bool => is_array($coach))
            ->unique(fn (array $coach): string => $this->coachTrackingIdentity($coach))
            ->values();

        foreach ($coachRows as $coach) {
            $keys = [];
            $businessIds = collect([
                $coach['business_id'] ?? null,
                $coach['company_id'] ?? null,
                $coach['ghl_business_id'] ?? null,
                $coach['school_id'] ?? null,
                data_get($coach, 'company.id'),
                data_get($coach, 'business.id'),
            ])->merge($this->coachBusinessIdCandidates($coach))->map(fn ($value): string => trim((string) $value))->filter()->unique()->values();

            foreach ($businessIds as $businessId) {
                $keys[] = 'business:' . strtolower(trim((string) $businessId));
            }

            $schoolNames = collect([
                $coach['school'] ?? null,
                $coach['school_name'] ?? null,
                $coach['company_name'] ?? null,
                $coach['school_or_company'] ?? null,
                data_get($coach, 'company.name'),
                data_get($coach, 'business.name'),
            ])->merge(is_array($coach['school_aliases'] ?? null) ? $coach['school_aliases'] : [])
                ->merge($this->coachSchoolNameCandidates($coach))
                ->map(fn ($value): string => trim((string) $value))
                ->filter()
                ->unique()
                ->values();

            foreach ($schoolNames as $schoolName) {
                $keys[] = 'school:' . strtolower($schoolName);
                $normalized = $this->normalizeSchoolMatchKey($schoolName);
                if ($normalized !== '') {
                    $keys[] = 'school_key:' . $normalized;
                }
            }

            foreach (array_unique($keys) as $key) {
                $index[$key] ??= [];
                $index[$key][$this->coachTrackingIdentity($coach)] = $coach;
            }
        }

        return $this->schoolCoachIndexMemo = $index;
    }

    protected function coachCountForSchoolSearch(array $school): int
    {
        return count($this->coachesForSchoolSearch($school));
    }

    protected function coachesForSchoolSearch(array $school): array
    {
        $businessId = trim((string) ($school['business_id'] ?? $school['id'] ?? ''));
        $schoolName = trim((string) ($school['name'] ?? $school['school'] ?? $school['school_name'] ?? $school['company_name'] ?? $school['business_name'] ?? ''));
        $normalizedSchoolName = $this->normalizeSchoolMatchKey($schoolName);

        $allCoaches = collect($this->allCoaches())
            ->filter(fn ($coach): bool => is_array($coach))
            ->values();
        $coachesById = $allCoaches
            ->filter(fn (array $coach): bool => filled($coach['id'] ?? $coach['contact_id'] ?? null))
            ->keyBy(fn (array $coach): string => trim((string) ($coach['id'] ?? $coach['contact_id'] ?? '')));
        $coachesByEmail = $allCoaches
            ->filter(fn (array $coach): bool => filled($coach['email'] ?? null))
            ->keyBy(fn (array $coach): string => strtolower(trim((string) ($coach['email'] ?? ''))));

        $coaches = [];

        // First use the exact reconciled contact references saved on the school row.
        foreach (collect($school['coach_ids'] ?? [])->map(fn ($id): string => trim((string) $id))->filter()->unique() as $coachId) {
            $coach = $coachesById->get($coachId);
            if (is_array($coach)) {
                $coaches[$this->coachTrackingIdentity($coach)] = $coach;
            }
        }
        foreach (collect($school['coach_emails'] ?? [])->map(fn ($email): string => strtolower(trim((string) $email)))->filter()->unique() as $email) {
            $coach = $coachesByEmail->get($email);
            if (is_array($coach)) {
                $coaches[$this->coachTrackingIdentity($coach)] = $coach;
            }
        }

        // Then union the live index matches from official Business IDs and contact-side
        // Business Name / Company Name / School Name values.
        $keys = [];
        if ($businessId !== '') {
            $keys[] = 'business:' . strtolower($businessId);
        }
        if ($schoolName !== '') {
            $keys[] = 'school:' . strtolower($schoolName);
        }
        if ($normalizedSchoolName !== '') {
            $keys[] = 'school_key:' . $normalizedSchoolName;
        }

        $index = $this->schoolCoachSearchIndex();
        foreach (array_unique($keys) as $key) {
            foreach (($index[$key] ?? []) as $coachId => $coach) {
                $coaches[$coachId] = $coach;
            }
        }

        foreach ($this->dashboardCoachesForSchoolRow($school) as $coach) {
            if (! is_array($coach)) {
                continue;
            }
            $coaches[$this->coachTrackingIdentity($coach)] = $coach;
        }

        return array_values($coaches);
    }

    protected function coachSchoolNameCandidates(array $coach): array
    {
        return collect(is_array($coach['school_aliases'] ?? null) ? $coach['school_aliases'] : [])->merge([
            $coach['school'] ?? null,
            $coach['school_name'] ?? null,
            $coach['company_name'] ?? null,
            $coach['business_name'] ?? null,
            $coach['school_or_company'] ?? null,
            $coach['organization'] ?? null,
            data_get($coach, 'company.name'),
            data_get($coach, 'business.name'),
            data_get($coach, 'raw_contact.companyName'),
            data_get($coach, 'raw_contact.businessName'),
        ])->map(fn ($value): string => trim((string) $value))->filter()->unique(fn (string $value): string => strtolower($value))->values()->all();
    }

    protected function coachBusinessIdCandidates(array $coach): array
    {
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
        ])->map(fn ($value): string => strtolower(trim((string) $value)))->filter()->unique()->values()->all();
    }

    protected function firstCoachSchoolName(array $coach): string
    {
        return (string) (collect($this->coachSchoolNameCandidates($coach))->first() ?? '');
    }

    protected function firstCoachBusinessId(array $coach): string
    {
        return (string) (collect($this->coachBusinessIdCandidates($coach))->first() ?? '');
    }

    protected function coachBelongsToSchool(array $coach, string $businessId, string $schoolName, string $normalizedSchoolName = ''): bool
    {
        $businessId = strtolower(trim($businessId));
        $normalizedSchoolName = $normalizedSchoolName !== '' ? $normalizedSchoolName : $this->normalizeSchoolMatchKey($schoolName);

        if ($businessId !== '' && in_array($businessId, $this->coachBusinessIdCandidates($coach), true)) {
            return true;
        }

        if ($normalizedSchoolName === '') {
            return false;
        }

        foreach ($this->coachSchoolNameCandidates($coach) as $candidate) {
            $coachSchoolKey = $this->normalizeSchoolMatchKey($candidate);
            if ($coachSchoolKey === '') {
                continue;
            }

            if ($coachSchoolKey === $normalizedSchoolName) {
                return true;
            }

            // Conservative loose match for common Recruiting Center mismatch cases, e.g.
            // business row includes "University" but contact row uses the shorter
            // school/company name. Require one side to be reasonably specific.
            if ((strlen($coachSchoolKey) >= 8 || strlen($normalizedSchoolName) >= 8)
                && (str_contains($coachSchoolKey, $normalizedSchoolName) || str_contains($normalizedSchoolName, $coachSchoolKey))) {
                return true;
            }
        }

        return false;
    }

    protected function dashboardCoachesForSchoolRow(array $school): array
    {
        $schoolKey = $this->normalizeSchoolMatchKey((string) ($school['name'] ?? ''));
        $businessId = strtolower(trim((string) ($school['business_id'] ?? $school['id'] ?? '')));

        if ($schoolKey === '' && $businessId === '') {
            return [];
        }

        $sources = collect()
            ->merge(is_array($this->topSchools ?? null) ? $this->topSchools : [])
            ->merge(is_array($this->dashboardTopEngagedSchools ?? null) ? $this->dashboardTopEngagedSchools : [])
            ->merge(is_array($this->onTheRadarSchools ?? null) ? $this->onTheRadarSchools : [])
            ->merge(is_array($this->schoolsMostInterested ?? null) ? $this->schoolsMostInterested : [])
            ->merge(is_array($this->topEngagedSchools ?? null) ? $this->topEngagedSchools : [])
            ->merge(is_array($this->schoolRecommendations ?? null) ? $this->schoolRecommendations : [])
            ->filter(fn ($row): bool => is_array($row));

        $coaches = [];

        foreach ($sources as $row) {
            $rowName = (string) ($row['name'] ?? $row['school'] ?? $row['school_name'] ?? $row['company_name'] ?? '');
            $rowKey = $this->normalizeSchoolMatchKey($rowName);
            $rowBusinessId = strtolower(trim((string) ($row['business_id'] ?? $row['id'] ?? $row['company_id'] ?? $row['ghl_business_id'] ?? '')));

            $isMatch = false;
            if ($businessId !== '' && $rowBusinessId !== '' && $businessId === $rowBusinessId) {
                $isMatch = true;
            }
            if (! $isMatch && $schoolKey !== '' && $rowKey !== '' && $schoolKey === $rowKey) {
                $isMatch = true;
            }
            if (! $isMatch) {
                continue;
            }

            foreach (['coaches', 'staff', 'coaching_staff', 'contacts'] as $field) {
                if (is_array($row[$field] ?? null)) {
                    foreach ($row[$field] as $coach) {
                        if (is_array($coach)) {
                            $coachId = (string) ($coach['id'] ?? $coach['contact_id'] ?? $coach['email'] ?? $coach['name'] ?? md5(json_encode($coach)));
                            $coaches[$coachId] = $coach;
                        }
                    }
                }
            }

            if (is_array($row['head_coach'] ?? null) && filled($row['head_coach']['name'] ?? null)) {
                $coach = $row['head_coach'];
                $coachId = (string) ($coach['id'] ?? $coach['contact_id'] ?? $coach['email'] ?? $coach['name'] ?? md5(json_encode($coach)));
                $coaches[$coachId] = $coach;
            }
        }
        return array_values($coaches);
    }

    protected function normalizeSchoolMatchKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\b(the|university|college|school|athletics|athletic|department|dept|of|at)\b/i', ' ', $value) ?: $value;
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
        // Keep school search lightweight. Coach names are searchable in the Coaches
        // index/suggestions; running a full school-coach lookup for every school card
        // causes timeout/memory issues on large datasets.
        $headCoach = is_array($school['head_coach'] ?? null) ? $school['head_coach'] : [];
        $coachTokens = [
            $headCoach['name'] ?? '',
            $headCoach['first_name'] ?? '',
            $headCoach['last_name'] ?? '',
            $headCoach['email'] ?? '',
            $headCoach['title'] ?? '',
            $headCoach['position'] ?? '',
        ];

        return $this->normalizeSearchText(array_merge([
            $school['name'] ?? '',
            $school['conference'] ?? '',
            $this->conferenceSearchTokens($school['conference'] ?? ''),
            $school['division'] ?? '',
            $school['city'] ?? '',
            $school['state'] ?? '',
            $this->listTokensForSchool($school),
        ], $coachTokens));
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
        $this->allSchoolsMemo = null;
        $this->allCoachesMemo = null;
        $this->trackingCoachesMemo = null;
    }

    protected function allSchools(): array
    {
        if (is_array($this->allSchoolsMemo)) {
            return $this->allSchoolsMemo;
        }

        $snapshot = $this->activeSnapshotRows();

        if ((bool) ($snapshot['dataset_reconciled'] ?? false) && is_array($snapshot['schools'] ?? null)) {
            return $this->allSchoolsMemo = collect($snapshot['schools'])
                ->filter(fn ($school): bool => is_array($school))
                ->values()
                ->all();
        }

        $snapshotSchools = collect(is_array($snapshot['schools'] ?? null) ? $snapshot['schools'] : [])
            ->filter(fn ($school): bool => is_array($school));

        $topSchoolRows = collect(is_array($snapshot['top_schools'] ?? null) ? $snapshot['top_schools'] : [])
            ->merge(is_array($this->topSchools ?? null) ? $this->topSchools : [])
            ->merge(is_array($this->dashboardTopEngagedSchools ?? null) ? $this->dashboardTopEngagedSchools : [])
            ->filter(fn ($school): bool => is_array($school));

        /**
         * Discover Schools cannot depend only on the paged business/school cache.
         * On large Recruiting Center accounts that cache may contain only the first page, while
         * the contacts/coaches cache already contains schools from every coach.
         * Build lightweight school rows from coaches so Discover can show every
         * school and still paginate with the existing Load More button.
         */
        $coachRows = collect(is_array($snapshot['coaches'] ?? null) ? $snapshot['coaches'] : [])
            ->filter(fn ($coach): bool => is_array($coach));

        $coachDerivedSchools = $coachRows
            ->map(function (array $coach): array {
                $schoolName = trim($this->firstCoachSchoolName($coach));
                $businessId = trim($this->firstCoachBusinessId($coach));

                if ($schoolName === '' && $businessId === '') {
                    return [];
                }

                $logo = $coach['school_logo_url']
                    ?? $coach['business_logo_url']
                    ?? $coach['logo_url']
                    ?? $coach['logo']
                    ?? data_get($coach, 'business.logo')
                    ?? data_get($coach, 'contact.school_logo')
                    ?? null;

                return [
                    'id' => $businessId !== '' ? $businessId : 'school-' . md5(strtolower($schoolName)),
                    'business_id' => $businessId,
                    'name' => $schoolName !== '' ? $schoolName : (string) ($coach['company_name'] ?? 'School'),
                    'conference' => $coach['conference'] ?? $coach['school_conference'] ?? '',
                    'division' => $coach['division'] ?? $coach['school_division'] ?? '',
                    'city' => $coach['city'] ?? $coach['school_city'] ?? '',
                    'state' => $coach['state'] ?? $coach['school_state'] ?? '',
                    'logo_url' => $logo,
                    'school_logo_url' => $logo,
                    'business_logo_url' => $logo,
                    'coach_count' => 1,
                    'coaches_count' => 1,
                    'head_coach' => $this->isHeadCoachTitle((string) ($coach['title'] ?? $coach['position'] ?? '')) ? $coach : null,
                ];
            })
            ->filter(fn (array $school): bool => filled($school['name'] ?? null));

        $allRows = $snapshotSchools
            ->merge($coachDerivedSchools)
            ->merge($topSchoolRows)
            ->filter(fn ($school): bool => is_array($school) && filled($school['name'] ?? $school['school'] ?? $school['school_name'] ?? $school['company_name'] ?? null));

        $coachIdsByKey = [];
        $headCoachesByKey = [];

        foreach ($coachRows as $coach) {
            $keys = [];

            // Exact association keys.
            foreach ($this->coachBusinessIdCandidates($coach) as $businessId) {
                $keys[] = 'business:' . strtolower(trim((string) $businessId));
            }

            // Cross-reference keys. Include every known School / Company / Business
            // name because Recruiting Center sometimes leaves businessId empty even though the
            // contact's Business Name field is populated correctly.
            foreach ($this->coachSchoolNameCandidates($coach) as $schoolName) {
                $schoolKey = $this->normalizeSchoolMatchKey($schoolName);
                if ($schoolKey !== '') {
                    $keys[] = 'school:' . $schoolKey;
                }
            }

            $identity = $this->coachTrackingIdentity($coach);

            foreach (array_unique(array_filter($keys)) as $key) {
                $coachIdsByKey[$key] ??= [];
                $coachIdsByKey[$key][$identity] = true;

                if (! isset($headCoachesByKey[$key]) && $this->isHeadCoachTitle((string) ($coach['title'] ?? $coach['position'] ?? ''))) {
                    $headCoachesByKey[$key] = $coach;
                }
            }
        }

        $coachCountsByKey = collect($coachIdsByKey)
            ->map(fn (array $identities): int => count($identities))
            ->all();

        return $allRows
            ->groupBy(function (array $school): string {
                $schoolKey = $this->normalizeSchoolMatchKey((string) ($school['name'] ?? $school['school'] ?? $school['school_name'] ?? $school['company_name'] ?? ''));
                if ($schoolKey !== '') {
                    return 'school:' . $schoolKey;
                }

                $businessId = trim((string) ($school['business_id'] ?? $school['id'] ?? ''));
                return 'business:' . strtolower($businessId);
            })
            ->map(function ($rows, string $groupKey) use ($coachCountsByKey, $headCoachesByKey): array {
                $rows = collect($rows)->filter(fn ($school): bool => is_array($school))->values();
                $primary = $rows->sortByDesc(function (array $school): int {
                    $nestedCoachCount = is_array($school['coaches'] ?? null)
                        ? count(array_filter($school['coaches'], fn ($coach): bool => is_array($coach)))
                        : 0;

                    return (filled($school['business_id'] ?? null) ? 100 : 0)
                        + (filled($school['logo_url'] ?? $school['school_logo_url'] ?? $school['business_logo_url'] ?? null) ? 50 : 0)
                        + max((int) ($school['coach_count'] ?? 0), (int) ($school['coaches_count'] ?? 0), $nestedCoachCount);
                })->first() ?: [];

                foreach ($rows as $row) {
                    foreach (['id', 'business_id', 'logo_url', 'school_logo_url', 'business_logo_url', 'conference', 'division', 'city', 'state'] as $field) {
                        if (blank($primary[$field] ?? null) && filled($row[$field] ?? null)) {
                            $primary[$field] = $row[$field];
                        }
                    }

                    if (blank(data_get($primary, 'head_coach.name')) && filled(data_get($row, 'head_coach.name'))) {
                        $primary['head_coach'] = $row['head_coach'];
                    }
                }

                $schoolKey = $this->normalizeSchoolMatchKey((string) ($primary['name'] ?? $primary['school'] ?? $primary['school_name'] ?? $primary['company_name'] ?? ''));
                $businessKey = trim((string) ($primary['business_id'] ?? $primary['id'] ?? ''));
                $lookupKeys = array_values(array_filter([
                    $schoolKey !== '' ? 'school:' . $schoolKey : null,
                    $businessKey !== '' ? 'business:' . strtolower($businessKey) : null,
                    $groupKey,
                ]));

                $nestedCoachCount = 0;
                foreach ($rows as $row) {
                    $nestedCoachCount = max(
                        $nestedCoachCount,
                        (int) ($row['coach_count'] ?? 0),
                        (int) ($row['coaches_count'] ?? 0)
                    );
                }

                $indexedCoachCount = 0;
                foreach ($lookupKeys as $key) {
                    $indexedCoachCount = max($indexedCoachCount, (int) ($coachCountsByKey[$key] ?? 0));

                    if (blank(data_get($primary, 'head_coach.name')) && isset($headCoachesByKey[$key])) {
                        $primary['head_coach'] = $headCoachesByKey[$key];
                    }
                }

                $primary['coach_count'] = max(
                    (int) ($primary['coach_count'] ?? 0),
                    (int) ($primary['coaches_count'] ?? 0),
                    $nestedCoachCount,
                    $indexedCoachCount,
                    (is_array($primary['head_coach'] ?? null) && filled($primary['head_coach']['name'] ?? null)) ? 1 : 0
                );
                $primary['coaches_count'] = $primary['coach_count'];

                $indexedCount = $this->coachCountForSchoolSearch($primary);
                if ($indexedCount > 0) {
                    $primary['coach_count'] = max((int) ($primary['coach_count'] ?? 0), $indexedCount);
                    $primary['coaches_count'] = $primary['coach_count'];
                }

                unset($primary['coaches'], $primary['staff'], $primary['coaching_staff'], $primary['contacts']);

                return $primary;
            })
            ->sortBy(fn (array $school): string => strtolower((string) ($school['name'] ?? '')))
            ->values()
            ->all();
    }

    protected function allCoaches(): array
    {
        if (is_array($this->allCoachesMemo)) {
            return $this->allCoachesMemo;
        }

        $snapshot = $this->activeSnapshotRows();

        // Use the canonical contact list as the source of truth. Do not re-read
        // nested school coach arrays here. Old cached snapshots may contain a full
        // duplicated roster under every school, which causes timeout/memory errors.
        return $this->allCoachesMemo = collect(is_array($snapshot['coaches'] ?? null) ? $snapshot['coaches'] : [])
            ->filter(fn ($coach): bool => is_array($coach))
            ->unique(fn (array $coach): string => strtolower(trim((string) ($coach['id'] ?? $coach['contact_id'] ?? $coach['email'] ?? $coach['name'] ?? md5(json_encode($coach))))))
            ->values()
            ->all();
    }

    protected function isHeadCoachTitle(string $title): bool
    {
        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $title)));

        if ($normalized === '') {
            return false;
        }

        // Treat common Recruiting Center/NCAA title variants as the primary/head coach.
        // Avoid matching assistant/associate/volunteer titles that merely contain "coach".
        if (preg_match('/\b(assistant|associate|volunteer|graduate|goalkeeper|keeper|operations|recruiting|director|staff)\b/', $normalized)) {
            return false;
        }

        return (bool) preg_match('/\b(head coach|college head coach|head women\'?s coach|head womens coach|women\'?s head coach|womens head coach|head soccer coach|head)\b/', $normalized);
    }

    protected function hydrateFromSnapshot(array $snapshot): void
    {
        $this->coachDatabaseSnapshotMemo = is_array($snapshot) ? $snapshot : [];
        $this->coachSearchIndexMemo = null;
        $this->schoolCoachIndexMemo = null;
        $this->allSchoolsMemo = null;
        $this->allCoachesMemo = null;

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