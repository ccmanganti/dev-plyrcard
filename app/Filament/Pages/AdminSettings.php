<?php

namespace App\Filament\Pages;

use App\Services\PlyrPointCatalogService;
use App\Services\SportAvailabilityService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use InvalidArgumentException;
use Livewire\WithFileUploads;

class AdminSettings extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.pages.admin-settings';

    protected static ?string $slug = 'admin-settings';
    protected static ?string $navigationLabel = 'Admin Settings';
    protected static ?string $title = 'Admin Settings';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?int $navigationSort = 95;

    public array $enabledSports = [];

    public bool $plyrPointEditorOpen = false;
    public ?string $plyrPointEditingKey = null;
    public string $plyrPointName = '';
    public int $plyrPointPoints = 50;
    public string $plyrPointDescription = '';
    public string $plyrPointCta = '';
    public bool $plyrPointEnabled = true;
    public string $plyrPointYoutube = '';
    public array $plyrPointUploads = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user || ! method_exists($user, 'hasRole')) {
            return false;
        }

        return $user->hasRole('Superadmin')
            || $user->hasRole('superadmin')
            || $user->hasRole('Super Admin')
            || $user->hasRole('Admin')
            || $user->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->enabledSports = app(SportAvailabilityService::class)->enabledKeys();
    }

    public function getSportsProperty(): array
    {
        return app(SportAvailabilityService::class)->availabilityRows();
    }

    public function getPlyrPointServicesProperty(): array
    {
        return app(PlyrPointCatalogService::class)->adminRows();
    }

    public function enableAllSports(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->enabledSports = array_keys(app(SportAvailabilityService::class)->allOptions());
    }

    public function disableAllSports(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->enabledSports = [];
    }

    public function saveSports(): void
    {
        abort_unless(static::canAccess(), 403);

        $sports = app(SportAvailabilityService::class);
        $allowed = array_keys($sports->allOptions());

        $this->enabledSports = collect($this->enabledSports)
            ->map(fn ($value): string => strtolower(trim((string) $value)))
            ->filter(fn (string $value): bool => in_array($value, $allowed, true))
            ->unique()
            ->values()
            ->all();

        $sports->saveEnabledKeys($this->enabledSports);

        Notification::make()
            ->title('Sport availability updated')
            ->success()
            ->send();
    }

    public function togglePlyrPointService(string $serviceKey): void
    {
        abort_unless(static::canAccess(), 403);

        $catalog = app(PlyrPointCatalogService::class);
        $service = $catalog->find($serviceKey);
        abort_unless($service, 404);

        $catalog->setEnabled($serviceKey, ! (bool) ($service['enabled'] ?? true));

        Notification::make()
            ->title((bool) ($service['enabled'] ?? true) ? 'Service turned off' : 'Service turned on')
            ->success()
            ->send();
    }

    public function createPlyrPointService(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->resetPlyrPointEditor();
        $this->plyrPointEditorOpen = true;
    }

    public function editPlyrPointService(string $serviceKey): void
    {
        abort_unless(static::canAccess(), 403);

        $service = app(PlyrPointCatalogService::class)->find($serviceKey);
        abort_unless($service, 404);

        $this->resetValidation();
        $this->plyrPointEditingKey = $serviceKey;
        $this->plyrPointName = (string) ($service['name'] ?? '');
        $this->plyrPointPoints = max(1, (int) ($service['points'] ?? 1));
        $this->plyrPointDescription = (string) ($service['description'] ?? '');
        $this->plyrPointCta = (string) ($service['cta'] ?? '');
        $this->plyrPointEnabled = (bool) ($service['enabled'] ?? true);
        $this->plyrPointYoutube = '';
        $this->plyrPointUploads = [];
        $this->plyrPointEditorOpen = true;
    }

    public function closePlyrPointEditor(): void
    {
        $this->plyrPointEditorOpen = false;
        $this->resetValidation();
        $this->plyrPointYoutube = '';
        $this->plyrPointUploads = [];
    }

    public function togglePlyrPointEditorEnabled(): void
    {
        $this->plyrPointEnabled = ! $this->plyrPointEnabled;
    }

    public function savePlyrPointService(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->validate([
            'plyrPointName' => ['required', 'string', 'max:80'],
            'plyrPointPoints' => ['required', 'integer', 'min:1', 'max:100000'],
            'plyrPointDescription' => ['nullable', 'string', 'max:240'],
            'plyrPointCta' => ['nullable', 'string', 'max:80'],
            'plyrPointEnabled' => ['boolean'],
        ]);

        $key = app(PlyrPointCatalogService::class)->saveService([
            'name' => $data['plyrPointName'],
            'points' => $data['plyrPointPoints'],
            'description' => $data['plyrPointDescription'] ?? '',
            'cta' => $data['plyrPointCta'] ?? '',
            'enabled' => $data['plyrPointEnabled'],
        ], $this->plyrPointEditingKey);

        $this->plyrPointEditingKey = $key;

        Notification::make()
            ->title('PLYR Points service saved')
            ->success()
            ->send();
    }

    public function deletePlyrPointService(): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless($this->plyrPointEditingKey, 404);

        try {
            app(PlyrPointCatalogService::class)->deleteService($this->plyrPointEditingKey);
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
            return;
        }

        $this->resetPlyrPointEditor();
        $this->plyrPointEditorOpen = false;

        Notification::make()
            ->title('Service deleted')
            ->success()
            ->send();
    }

    public function addPlyrPointYoutubeSample(): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless($this->plyrPointEditingKey, 404);

        $this->validate([
            'plyrPointYoutube' => ['required', 'url', 'max:1000'],
        ], [
            'plyrPointYoutube.required' => 'Paste a YouTube URL first.',
        ]);

        try {
            app(PlyrPointCatalogService::class)->addYoutubeSample($this->plyrPointEditingKey, $this->plyrPointYoutube);
        } catch (InvalidArgumentException $exception) {
            $this->addError('plyrPointYoutube', $exception->getMessage());
            return;
        }

        $this->resetErrorBag('plyrPointYoutube');
        $this->plyrPointYoutube = '';

        Notification::make()->title('YouTube sample added')->success()->send();
    }

    public function uploadPlyrPointSamples(): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless($this->plyrPointEditingKey, 404);

        $this->validate([
            'plyrPointUploads' => ['required', 'array', 'min:1', 'max:12'],
            'plyrPointUploads.*' => ['file', 'max:12288', 'mimes:jpg,jpeg,png,webp,gif,mp4,mov,m4v,webm'],
        ], [
            'plyrPointUploads.*.max' => 'Each sample must be 12 MB or smaller. Use YouTube for larger videos.',
            'plyrPointUploads.*.mimes' => 'Samples must be an image or video file.',
        ]);

        try {
            foreach ($this->plyrPointUploads as $file) {
                app(PlyrPointCatalogService::class)->addUploadedSample($this->plyrPointEditingKey, $file);
            }
        } catch (InvalidArgumentException $exception) {
            $this->addError('plyrPointUploads', $exception->getMessage());
            return;
        }

        $this->resetErrorBag('plyrPointUploads');
        $this->plyrPointUploads = [];

        Notification::make()->title('Sample media added')->success()->send();
    }

    public function removePlyrPointSample(string $sampleId): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless($this->plyrPointEditingKey, 404);

        app(PlyrPointCatalogService::class)->removeSample($this->plyrPointEditingKey, $sampleId);

        Notification::make()->title('Sample removed')->success()->send();
    }

    private function resetPlyrPointEditor(): void
    {
        $this->resetValidation();
        $this->plyrPointEditingKey = null;
        $this->plyrPointName = '';
        $this->plyrPointPoints = 50;
        $this->plyrPointDescription = '';
        $this->plyrPointCta = '';
        $this->plyrPointEnabled = true;
        $this->plyrPointYoutube = '';
        $this->plyrPointUploads = [];
    }
}