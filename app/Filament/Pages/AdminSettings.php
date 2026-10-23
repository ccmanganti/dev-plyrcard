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
    public array $plyrPointEnabled = [];
    public array $plyrPointYoutube = [];
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
        $this->plyrPointEnabled = app(PlyrPointCatalogService::class)->enabledKeys();
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
            ->body('New sport selections now follow the enabled sports list. Existing athlete and coach records were not changed.')
            ->success()
            ->send();
    }

    public function savePlyrPointServices(): void
    {
        abort_unless(static::canAccess(), 403);

        $service = app(PlyrPointCatalogService::class);
        $allowed = array_keys(PlyrPointCatalogService::DEFAULTS);

        $this->plyrPointEnabled = collect($this->plyrPointEnabled)
            ->map(fn ($value): string => strtolower(trim((string) $value)))
            ->filter(fn (string $value): bool => in_array($value, $allowed, true))
            ->unique()
            ->values()
            ->all();

        $service->saveEnabledKeys($this->plyrPointEnabled);

        Notification::make()
            ->title('PLYR Points services updated')
            ->body('The player-facing PLYR Points catalog now follows these enabled services.')
            ->success()
            ->send();
    }

    public function addPlyrPointYoutubeSample(string $serviceKey): void
    {
        abort_unless(static::canAccess(), 403);
        $this->assertPlyrPointKey($serviceKey);

        $field = 'plyrPointYoutube.' . $serviceKey;
        $url = trim((string) ($this->plyrPointYoutube[$serviceKey] ?? ''));

        $this->validate([
            $field => ['required', 'url', 'max:1000'],
        ], [
            $field . '.required' => 'Paste a YouTube URL first.',
        ]);

        try {
            app(PlyrPointCatalogService::class)->addYoutubeSample($serviceKey, $url);
        } catch (InvalidArgumentException $exception) {
            $this->addError($field, $exception->getMessage());
            return;
        }

        $this->resetErrorBag($field);
        $this->plyrPointYoutube[$serviceKey] = '';

        Notification::make()
            ->title('YouTube sample added')
            ->success()
            ->send();
    }

    public function uploadPlyrPointSamples(string $serviceKey): void
    {
        abort_unless(static::canAccess(), 403);
        $this->assertPlyrPointKey($serviceKey);

        $uploads = $this->plyrPointUploads[$serviceKey] ?? [];
        if (! is_array($uploads)) {
            $uploads = [$uploads];
        }

        $field = 'plyrPointUploads.' . $serviceKey;
        $this->validate([
            $field => ['required', 'array', 'min:1', 'max:12'],
            $field . '.*' => ['file', 'max:12288', 'mimes:jpg,jpeg,png,webp,gif,mp4,mov,m4v,webm'],
        ], [
            $field . '.*.max' => 'Each sample must be 12 MB or smaller. Use YouTube for larger videos.',
            $field . '.*.mimes' => 'Samples must be an image or video file.',
        ]);

        try {
            foreach ($uploads as $file) {
                app(PlyrPointCatalogService::class)->addUploadedSample($serviceKey, $file);
            }
        } catch (InvalidArgumentException $exception) {
            $this->addError('plyrPointUploads.' . $serviceKey, $exception->getMessage());
            return;
        }

        $this->resetErrorBag('plyrPointUploads.' . $serviceKey);
        $this->plyrPointUploads[$serviceKey] = [];

        Notification::make()
            ->title('Sample media uploaded')
            ->body('The new media is now available in the player-facing sample slider.')
            ->success()
            ->send();
    }

    public function removePlyrPointSample(string $serviceKey, string $sampleId): void
    {
        abort_unless(static::canAccess(), 403);
        $this->assertPlyrPointKey($serviceKey);

        app(PlyrPointCatalogService::class)->removeSample($serviceKey, $sampleId);

        Notification::make()
            ->title('Sample removed')
            ->success()
            ->send();
    }

    private function assertPlyrPointKey(string $serviceKey): void
    {
        abort_unless(array_key_exists($serviceKey, PlyrPointCatalogService::DEFAULTS), 404);
    }
}