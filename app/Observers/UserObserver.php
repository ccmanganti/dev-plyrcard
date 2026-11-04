<?php

namespace App\Observers;

use App\Jobs\SyncProfileCompletionToGhl;
use App\Models\User;
use App\Services\PlyrcardSystemEmailService;
use App\Services\ProfileCompletionService;
use App\Services\SupportAlertService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    public function created(User $user): void
    {
        $userId = (int) $user->getKey();

        DB::afterCommit(function () use ($userId): void {
            $createdUser = User::query()->find($userId);

            if (! $createdUser) {
                return;
            }

            try {
                app(SupportAlertService::class)->sendNewRegistration($createdUser);
            } catch (\Throwable $exception) {
                Log::warning('PLYRCARD new user admin alert failed.', [
                    'user_id' => $userId,
                    'error' => $exception->getMessage(),
                ]);
            }

            // Native registration already sends its welcome email after Stripe/setup work.
            // Other creation paths (public intake/admin introduction/import) use this observer
            // so the new player still receives the same account-created notification.
            if (strtolower(trim((string) $createdUser->registration_source)) !== 'native-registration') {
                try {
                    app(PlyrcardSystemEmailService::class)->sendRegistrationWelcome($createdUser);
                } catch (\Throwable $exception) {
                    Log::warning('PLYRCARD new user welcome email failed.', [
                        'user_id' => $userId,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        });
    }

    public function updated(User $user): void
    {
        if (! $user->wasChanged('raw_player_images')) {
            return;
        }

        $decodeImages = static function ($value): array {
            if (is_array($value)) {
                return array_values(array_filter($value, fn ($item): bool => filled($item)));
            }

            if (is_string($value) && trim($value) !== '') {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    return array_values(array_filter($decoded, fn ($item): bool => filled($item)));
                }
            }

            return [];
        };

        $before = $decodeImages($user->getOriginal('raw_player_images'));
        $after = $decodeImages($user->raw_player_images ?? []);
        $newCount = max(0, count($after) - count($before));

        if ($newCount < 1) {
            return;
        }

        $userId = (int) $user->getKey();

        DB::afterCommit(function () use ($userId, $newCount): void {
            $player = User::query()->find($userId);

            if (! $player) {
                return;
            }

            try {
                app(SupportAlertService::class)->sendPlayerPhotosUploaded(
                    $player,
                    $newCount,
                    'My Photos / raw player images',
                );
            } catch (\Throwable $exception) {
                Log::warning('PLYRCARD player photo upload admin alert failed.', [
                    'user_id' => $userId,
                    'photo_count' => $newCount,
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }

    public function saved(User $user): void
    {
        $completion = app(ProfileCompletionService::class)->calculate($user);

        $user->forceFill([
            'profile_completion_percentage' => $completion,
        ])->saveQuietly();

        if ($completion >= 75 && is_null($user->profile_completion_threshold_sent_at)) {
            SyncProfileCompletionToGhl::dispatch($user->id, $completion);
        }
    }
}