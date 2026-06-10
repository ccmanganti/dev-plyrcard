<?php

namespace App\Filament\Widgets;

use App\Services\GoHighLevelService;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GhlProfileViewersWidget extends Widget
{
    protected string $view = 'filament.widgets.ghl-profile-viewers-widget';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public bool $loaded = false;

    public int $count = 0;

    public array $contacts = [];

    public ?string $error = null;

    public function mount(): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $cached = Cache::get($this->cacheKey($user->id));

        if (is_array($cached)) {
            $this->loaded = true;
            $this->count = (int) ($cached['count'] ?? 0);
            $this->contacts = $cached['contacts'] ?? [];
            $this->error = $cached['error'] ?? null;
        }
    }

    public function loadProfileViewers(): void
    {
        $this->dispatch('ghl-profile-viewers-debug', result: [
            'stage' => 'button_clicked',
            'message' => 'Profile Views clicked.',
        ]);

        $user = Auth::user();

        $this->dispatch('ghl-profile-viewers-debug', result: [
            'stage' => 'auth_user',
            'user_id' => $user?->id,
            'email' => $user?->email,
            'has_ghl_location_id' => filled($user?->ghl_location_id),
            'ghl_location_id' => $user?->ghl_location_id,
            'has_ghl_api_key' => filled($user?->ghl_api_key),
        ]);

        if (! $user) {
            $this->error = 'You must be logged in to view this data.';
            $this->loaded = true;

            return;
        }

        if (! method_exists($user, 'hasGhlConnection') || ! $user->hasGhlConnection()) {
            $this->error = 'No GHL connection is configured for this user.';
            $this->loaded = true;

            $this->dispatch('ghl-profile-viewers-debug', result: [
                'stage' => 'error',
                'message' => $this->error,
            ]);

            return;
        }

        if (! method_exists($user, 'hasGhlLocationId') || ! $user->hasGhlLocationId()) {
            $this->error = 'GHL Location ID is missing for this user.';
            $this->loaded = true;

            $this->dispatch('ghl-profile-viewers-debug', result: [
                'stage' => 'error',
                'message' => $this->error,
            ]);

            return;
        }

        try {
            $result = app(GoHighLevelService::class)->getViewedProfileContactsForUser($user);

            $this->dispatch('ghl-profile-viewers-debug', result: [
                'stage' => 'ghl_result',
                'result' => $result,
            ]);

            $this->count = (int) ($result['count'] ?? 0);
            $this->contacts = $result['contacts'] ?? [];
            $this->error = $result['error'] ?? null;
            $this->loaded = true;

            Cache::put($this->cacheKey($user->id), [
                'count' => $this->count,
                'contacts' => $this->contacts,
                'error' => $this->error,
            ], now()->addMinutes(10));

            if ($this->error) {
                Notification::make()
                    ->title('Profile Views')
                    ->body($this->error)
                    ->danger()
                    ->send();

                return;
            }

            Notification::make()
                ->title('Profile Views')
                ->body("Found {$this->count} contact(s).")
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            Log::error('GHL profile viewers widget failed.', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $this->error = 'Unable to load Profile Views right now.';
            $this->loaded = true;

            $this->dispatch('ghl-profile-viewers-debug', result: [
                'stage' => 'exception',
                'error' => true,
                'message' => $exception->getMessage(),
            ]);

            Notification::make()
                ->title('Profile Views')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function refreshProfileViewers(): void
    {
        $user = Auth::user();

        if ($user) {
            Cache::forget($this->cacheKey($user->id));
        }

        $this->loaded = false;
        $this->count = 0;
        $this->contacts = [];
        $this->error = null;

        $this->loadProfileViewers();
    }

    protected function cacheKey(int|string $userId): string
    {
        return "user:{$userId}:ghl:viewed-profile-contacts";
    }
}