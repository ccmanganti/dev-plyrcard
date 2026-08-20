<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PlayerActivityEmailService
{
    public function __construct(
        protected PlyrcardSystemEmailService $systemEmail,
    ) {}

    public function profileViewed(User $player, Website $website, Request $request): void
    {
        $this->send($player, $website, 'profile_view', 'website', $request);
    }

    public function socialClicked(User $player, Website $website, string $platform, Request $request): void
    {
        $platform = strtolower(trim($platform));

        // On the player card, YouTube is the highlight destination. Keep a
        // dedicated activity type so the athlete gets a clear highlight alert.
        $type = $platform === 'youtube' ? 'highlight_click' : 'social_click';

        $this->send($player, $website, $type, $platform, $request);
    }

    protected function send(User $player, Website $website, string $type, string $platform, Request $request): void
    {
        $recipient = $player->email ?: $player->personal_email;
        if (! $recipient || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        // Avoid emailing the athlete for their own authenticated profile visits.
        if (auth()->check() && (int) auth()->id() === (int) $player->getKey()) {
            return;
        }

        $viewer = hash_hmac('sha256', implode('|', [
            (string) $request->ip(),
            (string) $request->userAgent(),
            strtolower((string) $request->query('rc_email', '')),
        ]), (string) config('app.key'));

        // One notification per visitor/activity in a 15-minute window. Tracking
        // itself remains untouched; this only prevents notification floods.
        $key = "plyrcard:activity-mail:{$player->id}:{$type}:{$platform}:{$viewer}";
        if (! Cache::add($key, true, now()->addMinutes(15))) {
            return;
        }

        $result = $this->systemEmail->sendPlayerActivity(
            player: $player,
            website: $website,
            activityType: $type,
            platform: $platform,
            viewerEmail: filter_var($request->query('rc_email'), FILTER_VALIDATE_EMAIL) ?: null,
        );

        if (! ($result['success'] ?? false)) {
            Cache::forget($key);

            Log::warning('PLYRCARD player activity email failed through native PHP mail().', [
                'user_id' => $player->id,
                'activity_type' => $type,
                'platform' => $platform,
                'error' => $result['error'] ?? 'Unknown email error.',
            ]);
        }
    }
}