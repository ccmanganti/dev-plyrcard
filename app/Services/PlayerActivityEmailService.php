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
        protected CoachViewerIdentityService $coachIdentity,
    ) {}

    public function profileViewed(User $player, Website $website, Request $request): void
    {
        $this->send($player, $website, 'profile_view', 'website', $request);
    }

    public function socialClicked(User $player, Website $website, string $platform, Request $request): void
    {
        $platform = strtolower(trim($platform));
        $type = $platform === 'youtube' ? 'highlight_click' : 'social_click';

        $this->send($player, $website, $type, $platform, $request);
    }

    protected function send(User $player, Website $website, string $type, string $platform, Request $request): void
    {
        $recipient = $player->email ?: $player->personal_email;
        if (! $recipient || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        if (auth()->check() && (int) auth()->id() === (int) $player->getKey()) {
            return;
        }

        // IMPORTANT: anonymous/direct visitors must never trigger an athlete
        // notification. Only a visitor matched to the player's Coach Database,
        // sent-message recipient records, school records, or an immediately
        // preceding coach-attributed tracking event is allowed through.
        $coach = $this->coachIdentity->resolve(
            player: $player,
            request: $request,
            eventType: $type === 'profile_view' ? 'profile_view' : 'link_click',
            platform: $platform,
        );

        if (! ($coach['matched'] ?? false)) {
            Log::debug('PLYRCARD activity email skipped for anonymous/unmatched visitor.', [
                'user_id' => $player->id,
                'activity_type' => $type,
                'platform' => $platform,
            ]);

            return;
        }

        $viewerIdentity = strtolower(trim((string) (
            $coach['contact_id']
                ?? $coach['email']
                ?? $coach['name']
                ?? 'coach'
        )));

        // One email per known coach/activity in a 15-minute window. Anonymous
        // traffic never reaches this point and therefore never consumes a key.
        $key = 'plyrcard:activity-mail:' . implode(':', [
            $player->id,
            $type,
            $platform,
            hash('sha256', $viewerIdentity),
        ]);

        if (! Cache::add($key, true, now()->addMinutes(15))) {
            return;
        }

        $result = $this->systemEmail->sendPlayerActivity(
            player: $player,
            website: $website,
            activityType: $type,
            platform: $platform,
            viewerEmail: $coach['email'] ?? null,
            viewerName: $coach['name'] ?? null,
            viewerSchool: $coach['school'] ?? null,
        );

        if (! ($result['success'] ?? false)) {
            Cache::forget($key);

            Log::warning('PLYRCARD coach activity email failed through native PHP mail().', [
                'user_id' => $player->id,
                'activity_type' => $type,
                'platform' => $platform,
                'coach_contact_id' => $coach['contact_id'] ?? null,
                'coach_match_source' => $coach['source'] ?? null,
                'error' => $result['error'] ?? 'Unknown email error.',
            ]);
        }
    }
}