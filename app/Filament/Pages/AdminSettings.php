<?php

namespace App\Filament\Pages;

use App\Services\SportAvailabilityService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class AdminSettings extends Page
{
    protected string $view = 'filament.pages.admin-settings';

    protected static ?string $slug = 'admin-settings';
    protected static ?string $navigationLabel = 'Admin Settings';
    protected static ?string $title = 'Admin Settings';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?int $navigationSort = 95;

    public array $enabledSports = [];

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
}
