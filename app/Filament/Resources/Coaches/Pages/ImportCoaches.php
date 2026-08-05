<?php

namespace App\Filament\Resources\Coaches\Pages;

use App\Filament\Resources\Coaches\CoachResource;
use App\Services\CoachSpreadsheetService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

class ImportCoaches extends Page
{
    use WithFileUploads;

    protected static string $resource = CoachResource::class;
    protected string $view = 'filament.resources.coaches.pages.import-coaches';

    #[Url(as: 'sport')]
    public ?string $selectedSport = null;

    public TemporaryUploadedFile|string|null $upload = null;
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

        $this->mapping = array_fill_keys(array_keys(CoachSpreadsheetService::IMPORT_FIELDS), '');
    }

    public function getTitle(): string
    {
        return 'Import ' . (CoachResource::sportOptions()[$this->selectedSport] ?? 'Sport') . ' Coaches';
    }

    public function analyzeUpload(CoachSpreadsheetService $service): void
    {
        $this->validate(['upload' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:20480']]);

        $this->storedImportPath = $this->upload->store('coach-imports', 'local');
        $analysis = $service->analyze(Storage::disk('local')->path($this->storedImportPath));

        $this->headers = $analysis['headers'];
        $this->previewRows = $analysis['preview'];
        $this->totalRows = $analysis['total_rows'];
        $this->mapping = $service->suggestMapping($this->headers);
    }

    public function startImport(CoachSpreadsheetService $service): void
    {
        if (! $this->storedImportPath || $this->importRunning) {
            return;
        }

        $hasName = filled($this->mapping['first_name'] ?? null)
            && filled($this->mapping['last_name'] ?? null);

        if (! filled($this->mapping['email'] ?? null) || ! $hasName) {
            Notification::make()
                ->title('Required mapping is missing')
                ->body('Map Email, First Name, and Last Name. Sport is automatically supplied by the selected folder.')
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
        if (! $this->importRunning || ! $this->importJobPath) {
            return;
        }

        try {
            $result = $service->processImportBatch(
                $this->importJobPath,
                $this->importProcessed,
                $this->importBatchSize,
            );

            $this->importProcessed += (int) $result['processed'];
            $this->importCreated += (int) $result['created'];
            $this->importUpdated += (int) $result['updated'];
            $this->importFailed += count($result['errors']);
            $this->lastImportErrors = array_slice(
                array_merge($this->lastImportErrors, $result['errors']),
                0,
                100,
            );

            if ((bool) $result['done']) {
                $this->finishImport($service);
            }
        } catch (Throwable $exception) {
            $this->importRunning = false;
            Notification::make()
                ->title('Import paused after ' . number_format($this->importProcessed) . ' rows')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function getImportProgressProperty(): int
    {
        if ($this->importTotal <= 0) {
            return 0;
        }

        return min(100, (int) floor(($this->importProcessed / $this->importTotal) * 100));
    }

    public function downloadTemplate(string $format, CoachSpreadsheetService $service)
    {
        abort_unless(in_array($format, ['csv', 'xlsx'], true), 404);
        $path = $service->createTemplate($format, (string) $this->selectedSport);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    public function resetImport(CoachSpreadsheetService $service): void
    {
        $service->deleteImportJob($this->importJobPath);

        if ($this->storedImportPath) {
            Storage::disk('local')->delete($this->storedImportPath);
        }

        $this->reset([
            'upload', 'headers', 'previewRows', 'totalRows', 'storedImportPath',
            'lastImportErrors', 'importRunning', 'importProcessed', 'importTotal',
            'importCreated', 'importUpdated', 'importSkipped', 'importFailed', 'importJobPath',
        ]);

        $this->mapping = array_fill_keys(array_keys(CoachSpreadsheetService::IMPORT_FIELDS), '');
    }

    private function finishImport(CoachSpreadsheetService $service): void
    {
        $this->importRunning = false;
        $service->deleteImportJob($this->importJobPath);
        $this->importJobPath = null;

        Notification::make()
            ->title('Coach import completed')
            ->body(sprintf(
                '%d created, %d updated, %d blank rows skipped, %d rows failed.',
                $this->importCreated,
                $this->importUpdated,
                $this->importSkipped,
                $this->importFailed,
            ))
            ->success()
            ->persistent()
            ->send();
    }
}