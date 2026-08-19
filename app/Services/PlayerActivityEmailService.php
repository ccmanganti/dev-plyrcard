<?php

namespace App\Services;

use App\Mail\PlayerActivityMail;
use App\Models\User;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PlayerActivityEmailService
{
    public function profileViewed(User $player, Website $website, Request $request): void
    {
        $this->send($player, $website, 'profile_view', 'website', $request);
    }

    public function socialClicked(User $player, Website $website, string $platform, Request $request): void
    {
        $this->send($player, $website, 'social_click', strtolower($platform), $request);
    }

    protected function send(User $player, Website $website, string $type, string $platform, Request $request): void
    {
        $recipient = $player->email ?: $player->personal_email;
        if (! $recipient || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        // Avoid refresh/bot storms while still notifying about new visitors/activity.
        $visitor = hash_hmac('sha256', implode('|', [
            (string) $request->ip(),
            (string) $request->userAgent(),
            strtolower((string) $request->query('rc_email', '')),
        ]), (string) config('app.key'));
        $key = "plyrcard:activity-mail:{$player->id}:{$type}:{$platform}:{$visitor}";
        if (! Cache::add($key, true, now()->addMinutes(15))) {
            return;
        }

        try {
            Mail::to($recipient)->send(new PlayerActivityMail(
                player: $player,
                website: $website,
                activityType: $type,
                platform: $platform,
                viewerEmail: filter_var($request->query('rc_email'), FILTER_VALIDATE_EMAIL) ?: null,
            ));
        } catch (\Throwable $exception) {
            Cache::forget($key);
            Log::warning('PLYRCARD player activity email failed.', [
                'user_id' => $player->id,
                'activity_type' => $type,
                'platform' => $platform,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
