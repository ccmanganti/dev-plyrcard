<?php



namespace App\Filament\Resources\Coaches\Pages;



use App\Filament\Resources\Coaches\CoachResource;

use App\Models\Coach;
use App\Models\Club;
use App\Models\League;
use App\Models\School;
use App\Models\User;

use App\Services\CoachSpreadsheetService;

use App\Services\SportAvailabilityService;

use Filament\Notifications\Notification;

use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Str;

use Livewire\Attributes\Url;

use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

use Livewire\WithFileUploads;

use Throwable;



class ImportCoaches extends Page

{

    use WithFileUploads;



    protected static string $resource = CoachResource::class;

    protected string $view = 'filament.resources.coaches.pages.import-coaches';



    private const MAX_UPLOAD_BYTES = 20 * 1024 * 1024;

    private const ALLOWED_UPLOAD_EXTENSIONS = ['csv', 'txt', 'xlsx'];



    #[Url(as: 'sport')]

    public ?string $selectedSport = null;



    #[Url(as: 'gender')]

    public ?string $selectedGender = null;



    public TemporaryUploadedFile|string|null $upload = null;

    public ?string $stagedUploadName = null;

    public int $stagedUploadBytes = 0;

    public array $headers = [];

    public array $previewRows = [];

    public array $mapping = [];

    public int $totalRows = 0;

    public ?string $storedImportPath = null;

    public array $lastImportErrors = [];



    public bool $importRunning = false;

    public int $importProcessed = 0;

    public int $importTotal = 0;

    public int $importCreated = 0;

    public int $importUpdated = 0;

    public int $importSkipped = 0;

    public int $importFailed = 0;

    public int $importBatchSize = 150;

    public ?string $importJobPath = null;

    // Optional quick exclusivity for this upload. Public keeps the existing
    // behavior. Other modes resolve to concrete non-admin user IDs when the
    // import starts, then every imported coach is restricted to that audience.
    public string $quickVisibilityType = 'public';
    public array $quickVisibilityUserIds = [];
    public ?string $quickVisibilityClubId = null;
    public ?string $quickVisibilityLeagueId = null;



    public function mount(): void

    {

        abort_unless(

            filled($this->selectedSport) && array_key_exists($this->selectedSport, CoachResource::sportOptions()),

            404,

        );



        $this->selectedGender = Coach::normalizeGender($this->selectedGender);

        $this->mapping = array_fill_keys(array_keys(CoachSpreadsheetService::IMPORT_FIELDS), '');

    }



    public function getTitle(): string

    {

        return 'Import ' . (CoachResource::sportOptions()[$this->selectedSport] ?? 'Sport') . ' Coaches';

    }

    public function getQuickVisibilityUserOptionsProperty(): array
    {
        return $this->eligibleAudienceUserQuery()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(500)
            ->get(['id', 'first_name', 'last_name', 'email', 'sport'])
            ->mapWithKeys(fn (User $user): array => [
                (string) $user->getKey() => $this->formatAudienceUserLabel($user),
            ])
            ->all();
    }

    public function getQuickVisibilityClubOptionsProperty(): array
    {
        return Club::query()
            ->orderBy('name')
            ->limit(500)
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id): array => [(string) $id => (string) $name])
            ->all();
    }

    public function getQuickVisibilityLeagueOptionsProperty(): array
    {
        return League::query()
            ->orderBy('name')
            ->limit(500)
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id): array => [(string) $id => (string) $name])
            ->all();
    }

    public function getQuickVisibilityReadyProperty(): bool
    {
        return match ($this->quickVisibilityType) {
            'users' => collect($this->quickVisibilityUserIds)->filter()->isNotEmpty(),
            'club' => (int) $this->quickVisibilityClubId > 0,
            'league' => (int) $this->quickVisibilityLeagueId > 0,
            default => true,
        };
    }

    public function updatedQuickVisibilityType(): void
    {
        $this->quickVisibilityUserIds = [];
        $this->quickVisibilityClubId = null;
        $this->quickVisibilityLeagueId = null;
    }



    /**

     * Stage the Livewire temporary upload immediately while the temp file is still

     * available. This deliberately avoids Laravel's \`file|max\` validation rules on

     * TemporaryUploadedFile because those rules call Flysystem fileSize(), which is

     * the source of the UnableToRetrieveMetadata exception on this server.

     */

    public function updatedUpload(): void

    {

        $this->resetErrorBag('upload');



        if (! $this->upload instanceof TemporaryUploadedFile) {

            return;

        }



        $temporaryUpload = $this->upload;

        $originalName = trim((string) $temporaryUpload->getClientOriginalName());

        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));



        if (! in_array($extension, self::ALLOWED_UPLOAD_EXTENSIONS, true)) {

            $this->addError('upload', 'Please choose a CSV or Excel (.xlsx) file.');

            $this->upload = null;

            return;

        }



        $localDisk = Storage::disk('local');

        $newStoredPath = 'coach-imports/' . Str::uuid() . '.' . $extension;

        $destinationPath = $localDisk->path($newStoredPath);

        $destinationDirectory = dirname($destinationPath);



        if (! is_dir($destinationDirectory) && ! @mkdir($destinationDirectory, 0775, true) && ! is_dir($destinationDirectory)) {

            $this->addError('upload', 'The coach import staging directory could not be created.');

            $this->upload = null;

            return;

        }



        $source = null;

        $destination = null;



        try {

            // getRealPath() resolves the local temp path without asking Flysystem for

            // file-size metadata. Copying immediately prevents the later Analyze

            // request from depending on a livewire-tmp file that may have disappeared.

            $temporaryPath = $temporaryUpload->getRealPath();



            if (! is_string($temporaryPath) || $temporaryPath === '' || ! is_file($temporaryPath)) {

                throw new \RuntimeException('The temporary upload is no longer available. Please choose the file again.');

            }



            $source = @fopen($temporaryPath, 'rb');

            $destination = @fopen($destinationPath, 'wb');



            if (! is_resource($source) || ! is_resource($destination)) {

                throw new \RuntimeException('The uploaded file could not be staged for import.');

            }



            // Copy one byte beyond the limit so oversized files can be rejected

            // without ever calling TemporaryUploadedFile::getSize().

            $copiedBytes = stream_copy_to_stream($source, $destination, self::MAX_UPLOAD_BYTES + 1);



            if ($copiedBytes === false) {

                throw new \RuntimeException('The uploaded file could not be copied for import.');

            }



            if ($copiedBytes > self::MAX_UPLOAD_BYTES) {

                @unlink($destinationPath);

                $this->addError('upload', 'The file must not be larger than 20 MB.');

                $this->upload = null;

                return;

            }



            if ($copiedBytes <= 0) {

                @unlink($destinationPath);

                $this->addError('upload', 'The selected file is empty.');

                $this->upload = null;

                return;

            }



            if ($this->storedImportPath && $this->storedImportPath !== $newStoredPath) {

                $localDisk->delete($this->storedImportPath);

            }



            $this->storedImportPath = $newStoredPath;

            $this->stagedUploadName = $originalName !== '' ? $originalName : basename($newStoredPath);

            $this->stagedUploadBytes = (int) $copiedBytes;



            // A newly selected file invalidates any previous analysis/import preview.

            $this->headers = [];

            $this->previewRows = [];

            $this->totalRows = 0;

            $this->lastImportErrors = [];

            $this->mapping = array_fill_keys(array_keys(CoachSpreadsheetService::IMPORT_FIELDS), '');

            $this->importRunning = false;

            $this->importProcessed = 0;

            $this->importTotal = 0;

            $this->importCreated = 0;

            $this->importUpdated = 0;

            $this->importSkipped = 0;

            $this->importFailed = 0;



            // Do not keep the TemporaryUploadedFile around for future Livewire

            // requests. From here forward we only use the staged local file.

            $this->upload = null;

        } catch (Throwable $exception) {

            if (is_file($destinationPath)) {

                @unlink($destinationPath);

            }



            $this->storedImportPath = null;

            $this->stagedUploadName = null;

            $this->stagedUploadBytes = 0;

            $this->upload = null;



            $this->addError('upload', $exception->getMessage());

        } finally {

            if (is_resource($source)) {

                fclose($source);

            }



            if (is_resource($destination)) {

                fclose($destination);

            }

        }

    }



    public function analyzeUpload(CoachSpreadsheetService $service): void

    {

        $this->resetErrorBag('upload');



        if (! $this->storedImportPath || ! Storage::disk('local')->exists($this->storedImportPath)) {

            $this->addError('upload', 'Choose the CSV or Excel file again before analyzing it.');

            return;

        }



        try {

            $analysis = $service->analyze(Storage::disk('local')->path($this->storedImportPath));



            $this->headers = $analysis['headers'];

            $this->previewRows = $analysis['preview'];

            $this->totalRows = $analysis['total_rows'];

            $this->mapping = $service->suggestMapping($this->headers);

        } catch (Throwable $exception) {

            $this->addError('upload', 'The file could not be analyzed: ' . $exception->getMessage());

        }

    }



    public function startImport(CoachSpreadsheetService $service): void

    {

        if (! app(SportAvailabilityService::class)->isEnabled($this->selectedSport)) {

            Notification::make()

                ->title('Sport is disabled')

                ->body('This sport is currently disabled in Admin Settings and cannot be imported.')

                ->danger()->send();

            return;

        }



        if (! $this->storedImportPath || $this->importRunning) {

            return;

        }



        $gender = Coach::normalizeGender($this->selectedGender);

        if (! $gender) {

            Notification::make()

                ->title('Select a coach gender')

                ->body('Choose Male or Female before starting the import. Every imported coach will be assigned to that gender.')

                ->danger()->send();

            return;

        }

        $this->selectedGender = $gender;



        $hasName = filled($this->mapping['first_name'] ?? null) && filled($this->mapping['last_name'] ?? null);



        if (! filled($this->mapping['email'] ?? null) || ! $hasName) {

            Notification::make()

                ->title('Required mapping is missing')

                ->body('Map Email, First Name, and Last Name. Sport and Gender are automatically supplied by the import filters.')

                ->danger()->send();

            return;

        }

        try {
            $quickVisibilityUserIds = $this->resolveQuickVisibilityUserIds();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Choose a valid exclusivity audience')
                ->body($exception->getMessage())
                ->danger()
                ->send();
            return;
        }



        try {

            $service->deleteImportJob($this->importJobPath);



            $prepared = $service->prepareImport(

                Storage::disk('local')->path($this->storedImportPath),

                $this->mapping,

                (string) $this->selectedSport,

                $gender,

                auth()->id(),

            );



            $this->importJobPath = $prepared['job_path'];

            $this->decorateImportJobWithQuickVisibility(
                $this->importJobPath,
                $quickVisibilityUserIds,
            );

            $this->importTotal = (int) $prepared['total'];

            $this->importProcessed = 0;

            $this->importCreated = 0;

            $this->importUpdated = 0;

            $this->importSkipped = (int) $prepared['skipped'];

            $this->lastImportErrors = array_slice($prepared['errors'], 0, 100);

            $this->importFailed = count($prepared['errors']);

            $this->importRunning = $this->importTotal > 0;



            if (! $this->importRunning) {

                $this->finishImport($service);

            }

        } catch (Throwable $exception) {

            $this->importRunning = false;

            Notification::make()->title('Import could not start')->body($exception->getMessage())->danger()->persistent()->send();

        }

    }



    public function processNextBatch(CoachSpreadsheetService $service): void

    {

        if (! $this->importRunning || ! $this->importJobPath) return;

        $batchOffset = $this->importProcessed;
        $batchRows = $this->importJobRows($this->importJobPath, $batchOffset, $this->importBatchSize);

        try {

            $result = $service->processImportBatch($this->importJobPath, $batchOffset, $this->importBatchSize);

            $this->importProcessed += (int) $result['processed'];

            $this->importCreated += (int) $result['created'];

            $this->importUpdated += (int) $result['updated'];

            $this->importFailed += count($result['errors']);

            $this->lastImportErrors = array_slice(array_merge($this->lastImportErrors, $result['errors']), 0, 100);

            if ((int) $result['processed'] > 0) {
                $this->applyQuickVisibilityForBatch($this->importJobPath, $batchRows);
            }

            if ((bool) $result['done']) $this->finishImport($service);

        } catch (Throwable $exception) {

            $this->importRunning = false;

            Notification::make()->title('Import paused after ' . number_format($this->importProcessed) . ' rows')

                ->body($exception->getMessage())->danger()->persistent()->send();

        }

    }



    public function getImportProgressProperty(): int

    {

        if ($this->importTotal <= 0) return 0;

        return min(100, (int) floor(($this->importProcessed / $this->importTotal) * 100));

    }



    public function downloadTemplate(string $format, CoachSpreadsheetService $service)

    {

        abort_unless(in_array($format, ['csv', 'xlsx'], true), 404);

        abort_unless(app(SportAvailabilityService::class)->isEnabled($this->selectedSport), 404);

        $path = $service->createTemplate($format, (string) $this->selectedSport, Coach::normalizeGender($this->selectedGender));

        return response()->download($path)->deleteFileAfterSend(true);

    }



    public function resetImport(CoachSpreadsheetService $service): void

    {

        $service->deleteImportJob($this->importJobPath);

        if ($this->storedImportPath) Storage::disk('local')->delete($this->storedImportPath);



        $this->reset([

            'upload', 'stagedUploadName', 'stagedUploadBytes', 'headers', 'previewRows', 'totalRows',

            'storedImportPath', 'lastImportErrors', 'importRunning', 'importProcessed', 'importTotal',

            'importCreated', 'importUpdated', 'importSkipped', 'importFailed', 'importJobPath',

            'quickVisibilityType', 'quickVisibilityUserIds', 'quickVisibilityClubId', 'quickVisibilityLeagueId',

        ]);

        $this->mapping = array_fill_keys(array_keys(CoachSpreadsheetService::IMPORT_FIELDS), '');

    }



    protected function eligibleAudienceUserQuery(): Builder
    {
        return User::query()
            ->whereDoesntHave('roles', fn (Builder $roles): Builder => $roles->whereIn('name', [
                'Superadmin', 'superadmin', 'Super Admin', 'Admin', 'admin', 'Administrator',
            ]));
    }

    protected function formatAudienceUserLabel(User $user): string
    {
        $name = trim((string) ($user->first_name . ' ' . $user->last_name));
        $meta = collect([
            $user->email,
            filled($user->sport) ? Str::headline((string) $user->sport) : null,
        ])->filter()->implode(' · ');

        return ($name !== '' ? $name : ('User #' . $user->getKey())) . ($meta ? ' — ' . $meta : '');
    }

    protected function resolveQuickVisibilityUserIds(): array
    {
        $type = $this->quickVisibilityType;
        if (! in_array($type, ['public', 'users', 'club', 'league'], true)) {
            throw new \InvalidArgumentException('Choose a valid exclusivity option.');
        }

        if ($type === 'public') {
            return [];
        }

        $ids = match ($type) {
            'users' => $this->eligibleAudienceUserQuery()
                ->whereIn('id', collect($this->quickVisibilityUserIds)
                    ->map(fn ($id): int => (int) $id)
                    ->filter(fn (int $id): bool => $id > 0)
                    ->unique()
                    ->values()
                    ->all())
                ->pluck('id'),
            'club' => $this->audienceUserIdsForClub((int) $this->quickVisibilityClubId),
            'league' => $this->audienceUserIdsForLeague((int) $this->quickVisibilityLeagueId),
            default => collect(),
        };

        $ids = collect($ids)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            throw new \InvalidArgumentException(match ($type) {
                'users' => 'Select at least one user.',
                'club' => 'The selected club does not currently have any eligible users.',
                'league' => 'The selected league does not currently have any eligible users.',
                default => 'Choose an exclusivity audience.',
            });
        }

        return $ids->all();
    }

    protected function audienceUserIdsForClub(int $clubId)
    {
        if ($clubId <= 0 || ! Club::query()->whereKey($clubId)->exists()) {
            throw new \InvalidArgumentException('Choose a valid club.');
        }

        $ids = collect();

        if (Schema::hasColumn('users', 'club_id')) {
            $ids = $ids->merge($this->eligibleAudienceUserQuery()->where('club_id', $clubId)->pluck('id'));
        }

        if (Schema::hasColumn('users', 'legacy_club_id')) {
            $ids = $ids->merge($this->eligibleAudienceUserQuery()->where('legacy_club_id', $clubId)->pluck('id'));
        }

        if (Schema::hasColumn('users', 'club_league_id') && Schema::hasTable('club_leagues')) {
            $clubLeagueQuery = DB::table('club_leagues')->where('club_id', $clubId);
            if (Schema::hasColumn('club_leagues', 'deleted_at')) {
                $clubLeagueQuery->whereNull('deleted_at');
            }
            $clubLeagueIds = $clubLeagueQuery->pluck('id')->all();
            if ($clubLeagueIds !== []) {
                $ids = $ids->merge($this->eligibleAudienceUserQuery()->whereIn('club_league_id', $clubLeagueIds)->pluck('id'));
            }
        }

        return $ids->unique()->values();
    }

    protected function audienceUserIdsForLeague(int $leagueId)
    {
        if ($leagueId <= 0 || ! League::query()->whereKey($leagueId)->exists()) {
            throw new \InvalidArgumentException('Choose a valid league.');
        }

        $ids = collect();

        if (Schema::hasColumn('users', 'league_id')) {
            $ids = $ids->merge($this->eligibleAudienceUserQuery()->where('league_id', $leagueId)->pluck('id'));
        }

        if (Schema::hasColumn('users', 'legacy_league_id')) {
            $ids = $ids->merge($this->eligibleAudienceUserQuery()->where('legacy_league_id', $leagueId)->pluck('id'));
        }

        if (Schema::hasColumn('users', 'club_league_id') && Schema::hasTable('club_leagues')) {
            $clubLeagueQuery = DB::table('club_leagues')->where('league_id', $leagueId);
            if (Schema::hasColumn('club_leagues', 'deleted_at')) {
                $clubLeagueQuery->whereNull('deleted_at');
            }
            $clubLeagueIds = $clubLeagueQuery->pluck('id')->all();
            if ($clubLeagueIds !== []) {
                $ids = $ids->merge($this->eligibleAudienceUserQuery()->whereIn('club_league_id', $clubLeagueIds)->pluck('id'));
            }
        }

        return $ids->unique()->values();
    }

    protected function quickVisibilityAudienceLabel(array $resolvedUserIds): string
    {
        return match ($this->quickVisibilityType) {
            'users' => count($resolvedUserIds) === 1
                ? '1 selected user'
                : number_format(count($resolvedUserIds)) . ' selected users',
            'club' => (string) (Club::query()->whereKey((int) $this->quickVisibilityClubId)->value('name') ?: 'selected club'),
            'league' => (string) (League::query()->whereKey((int) $this->quickVisibilityLeagueId)->value('name') ?: 'selected league'),
            default => 'Public',
        };
    }

    protected function decorateImportJobWithQuickVisibility(?string $jobPath, array $resolvedUserIds): void
    {
        if (! $jobPath || $this->quickVisibilityType === 'public') {
            return;
        }

        $payload = $this->importJobPayload($jobPath);
        $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
        $schoolNames = collect($rows)
            ->pluck('school_name')
            ->map(fn ($name): string => $this->normalizeSchoolName((string) $name))
            ->filter()
            ->unique()
            ->values();

        $preexistingSchoolNames = collect();
        if ($schoolNames->isNotEmpty()) {
            $preexistingSchoolNames = School::withTrashed()
                ->whereIn(DB::raw('LOWER(TRIM(name))'), $schoolNames->all())
                ->pluck('name')
                ->map(fn ($name): string => $this->normalizeSchoolName((string) $name))
                ->filter()
                ->unique()
                ->values();
        }

        $payload['quick_visibility'] = [
            'type' => $this->quickVisibilityType,
            'user_ids' => array_values($resolvedUserIds),
            'audience_label' => $this->quickVisibilityAudienceLabel($resolvedUserIds),
            'preexisting_school_names' => $preexistingSchoolNames->all(),
        ];

        Storage::disk('local')->put($jobPath, json_encode($payload, JSON_THROW_ON_ERROR));
    }

    protected function importJobPayload(string $jobPath): array
    {
        if (! Storage::disk('local')->exists($jobPath)) {
            throw new \RuntimeException('The temporary import job could not be found.');
        }

        $payload = json_decode(Storage::disk('local')->get($jobPath), true, flags: JSON_THROW_ON_ERROR);
        return is_array($payload) ? $payload : [];
    }

    protected function importJobRows(string $jobPath, int $offset, int $limit): array
    {
        $payload = $this->importJobPayload($jobPath);
        $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];

        return array_slice($rows, max(0, $offset), max(1, $limit));
    }

    protected function applyQuickVisibilityForBatch(string $jobPath, array $batchRows): void
    {
        $payload = $this->importJobPayload($jobPath);
        $meta = is_array($payload['quick_visibility'] ?? null) ? $payload['quick_visibility'] : [];
        $type = (string) ($meta['type'] ?? 'public');

        if ($type === 'public') {
            return;
        }

        $userIds = collect($meta['user_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($userIds === []) {
            throw new \RuntimeException('Quick exclusivity has no eligible users. The import was paused so the new coaches are not intentionally left unrestricted.');
        }

        $emails = collect($batchRows)
            ->pluck('email')
            ->map(fn ($email): string => Str::lower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values();

        $visibility = app(RecruitingVisibilityService::class);

        if ($emails->isNotEmpty()) {
            $coachIds = Coach::query()
                ->whereIn('email', $emails->all())
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $visibility->syncCoaches($coachIds, $userIds);
        }

        // Only make schools themselves exclusive when the school was created by
        // this import. For an already-existing public school, only the imported
        // coaches are restricted so its unrelated coaches stay visible.
        $preexisting = collect($meta['preexisting_school_names'] ?? [])
            ->map(fn ($name): string => $this->normalizeSchoolName((string) $name))
            ->filter()
            ->flip();

        $newSchoolNames = collect($batchRows)
            ->pluck('school_name')
            ->map(fn ($name): string => $this->normalizeSchoolName((string) $name))
            ->filter()
            ->reject(fn (string $name): bool => $preexisting->has($name))
            ->unique()
            ->values();

        if ($newSchoolNames->isNotEmpty()) {
            $schoolIds = School::query()
                ->whereIn(DB::raw('LOWER(TRIM(name))'), $newSchoolNames->all())
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $visibility->syncSchools($schoolIds, $userIds);
        }
    }

    protected function quickVisibilitySummaryForJob(?string $jobPath): ?string
    {
        if (! $jobPath || ! Storage::disk('local')->exists($jobPath)) {
            return null;
        }

        $payload = $this->importJobPayload($jobPath);
        $meta = is_array($payload['quick_visibility'] ?? null) ? $payload['quick_visibility'] : [];
        if (($meta['type'] ?? 'public') === 'public') {
            return null;
        }

        $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
        $emails = collect($rows)->pluck('email')->filter()->unique()->values()->all();
        $coachCount = $emails === [] ? 0 : Coach::query()->whereIn('email', $emails)->count();

        $preexisting = collect($meta['preexisting_school_names'] ?? [])->flip();
        $newSchoolNames = collect($rows)
            ->pluck('school_name')
            ->map(fn ($name): string => $this->normalizeSchoolName((string) $name))
            ->filter()
            ->reject(fn (string $name): bool => $preexisting->has($name))
            ->unique()
            ->values();
        $schoolCount = $newSchoolNames->isEmpty()
            ? 0
            : School::query()->whereIn(DB::raw('LOWER(TRIM(name))'), $newSchoolNames->all())->count();

        $audience = trim((string) ($meta['audience_label'] ?? 'selected audience'));

        return sprintf(
            'Quick exclusivity applied to %d coach%s%s for %s.',
            $coachCount,
            $coachCount === 1 ? '' : 'es',
            $schoolCount > 0 ? ' and ' . $schoolCount . ' new school' . ($schoolCount === 1 ? '' : 's') : '',
            $audience !== '' ? $audience : 'selected audience',
        );
    }

    protected function normalizeSchoolName(string $name): string
    {
        return strtolower(trim((string) preg_replace('/\s+/', ' ', $name)));
    }

    private function finishImport(CoachSpreadsheetService $service): void

    {

        $quickVisibilitySummary = $this->quickVisibilitySummaryForJob($this->importJobPath);

        $this->importRunning = false;

        $service->deleteImportJob($this->importJobPath);

        $this->importJobPath = null;



        $body = sprintf('%d created, %d updated, %d blank rows skipped, %d rows failed.',
            $this->importCreated, $this->importUpdated, $this->importSkipped, $this->importFailed);

        if ($quickVisibilitySummary) {
            $body .= ' ' . $quickVisibilitySummary;
        }

        Notification::make()->title('Coach import completed')

            ->body($body)

            ->success()->persistent()->send();

    }

}