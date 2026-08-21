<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use Illuminate\Http\Request;
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
        $platform = $this->normalizePlatform($platform);

        if (! in_array($platform, ['instagram', 'youtube', 'x'], true)) {
            Log::debug('PLYRCARD social activity email skipped for unsupported platform.', [
                'user_id' => $player->id,
                'platform' => $platform,
            ]);

            return;
        }

        // YouTube is treated as the player's highlight/video destination.
        // Instagram and X are normal social-link clicks.
        $type = $platform === 'youtube' ? 'highlight_click' : 'social_click';

        $this->send($player, $website, $type, $platform, $request);
    }

    /**
     * Social URLs created by the External Tracking URL Generator already carry
     * rc_contact_id / rc_email. ExternalSocialTrackingController resolves those
     * values before the redirect is sent. Reuse that exact verified result here
     * instead of performing a second lookup that can lose attribution.
     */
    public function socialClickedFromTrackingIdentity(
        User $player,
        Website $website,
        string $platform,
        array $identity,
        Request $request,
    ): void {
        $platform = $this->normalizePlatform($platform);

        if (! in_array($platform, ['instagram', 'youtube', 'x'], true)) {
            return;
        }

        $type = $platform === 'youtube' ? 'highlight_click' : 'social_click';
        $matchSource = strtolower(trim((string) ($identity['match_source'] ?? '')));
        $verifiedSources = [
            'coach_database_cache',
            'coach_database_cache_snapshot',
            'coach_database_email_messages',
            'coach_database_schools',
            'coach_database_schools.head_coach_email',
        ];

        if (in_array($matchSource, $verifiedSources, true)) {
            $coach = [
                'matched' => true,
                'contact_id' => $identity['coach_contact_id'] ?? null,
                'email' => $identity['coach_email'] ?? null,
                'name' => $identity['coach_name'] ?? null,
                'school' => $identity['school_name'] ?? null,
                'source' => $matchSource,
            ];

            $this->sendResolvedCoach($player, $website, $type, $platform, $coach);
            return;
        }

        // Backwards compatibility for older/non-generator social URLs: let the
        // normal resolver inherit a verified recent coach profile view if one exists.
        $this->send($player, $website, $type, $platform, $request);
    }

    protected function send(User $player, Website $website, string $type, string $platform, Request $request): void
    {
        $recipient = $player->email ?: $player->personal_email;

        if (! $recipient || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        // Never notify the player about their own authenticated activity.
        if (auth()->check() && (int) auth()->id() === (int) $player->getKey()) {
            return;
        }

        // Anonymous/direct visitors remain trackable by the analytics layer,
        // but they must never trigger an athlete email. Only a visitor matched
        // to an actual coach is allowed to generate a notification.
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

        $this->sendResolvedCoach($player, $website, $type, $platform, $coach);
    }

    protected function sendResolvedCoach(
        User $player,
        Website $website,
        string $type,
        string $platform,
        array $coach,
    ): void {
        /*
         * There is intentionally NO cache/throttle/deduplication here. Every
         * verified coach interaction is a separate notification.
         */
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
            Log::warning('PLYRCARD coach activity email failed through native PHP mail().', [
                'user_id' => $player->id,
                'activity_type' => $type,
                'platform' => $platform,
                'coach_contact_id' => $coach['contact_id'] ?? null,
                'coach_match_source' => $coach['source'] ?? null,
                'error' => $result['error'] ?? 'Unknown email error.',
            ]);
            return;
        }

        Log::info('PLYRCARD coach activity notification sent.', [
            'user_id' => $player->id,
            'activity_type' => $type,
            'platform' => $platform,
            'coach_contact_id' => $coach['contact_id'] ?? null,
            'coach_email' => $coach['email'] ?? null,
            'coach_name' => $coach['name'] ?? null,
            'coach_school' => $coach['school'] ?? null,
            'coach_match_source' => $coach['source'] ?? null,
        ]);
    }

    protected function normalizePlatform(string $platform): string
    {
        $platform = strtolower(trim($platform));

        return match ($platform) {
            'ig', 'instagram' => 'instagram',
            'yt', 'youtube' => 'youtube',
            'twitter', 'x', 'x.com' => 'x',
            default => $platform,
        };
    }
}