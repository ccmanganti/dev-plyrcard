<?php

namespace App\Http\Controllers;

use App\Models\CoachDatabaseEmailMessage;
use App\Models\CoachDatabaseSchool;
use App\Models\User;
use App\Models\Website;
use App\Services\LocalRecruitingTrackingService;
use App\Services\PlayerActivityEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExternalSocialTrackingController extends Controller
{
    public function __construct(
        protected LocalRecruitingTrackingService $tracking,
        protected PlayerActivityEmailService $activityEmails,
    ) {
    }

    /**
     * Platform-hosted player URL:
     * /Sample/out/instagram?...coach tracking query...
     */
    public function platform(Request $request, string $slug, string $platform): RedirectResponse
    {
        $lookup = strtolower(trim(urldecode($slug)));
        $requestedWebsiteId = $this->requestedWebsiteId($request);

        $website = $requestedWebsiteId
            ? Website::query()
                ->whereKey($requestedWebsiteId)
                ->where('is_active', true)
                ->where(function ($query) use ($lookup): void {
                    $query->whereRaw('LOWER(slug) = ?', [$lookup])
                        ->orWhereRaw('LOWER(name) = ?', [$lookup]);
                })
                ->with('user')
                ->first()
            : null;

        if (! $website) {
            $website = Website::query()
                ->where('is_active', true)
                ->where(function ($query) use ($lookup): void {
                    $query->whereRaw('LOWER(slug) = ?', [$lookup])
                        ->orWhereRaw('LOWER(name) = ?', [$lookup]);
                })
                ->with('user')
                ->latest('updated_at')
                ->first();
        }

        return $this->trackAndRedirect($request, $website, $platform);
    }

    /**
     * Custom-domain player URL:
     * /out/instagram?...coach tracking query...
     */
    public function customDomain(Request $request, string $platform): RedirectResponse
    {
        $host = $this->normalizeHost($request->getHost());
        $requestedWebsiteId = $this->requestedWebsiteId($request);

        $website = $requestedWebsiteId
            ? Website::query()
                ->whereKey($requestedWebsiteId)
                ->where('is_active', true)
                ->with('user')
                ->first()
            : null;

        if ($website && ! $this->websiteMatchesHost($website, $host)) {
            $website = null;
        }

        if (! $website) {
            $website = Website::query()
                ->where('is_active', true)
                ->with('user')
                ->latest('updated_at')
                ->get()
                ->first(fn (Website $candidate): bool => $this->websiteMatchesHost($candidate, $host));
        }

        return $this->trackAndRedirect($request, $website, $platform);
    }

    protected function trackAndRedirect(
        Request $request,
        ?Website $website,
        string $platform,
    ): RedirectResponse {
        $platform = $this->normalizePlatform($platform);

        if (! $website || ! $website->user) {
            throw new NotFoundHttpException('Player website was not found.');
        }

        $player = $website->user;
        $destination = $this->socialDestination($player, $platform);

        if ($destination === '') {
            throw new NotFoundHttpException(
                match ($platform) {
                    'instagram' => 'Instagram is not configured for this player.',
                    'youtube' => 'YouTube is not configured for this player.',
                    'x' => 'X is not configured for this player.',
                }
            );
        }

        $identity = $this->resolveCoachIdentity($player, $request);

        Log::info('PLYRCARD social tracking URL received.', [
            'user_id' => $player->getKey(),
            'website_id' => $website->getKey(),
            'platform' => $platform,
            'has_rc_contact_id' => $this->mergeValue($request->query('rc_contact_id')) !== '',
            'has_rc_email' => $this->emailValue($request->query('rc_email')) !== '',
            'rc_notify' => $request->boolean('rc_notify'),
            'coach_match_source' => $identity['match_source'] ?? null,
            'coach_contact_id' => $identity['coach_contact_id'] ?? null,
            'coach_email' => $identity['coach_email'] ?? null,
            'coach_name' => $identity['coach_name'] ?? null,
            'school_name' => $identity['school_name'] ?? null,
        ]);

        try {
            $this->tracking->record([
                'athlete_id' => $player->getKey(),
                'athlete_user_id' => $player->getKey(),
                'athlete_email' => $player->email ?: $player->personal_email,
                'athlete_ghl_contact_id' => $player->ghl_contact_id,
                'athlete_ghl_location_id' => $player->ghl_location_id,
                'ghl_location_id' => $player->ghl_location_id,

                'coach_contact_id' => $identity['coach_contact_id'],
                'contact_id' => $identity['coach_contact_id'],
                'coach_email' => $identity['coach_email'],
                'coach_name' => $identity['coach_name'],
                'coach_title' => $identity['coach_title'],
                'school_business_id' => $identity['school_business_id'],
                'school_name' => $identity['school_name'],
                'school_logo_url' => $identity['school_logo_url'],

                'event_type' => 'link_click',
                'platform' => $platform,
                'source' => $this->trackingSource($request),
                'destination_url' => $destination,
                'website_id' => $website->getKey(),
                'website_name' => $website->name,

                'message_uuid' => $this->externalMessageUuid(
                    $request,
                    $player->getKey(),
                    $platform,
                    $identity['coach_contact_id'],
                    $identity['coach_email'],
                ),

                'metadata' => [
                    'tracking_mode' => 'social_middleman_redirect',
                    'coach_name' => $identity['coach_name'],
                    'coach_email' => $identity['coach_email'],
                    'coach_title' => $identity['coach_title'],
                    'school_name' => $identity['school_name'],
                    'school_logo_url' => $identity['school_logo_url'],
                    'match_source' => $identity['match_source'],
                    'utm_source' => $request->query('utm_source'),
                    'utm_medium' => $request->query('utm_medium'),
                    'utm_campaign' => $request->query('utm_campaign'),
                    'rc_destination' => $platform,
                ],
            ], $request, 'link_click');
        } catch (\Throwable $exception) {
            report($exception);
        }

        /*
         * v10.18: send the notification from the social redirect itself.
         * This is deliberately not deferred to route middleware. At this point
         * we still have the exact Website, player and platform that were clicked.
         * CoachViewerIdentityService can also inherit a verified coach from the
         * recent profile view if the social URL no longer contains rc_* params.
         */
        try {
            // Defense-in-depth: if an older cached route still has the activity
            // middleware attached, tell it this click was already handled here.
            $request->attributes->set('plyrcard_activity_email_sent', true);

            $this->activityEmails->socialClickedFromTrackingIdentity(
                player: $player,
                website: $website,
                platform: $platform,
                identity: $identity,
                request: $request,
            );
        } catch (\Throwable $exception) {
            report($exception);
        }

        return redirect()->away($destination, 302);
    }

    protected function socialDestination(User $player, string $platform): string
    {
        $value = match ($platform) {
            'instagram' => (string) $player->ig_handle,
            'youtube' => (string) $player->yt_url,
            'x' => (string) $player->x_handle,
        };

        return $this->normalizeSocialUrl($platform, $value);
    }

    protected function normalizeSocialUrl(string $platform, string $value): string
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($value === '' || $value === '#') {
            return '';
        }

        if (Str::startsWith(strtolower($value), ['http://', 'https://'])) {
            return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
        }

        $lower = strtolower(ltrim($value, '/'));
        $looksLikePlatformUrl = match ($platform) {
            'instagram' => Str::startsWith($lower, ['instagram.com/', 'www.instagram.com/']),
            'youtube' => Str::startsWith($lower, ['youtube.com/', 'www.youtube.com/', 'youtu.be/']),
            'x' => Str::startsWith($lower, ['x.com/', 'www.x.com/', 'twitter.com/', 'www.twitter.com/']),
        };

        if ($looksLikePlatformUrl) {
            $candidate = 'https://' . ltrim($value, '/');

            return filter_var($candidate, FILTER_VALIDATE_URL) ? $candidate : '';
        }

        $handle = trim($value, "@/ \t\n\r\0\x0B");

        if ($handle === '') {
            return '';
        }

        return match ($platform) {
            'instagram' => 'https://www.instagram.com/' . rawurlencode($handle) . '/',
            'youtube' => 'https://www.youtube.com/@' . rawurlencode($handle),
            'x' => 'https://x.com/' . rawurlencode($handle),
        };
    }

    protected function resolveCoachIdentity(User $player, Request $request): array
    {
        $suppliedContactId = $this->mergeValue($request->query('rc_contact_id'));
        $email = $this->emailValue($request->query('rc_email'));
        $locationId = trim((string) ($player->ghl_location_id ?? ''));

        $default = [
            'coach_contact_id' => $suppliedContactId !== '' ? $suppliedContactId : null,
            'coach_email' => $email !== '' ? $email : null,
            'coach_name' => null,
            'coach_title' => null,
            'school_business_id' => null,
            'school_name' => null,
            'school_logo_url' => null,
            'match_source' => $suppliedContactId !== '' ? 'supplied_contact_id' : 'unmatched',
        ];

        $cacheKey = 'coach-database:v10:' . $player->getKey() . ':'
            . Str::slug($locationId !== '' ? $locationId : 'default');
        $snapshot = Cache::get($cacheKey, []);
        $coaches = is_array($snapshot)
            ? collect($snapshot['coaches'] ?? [])
                ->merge($snapshot['contacts'] ?? [])
                ->filter(fn ($row): bool => is_array($row))
                ->values()
                ->all()
            : [];

        $cachedCoach = collect($coaches)
            ->filter(fn ($row): bool => is_array($row))
            ->first(function (array $row) use ($suppliedContactId, $email): bool {
                $rowContactId = trim((string) ($row['id'] ?? $row['contact_id'] ?? $row['ghl_contact_id'] ?? ''));
                $rowEmail = strtolower(trim((string) ($row['email'] ?? '')));

                return ($suppliedContactId !== '' && hash_equals($suppliedContactId, $rowContactId))
                    || ($email !== '' && $rowEmail !== '' && hash_equals($email, $rowEmail));
            });

        if (is_array($cachedCoach)) {
            return [
                'coach_contact_id' => trim((string) ($cachedCoach['id'] ?? $cachedCoach['contact_id'] ?? $cachedCoach['ghl_contact_id'] ?? $suppliedContactId)) ?: null,
                'coach_email' => strtolower(trim((string) ($cachedCoach['email'] ?? $email))) ?: null,
                'coach_name' => trim((string) ($cachedCoach['name'] ?? collect([
                    $cachedCoach['first_name'] ?? null,
                    $cachedCoach['last_name'] ?? null,
                ])->filter()->implode(' '))) ?: null,
                'coach_title' => trim((string) ($cachedCoach['title'] ?? '')) ?: null,
                'school_business_id' => trim((string) ($cachedCoach['business_id'] ?? $cachedCoach['company_id'] ?? $cachedCoach['school_id'] ?? '')) ?: null,
                'school_name' => trim((string) ($cachedCoach['school'] ?? $cachedCoach['school_name'] ?? $cachedCoach['company_name'] ?? $cachedCoach['business_name'] ?? '')) ?: null,
                'school_logo_url' => trim((string) ($cachedCoach['school_logo_url'] ?? $cachedCoach['business_logo_url'] ?? $cachedCoach['logo_url'] ?? '')) ?: null,
                'match_source' => 'coach_database_cache',
            ];
        }

        if (Schema::hasTable('coach_database_email_messages')) {
            $message = CoachDatabaseEmailMessage::query()
                ->where('athlete_user_id', $player->getKey())
                ->when($locationId !== '', fn ($query) => $query->where('ghl_location_id', $locationId))
                ->where(function ($query) use ($suppliedContactId, $email): void {
                    if ($suppliedContactId !== '') {
                        $query->where('coach_contact_id', $suppliedContactId);
                    }

                    if ($email !== '') {
                        $method = $suppliedContactId !== '' ? 'orWhereRaw' : 'whereRaw';
                        $query->{$method}('LOWER(recipient_email) = ?', [$email]);
                    }
                })
                ->latest('id')
                ->first();

            if ($message) {
                $school = null;
                if ($message->school_business_id && Schema::hasTable('coach_database_schools')) {
                    $school = CoachDatabaseSchool::query()
                        ->where('user_id', $player->getKey())
                        ->when($locationId !== '', fn ($query) => $query->where('ghl_location_id', $locationId))
                        ->where('business_id', $message->school_business_id)
                        ->first();
                }

                return array_merge($default, [
                    'coach_contact_id' => $message->coach_contact_id ?: ($suppliedContactId ?: null),
                    'coach_email' => strtolower(trim((string) ($message->recipient_email ?: $email))) ?: null,
                    'coach_name' => trim((string) ($message->recipient_name ?: $school?->head_coach_name)) ?: null,
                    'coach_title' => trim((string) ($school?->head_coach_title ?? '')) ?: null,
                    'school_business_id' => $message->school_business_id ?: null,
                    'school_name' => trim((string) ($message->school_name ?? $school?->name ?? '')) ?: null,
                    'school_logo_url' => trim((string) ($school?->logo_url ?? '')) ?: null,
                    'match_source' => 'coach_database_email_messages',
                ]);
            }
        }

        if ($email !== '' && Schema::hasTable('coach_database_schools')) {
            $school = CoachDatabaseSchool::query()
                ->where('user_id', $player->getKey())
                ->when($locationId !== '', fn ($query) => $query->where('ghl_location_id', $locationId))
                ->whereRaw('LOWER(head_coach_email) = ?', [$email])
                ->first();

            if ($school) {
                return array_merge($default, [
                    'coach_contact_id' => $suppliedContactId ?: null,
                    'coach_email' => $email,
                    'coach_name' => trim((string) $school->head_coach_name) ?: null,
                    'coach_title' => trim((string) $school->head_coach_title) ?: null,
                    'school_business_id' => $school->business_id ?: null,
                    'school_name' => trim((string) ($school->name ?? $school->school_name ?? '')) ?: null,
                    'school_logo_url' => trim((string) ($school->logo_url ?? $school->school_logo_url ?? '')) ?: null,
                    'match_source' => 'coach_database_schools',
                ]);
            }
        }

        return $default;
    }

    protected function requestedWebsiteId(Request $request): ?int
    {
        $value = $this->mergeValue($request->query('rc_website_id'));

        if ($value === '' || ! ctype_digit($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    protected function websiteMatchesHost(Website $website, string $requestHost): bool
    {
        $websiteHost = $this->normalizeHost((string) $website->domain);

        return $websiteHost !== '' && hash_equals($websiteHost, $requestHost);
    }

    protected function normalizeHost(?string $value): string
    {
        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return '';
        }

        if (! Str::startsWith($value, ['http://', 'https://'])) {
            $value = 'https://' . ltrim($value, '/');
        }

        $host = strtolower(trim((string) parse_url($value, PHP_URL_HOST)));
        $host = preg_replace('/^www\./i', '', $host) ?: $host;

        return rtrim($host, '.');
    }

    protected function normalizePlatform(string $platform): string
    {
        return match (strtolower(trim($platform))) {
            'instagram', 'ig' => 'instagram',
            'youtube', 'yt' => 'youtube',
            'x', 'twitter' => 'x',
            default => throw new NotFoundHttpException('Unsupported social platform.'),
        };
    }

    protected function trackingSource(Request $request): string
    {
        $source = strtolower(trim((string) $request->query('utm_source', 'external')));

        return match ($source) {
            'ghl', 'gohighlevel', 'highlevel' => 'ghl_external_campaign',
            '' => 'external_social_link',
            default => Str::limit($source . '_external_campaign', 80, ''),
        };
    }

    protected function mergeValue(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '' || str_contains($value, '{{') || str_contains($value, '}}')) {
            return '';
        }

        return Str::limit($value, 191, '');
    }

    protected function emailValue(mixed $value): string
    {
        $value = strtolower($this->mergeValue($value));

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
    }

    protected function externalMessageUuid(
        Request $request,
        int|string $playerId,
        string $platform,
        ?string $coachContactId,
        ?string $coachEmail,
    ): string {
        $seed = implode('|', [
            'social-middleman',
            (string) $playerId,
            strtolower((string) $request->query('utm_source', 'external')),
            strtolower((string) $request->query('utm_campaign', 'external')),
            (string) $coachContactId,
            (string) $coachEmail,
            $platform,
        ]);

        $hex = substr(hash('sha256', $seed), 0, 32);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}