<?php

namespace App\Filament\Resources\Coaches\Pages;

use App\Filament\Resources\Coaches\CoachResource;
use App\Models\Coach;
use App\Services\CoachSpreadsheetService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
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

    /**
     * Stage the Livewire temporary upload immediately while the temp file is still
     * available. This deliberately avoids Laravel's `file|max` validation rules on
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
            $service->deleteImportJob($this->importJobPath);

            $prepared = $service->prepareImport(
                Storage::disk('local')->path($this->storedImportPath),
                $this->mapping,
                (string) $this->selectedSport,
                $gender,
                auth()->id(),
            );

            $this->importJobPath = $prepared['job_path'];
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

        try {
            $result = $service->processImportBatch($this->importJobPath, $this->importProcessed, $this->importBatchSize);
            $this->importProcessed += (int) $result['processed'];
            $this->importCreated += (int) $result['created'];
            $this->importUpdated += (int) $result['updated'];
            $this->importFailed += count($result['errors']);
            $this->lastImportErrors = array_slice(array_merge($this->lastImportErrors, $result['errors']), 0, 100);
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
        ]);
        $this->mapping = array_fill_keys(array_keys(CoachSpreadsheetService::IMPORT_FIELDS), '');
    }

    private function finishImport(CoachSpreadsheetService $service): void
    {
        $this->importRunning = false;
        $service->deleteImportJob($this->importJobPath);
        $this->importJobPath = null;

        Notification::make()->title('Coach import completed')
            ->body(sprintf('%d created, %d updated, %d blank rows skipped, %d rows failed.',
                $this->importCreated, $this->importUpdated, $this->importSkipped, $this->importFailed))
            ->success()->persistent()->send();
    }
}