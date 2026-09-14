<?php

namespace App\Services;

use App\Models\CoachDatabaseEmailMessage;
use App\Models\CoachDatabaseSchool;
use App\Models\CoachDatabaseTrackingEvent;
use App\Models\User;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LocalRecruitingTrackingService
{
    public function record(array $payload, ?Request $request = null, ?string $fallbackEventType = null): ?CoachDatabaseTrackingEvent
    {
        if (! Schema::hasTable('coach_database_tracking_events')) {
            Log::error('Recruiting tracking event table is missing. Run the tracking migrations.', [
                'host' => $request?->getHost(),
                'event_type' => $payload['event_type'] ?? $fallbackEventType,
            ]);
            return null;
        }

        $payload = $this->hydratePayloadFromStoredEmail($payload);

        $user = $this->resolveAthlete($payload);
        if (! $user) {
            Log::error('Recruiting tracking athlete could not be resolved.', [
                'athlete_id' => $payload['athlete_id'] ?? null,
                'athlete_email' => $payload['athlete_email'] ?? null,
                'athlete_ghl_contact_id' => $payload['athlete_ghl_contact_id'] ?? null,
                'athlete_ghl_location_id' => $payload['athlete_ghl_location_id'] ?? null,
                'host' => $request?->getHost(),
                'payload_keys' => array_keys($payload),
            ]);
            return null;
        }

        $destination = $this->cleanUrl((string) ($payload['destination_url'] ?? ''));
        $eventType = strtolower(trim((string) ($payload['event_type'] ?? $fallbackEventType ?? 'link_click'))) ?: 'link_click';
        $platform = strtolower(trim((string) ($payload['platform'] ?? 'website'))) ?: 'website';
        $platform = match ($platform) {
            'ig' => 'instagram',
            'twitter' => 'x',
            'yt' => 'youtube',
            default => $platform,
        };
        $source = mb_substr(trim((string) ($payload['source'] ?? 'tracked_link')) ?: 'tracked_link', 0, 80);
        $coachContactId = $this->text($payload['coach_contact_id'] ?? $payload['contact_id'] ?? $payload['ghl_contact_id'] ?? $payload['recipient_key'] ?? null)
            ?: $this->text($payload['coach_email'] ?? $payload['contact_email'] ?? null);
        $ip = trim((string) ($request?->ip() ?? ''));
        $agent = mb_substr(trim((string) ($request?->userAgent() ?? '')), 0, 500);
        $now = now();

        $metadata = array_filter([
            'token_athlete_id' => $payload['athlete_id'] ?? null,
            'resolved_athlete_id' => $user->getKey(),
            'athlete_email' => $payload['athlete_email'] ?? $user->email ?? $user->personal_email ?? null,
            'coach_contact_id' => $coachContactId,
            'recipient_key' => $payload['recipient_key'] ?? null,
            'link_uuid' => $payload['link_uuid'] ?? null,
            'message_uuid' => $payload['message_uuid'] ?? null,
            'coach_name' => $payload['coach_name'] ?? null,
            'coach_email' => $payload['coach_email'] ?? null,
            'coach_title' => $payload['coach_title'] ?? null,
            'school_name' => $payload['school_name'] ?? $payload['school'] ?? null,
            'school_logo_url' => $payload['school_logo_url'] ?? $payload['business_logo_url'] ?? null,
            'business_logo_url' => $payload['business_logo_url'] ?? $payload['school_logo_url'] ?? null,
            'identity_type' => $payload['identity_type'] ?? null,
            'coach_match_source' => $payload['coach_match_source'] ?? null,
            'recipient_email' => $payload['recipient_email'] ?? $payload['coach_email'] ?? null,
            'email_subject' => $payload['email_subject'] ?? null,
            'website_id' => $payload['website_id'] ?? null,
            'website_name' => $payload['website_name'] ?? null,
            'request_host' => $request?->getHost(),
            'token_tracking_host' => $payload['tracking_host'] ?? null,
            'tracking_signature_valid' => $payload['_tracking_signature_valid'] ?? null,
            'tracking_signature_error' => $payload['_tracking_signature_error'] ?? null,
        ], static fn ($value): bool => $value !== null && $value !== '');

        $visitorHash = $this->visitorHash(
            user: $user,
            request: $request,
            eventType: $eventType,
            platform: $platform,
            destination: $destination,
            coachContactId: $coachContactId,
        );

        $row = [
            'athlete_user_id' => $user->getKey(),
            'ghl_location_id' => $this->text($payload['athlete_ghl_location_id'] ?? $payload['ghl_location_id'] ?? $user->ghl_location_id ?? '') ?? '',
            'coach_contact_id' => $coachContactId,
            'school_business_id' => $this->text($payload['school_business_id'] ?? $payload['business_id'] ?? $payload['ghl_business_id'] ?? $payload['company_id'] ?? null),
            'campaign_uuid' => $this->uuid($payload['campaign_uuid'] ?? $payload['campaign_id'] ?? null),
            'message_uuid' => $this->uuid($payload['message_uuid'] ?? $payload['message_id'] ?? null),
            'template_id' => $this->text($payload['template_id'] ?? $payload['template_uuid'] ?? null),
            'event_type' => $eventType,
            'platform' => $platform,
            'source' => $source,
            'destination_url' => $destination !== '' ? $destination : null,
            'visitor_hash' => $visitorHash,
            'ip_hash' => $ip !== '' ? hash_hmac('sha256', $ip, (string) config('app.key')) : null,
            'user_agent' => $agent !== '' ? $agent : null,
            'referer' => $request?->headers->get('referer'),
            'metadata' => empty($metadata) ? null : json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        try {
            // Every visit is an event row. Uniqueness is calculated only in dashboard queries.
            $id = DB::table('coach_database_tracking_events')->insertGetId($row);
            $event = CoachDatabaseTrackingEvent::query()->find($id);

            Log::info('Recruiting tracking event persisted locally.', [
                'id' => $id,
                'athlete_user_id' => $user->getKey(),
                'event_type' => $eventType,
                'platform' => $platform,
                'source' => $source,
                'coach_contact_id' => $coachContactId,
                'request_host' => $request?->getHost(),
                'destination_url' => $destination,
            ]);

            return $event;
        } catch (\Throwable $exception) {
            Log::error('Recruiting tracking event insert failed.', [
                'athlete_user_id' => $user->getKey(),
                'event_type' => $eventType,
                'platform' => $platform,
                'source' => $source,
                'request_host' => $request?->getHost(),
                'destination_url' => $destination,
                'error' => $exception->getMessage(),
                'row_columns' => array_keys($row),
            ]);
            return null;
        }
    }

    public function recordDirectProfileVisit(Website $website, Request $request): ?CoachDatabaseTrackingEvent
    {
        // Compatibility guard only. The recommended setup removes the old external
        // middleware and lets this existing profile-view path record exactly one row.
        if ($request->attributes->get('external_tracking_recorded') === true) {
            return null;
        }

        $user = $website->user;
        if (! $user) {
            return null;
        }

        // GHL places the recipient email/contact ID in the public profile URL.
        // Resolve that recipient against our existing local cache/database before
        // inserting the same profile_view event used by the rest of the dashboard.
        $identity = $this->resolveProfileViewerIdentity($user, $request);
        $isTrackedRecipient = filled($request->query('rc_email'))
            || filled($request->query('rc_contact_id'));

        return $this->record(array_filter([
            'athlete_id' => $user->getKey(),
            'athlete_email' => $user->email ?: $user->personal_email,
            'athlete_ghl_contact_id' => $user->ghl_contact_id,
            'athlete_ghl_location_id' => $user->ghl_location_id,
            'event_type' => 'profile_view',
            'platform' => 'website',
            'source' => $isTrackedRecipient ? 'ghl_email_profile_view' : 'direct_website_visit',
            'destination_url' => $request->fullUrlWithoutQuery(['rc_email', 'rc_contact_id', 'rc_external']),
            'website_id' => $website->getKey(),
            'website_name' => $website->name,
            'coach_contact_id' => $identity['coach_contact_id'],
            'school_business_id' => $identity['school_business_id'],
            'coach_name' => $identity['coach_name'],
            'coach_email' => $identity['coach_email'],
            'coach_title' => $identity['coach_title'],
            'school_name' => $identity['school_name'],
            'school_logo_url' => $identity['school_logo_url'],
            'business_logo_url' => $identity['school_logo_url'],
            'identity_type' => $identity['identity_type'],
            'coach_match_source' => $identity['match_source'],
            'recipient_email' => $identity['coach_email'],
        ], static fn ($value): bool => $value !== null && $value !== ''), $request, 'profile_view');
    }

    /**
     * Resolve the viewer from the email/contact ID embedded in a GHL campaign URL.
     * A match becomes a coach-attributed profile view. No match stays anonymous and
     * therefore contributes only to total/unique profile views.
     */
    protected function resolveProfileViewerIdentity(User $user, Request $request): array
    {
        $email = $this->normalizedEmail($request->query('rc_email'));
        $suppliedContactId = $this->normalizedMergeValue($request->query('rc_contact_id'));
        $locationId = trim((string) ($user->ghl_location_id ?? ''));

        // Fastest and richest source: the same cached coach rows used by the UI.
        $locationSlug = Str::slug($locationId !== '' ? $locationId : 'default');
        $snapshot = Cache::get('coach-database:v10:' . $user->getKey() . ':' . $locationSlug, []);
        $cachedCoach = collect(is_array($snapshot) ? ($snapshot['coaches'] ?? []) : [])
            ->filter(fn ($row): bool => is_array($row))
            ->first(function (array $coach) use ($email, $suppliedContactId): bool {
                $coachEmail = $this->normalizedEmail(
                    $coach['email'] ?? $coach['contact_email'] ?? null
                );

                if ($email !== '' && $coachEmail === $email) {
                    return true;
                }

                if ($suppliedContactId === '') {
                    return false;
                }

                return collect([
                    $coach['id'] ?? null,
                    $coach['contact_id'] ?? null,
                    $coach['ghl_contact_id'] ?? null,
                ])->map(fn ($value): string => trim((string) $value))
                    ->contains($suppliedContactId);
            });

        if (is_array($cachedCoach)) {
            return $this->profileViewerIdentityFromCoachRow(
                $cachedCoach,
                $email,
                $suppliedContactId,
                'coach_database_cache_snapshot'
            );
        }

        // App-sent recipients are persisted here, so the same lookup also supports
        // links generated by the built-in email composer.
        if (Schema::hasTable('coach_database_email_messages')) {
            $message = CoachDatabaseEmailMessage::query()
                ->where('athlete_user_id', $user->getKey())
                ->when($locationId !== '', fn ($query) => $query->where('ghl_location_id', $locationId))
                ->where(function ($query) use ($email, $suppliedContactId): void {
                    if ($email !== '') {
                        $query->whereRaw('LOWER(recipient_email) = ?', [$email]);
                        if ($suppliedContactId !== '') {
                            $query->orWhere('coach_contact_id', $suppliedContactId);
                        }
                    } elseif ($suppliedContactId !== '') {
                        $query->where('coach_contact_id', $suppliedContactId);
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                })
                ->latest('id')
                ->first();

            if ($message) {
                return [
                    'identity_type' => 'known_coach',
                    'coach_contact_id' => $this->text($message->coach_contact_id) ?: ($suppliedContactId ?: $email),
                    'school_business_id' => $this->text($message->school_business_id),
                    'coach_name' => $this->text($message->recipient_name) ?: 'Known coach contact',
                    'coach_email' => $this->normalizedEmail($message->recipient_email) ?: ($email ?: null),
                    'coach_title' => null,
                    'school_name' => $this->text($message->school_name),
                    'school_logo_url' => null,
                    'match_source' => 'coach_database_email_messages',
                ];
            }
        }

        // The local school table reliably supports the normalized head coach.
        if ($email !== '' && Schema::hasTable('coach_database_schools')) {
            $school = CoachDatabaseSchool::query()
                ->where('user_id', $user->getKey())
                ->when($locationId !== '', fn ($query) => $query->where('ghl_location_id', $locationId))
                ->whereRaw('LOWER(head_coach_email) = ?', [$email])
                ->first();

            if ($school) {
                return [
                    'identity_type' => 'known_coach',
                    'coach_contact_id' => $suppliedContactId ?: $email,
                    'school_business_id' => $this->text($school->business_id),
                    'coach_name' => $this->text($school->head_coach_name) ?: 'Known coach contact',
                    'coach_email' => $email,
                    'coach_title' => $this->text($school->head_coach_title),
                    'school_name' => $this->text($school->name),
                    'school_logo_url' => $this->text($school->logo_url),
                    'match_source' => 'coach_database_schools.head_coach_email',
                ];
            }
        }

        return [
            'identity_type' => $email !== '' ? 'unmatched_email' : 'anonymous',
            'coach_contact_id' => null,
            'school_business_id' => null,
            'coach_name' => null,
            'coach_email' => $email ?: null,
            'coach_title' => null,
            'school_name' => null,
            'school_logo_url' => null,
            'match_source' => null,
        ];
    }

    protected function profileViewerIdentityFromCoachRow(
        array $coach,
        string $email,
        string $suppliedContactId,
        string $source
    ): array {
        $contactId = $this->text(
            $coach['id'] ?? $coach['contact_id'] ?? $coach['ghl_contact_id'] ?? null
        ) ?: ($suppliedContactId ?: null);
        $coachEmail = $this->normalizedEmail(
            $coach['email'] ?? $coach['contact_email'] ?? null
        ) ?: ($email ?: null);
        $firstName = trim((string) ($coach['first_name'] ?? $coach['firstName'] ?? ''));
        $lastName = trim((string) ($coach['last_name'] ?? $coach['lastName'] ?? ''));
        $name = $this->text(
            $coach['name'] ?? $coach['full_name'] ?? trim($firstName . ' ' . $lastName)
        ) ?: 'Known coach contact';

        return [
            'identity_type' => 'known_coach',
            'coach_contact_id' => $contactId ?: $coachEmail,
            'school_business_id' => $this->text(
                $coach['business_id'] ?? $coach['company_id'] ?? $coach['school_id'] ?? null
            ),
            'coach_name' => $name,
            'coach_email' => $coachEmail,
            'coach_title' => $this->text($coach['title'] ?? $coach['job_title'] ?? null),
            'school_name' => $this->text(
                $coach['school'] ?? $coach['school_name'] ?? $coach['company_name'] ?? $coach['business_name'] ?? null
            ),
            'school_logo_url' => $this->text(
                $coach['school_logo_url'] ?? $coach['business_logo_url'] ?? $coach['logo_url'] ?? null
            ),
            'match_source' => $source,
        ];
    }

    protected function normalizedMergeValue(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '' || str_contains($value, '{{') || str_contains($value, '}}')) {
            return '';
        }

        return Str::limit($value, 191, '');
    }

    protected function normalizedEmail(mixed $value): string
    {
        $email = strtolower($this->normalizedMergeValue($value));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    public function storePendingEmailRecipient(User $user, array $context, string $subject, string $html): string
    {
        $messageUuid = $this->uuid($context['message_uuid'] ?? null) ?: (string) Str::uuid();

        if (Schema::hasTable('coach_database_email_messages')) {
            CoachDatabaseEmailMessage::query()->updateOrCreate(
                ['message_uuid' => $messageUuid],
                [
                    'athlete_user_id' => $user->getKey(),
                    'ghl_location_id' => trim((string) ($user->ghl_location_id ?? config('ghl.location_id') ?? '')),
                    'campaign_uuid' => $this->uuid($context['campaign_uuid'] ?? null),
                    'coach_contact_id' => $this->text($context['coach_contact_id'] ?? $context['contact_id'] ?? null),
                    'school_business_id' => $this->text($context['school_business_id'] ?? $context['business_id'] ?? null),
                    'template_id' => $this->text($context['template_id'] ?? null),
                    'recipient_email' => $this->text($context['coach_email'] ?? $context['to'] ?? null),
                    'recipient_name' => $this->text($context['coach_name'] ?? null),
                    'school_name' => $this->text($context['school_name'] ?? $context['school'] ?? null),
                    'subject' => $subject,
                    'rendered_html' => $html,
                    'sent_at' => null,
                ]
            );
        }

        return $messageUuid;
    }

    public function markEmailSent(string $messageUuid, array $sendResult = [], ?string $renderedHtml = null): void
    {
        if (! Schema::hasTable('coach_database_email_messages')) {
            return;
        }

        CoachDatabaseEmailMessage::query()
            ->where('message_uuid', $messageUuid)
            ->update(array_filter([
                'ghl_message_id' => $this->text($sendResult['message_id'] ?? $sendResult['id'] ?? null),
                'rendered_html' => $renderedHtml,
                'sent_at' => now(),
                'updated_at' => now(),
            ], static fn ($value): bool => $value !== null));
    }

    protected function hydratePayloadFromStoredEmail(array $payload): array
    {
        $messageUuid = $this->uuid($payload['message_uuid'] ?? $payload['message_id'] ?? null);
        if (! $messageUuid || ! Schema::hasTable('coach_database_email_messages')) {
            return $payload;
        }

        $message = CoachDatabaseEmailMessage::query()
            ->where('message_uuid', $messageUuid)
            ->first();

        if (! $message) {
            return $payload;
        }

        return array_merge([
            'athlete_id' => $message->athlete_user_id,
            'athlete_ghl_location_id' => $message->ghl_location_id,
            'coach_contact_id' => $message->coach_contact_id,
            'contact_id' => $message->coach_contact_id,
            'ghl_contact_id' => $message->coach_contact_id,
            'coach_email' => $message->recipient_email,
            'coach_name' => $message->recipient_name,
            'school_business_id' => $message->school_business_id,
            'business_id' => $message->school_business_id,
            'school_name' => $message->school_name,
            'campaign_uuid' => $message->campaign_uuid,
            'message_uuid' => $message->message_uuid,
            'template_id' => $message->template_id,
            'email_subject' => $message->subject,
            'source' => 'compose_email',
        ], array_filter($payload, static fn ($value): bool => $value !== null && $value !== ''));
    }

    public function recordSentEmail(User $user, array $context, string $subject, string $html, array $sendResult = []): string
    {
        $messageUuid = $this->uuid($context['message_uuid'] ?? null) ?: (string) Str::uuid();

        if (Schema::hasTable('coach_database_email_messages')) {
            CoachDatabaseEmailMessage::query()->updateOrCreate(
                ['message_uuid' => $messageUuid],
                [
                    'athlete_user_id' => $user->getKey(),
                    'ghl_location_id' => trim((string) ($user->ghl_location_id ?? config('ghl.location_id') ?? '')),
                    'campaign_uuid' => $this->uuid($context['campaign_uuid'] ?? null),
                    'ghl_message_id' => $this->text($sendResult['message_id'] ?? $sendResult['id'] ?? null),
                    'coach_contact_id' => $this->text($context['contact_id'] ?? $context['coach_contact_id'] ?? null),
                    'school_business_id' => $this->text($context['business_id'] ?? $context['school_business_id'] ?? null),
                    'template_id' => $this->text($context['template_id'] ?? null),
                    'recipient_email' => $this->text($context['coach_email'] ?? $context['to'] ?? null),
                    'subject' => $subject,
                    'rendered_html' => $html,
                    'sent_at' => now(),
                ]
            );
        }

        $this->record(array_merge($context, [
            'athlete_id' => $user->getKey(),
            'athlete_email' => $user->email ?: $user->personal_email,
            'athlete_ghl_contact_id' => $user->ghl_contact_id,
            'athlete_ghl_location_id' => $user->ghl_location_id,
            'message_uuid' => $messageUuid,
            'event_type' => 'email_sent',
            'platform' => 'email',
            'destination_url' => null,
        ]), null, 'email_sent');

        return $messageUuid;
    }

    public function dashboardStats(User $user): array
    {
        if (! Schema::hasTable('coach_database_tracking_events')) {
            return [];
        }

        $query = DB::table('coach_database_tracking_events')
            ->where('athlete_user_id', $user->getKey());

        $eventCount = fn (string $event): int =>
            (clone $query)->where('event_type', $event)->count();

        $profile = (clone $query)->where('event_type', 'profile_view');

        // Coach Engagement excludes profile views.
        // It only counts social clicks from tracked emails.
        $emailClicks = (clone $query)
            ->where('event_type', 'link_click')
            ->whereNotNull('message_uuid')
            ->where('message_uuid', '<>', '');

        $socialClicks = (clone $emailClicks)
            ->whereIn('platform', ['instagram', 'youtube', 'x']);

        $totalProfileViews = (clone $profile)->count();

        $uniqueProfileViews = (clone $profile)
            ->whereNotNull('visitor_hash')
            ->distinct()
            ->count('visitor_hash');

        $knownCoachProfileViews = (clone $profile)
            ->whereNotNull('coach_contact_id')
            ->count();

        return [
            'profile_views' => $totalProfileViews,
            'view_profile_total' => $totalProfileViews,
            'unique_profile_views' => $uniqueProfileViews,
            'unique_profile_view_count' => $uniqueProfileViews,
            'known_coach_profile_views' => $knownCoachProfileViews,

            'view_profile_website' => (clone $profile)->where('platform', 'website')->count(),
            'view_profile_instagram' => (clone $profile)->where('platform', 'instagram')->count(),
            'view_profile_youtube' => (clone $profile)->where('platform', 'youtube')->count(),
            'view_profile_x' => (clone $profile)->where('platform', 'x')->count(),

            'email_sent_count' => $eventCount('email_sent'),
            'emails_sent' => $eventCount('email_sent'),
            'email_open_count' => $eventCount('email_open'),
            'email_opens' => $eventCount('email_open'),

            // All email-link clicks, including profile links.
            'email_click_count' => (clone $emailClicks)->count(),
            'email_clicks' => (clone $emailClicks)->count(),

            // Coach Engagement social-only counts.
            'website_click_count' => 0,
            'website_clicks' => 0,

            'instagram_click_count' => (clone $socialClicks)->where('platform', 'instagram')->count(),
            'instagram_clicks' => (clone $socialClicks)->where('platform', 'instagram')->count(),

            'youtube_click_count' => (clone $socialClicks)->where('platform', 'youtube')->count(),
            'youtube_clicks' => (clone $socialClicks)->where('platform', 'youtube')->count(),

            'x_click_count' => (clone $socialClicks)->where('platform', 'x')->count(),
            'x_clicks' => (clone $socialClicks)->where('platform', 'x')->count(),
            'twitter_clicks' => (clone $socialClicks)->where('platform', 'x')->count(),

            'social_clicks' => (clone $socialClicks)->count(),
            'link_clicks' => (clone $socialClicks)->count(),
            'trigger_link_clicks' => (clone $socialClicks)->count(),

            'unique_contact_clicks' => (clone $socialClicks)
                ->whereNotNull('coach_contact_id')
                ->distinct()
                ->count('coach_contact_id'),

            'unique_link_click_contacts' => (clone $socialClicks)
                ->whereNotNull('coach_contact_id')
                ->distinct()
                ->count('coach_contact_id'),

            'unique_profile_view_contacts' => (clone $profile)
                ->whereNotNull('coach_contact_id')
                ->distinct()
                ->count('coach_contact_id'),

            'profile_view_unique_contact_count' => (clone $profile)
                ->whereNotNull('coach_contact_id')
                ->distinct()
                ->count('coach_contact_id'),

            'profile_view_unique_school_count' => (clone $profile)
                ->whereNotNull('school_business_id')
                ->distinct()
                ->count('school_business_id'),

            'school_clicks_total' => (clone $socialClicks)
                ->whereNotNull('school_business_id')
                ->count(),

            'overall_school_clicks' => (clone $socialClicks)
                ->whereNotNull('school_business_id')
                ->count(),

            'schools_with_clicks' => (clone $socialClicks)
                ->whereNotNull('school_business_id')
                ->distinct()
                ->count('school_business_id'),
        ];
    }

    protected function visitorHash(User $user, ?Request $request, string $eventType, string $platform, string $destination, ?string $coachContactId): string
    {
        $ip = strtolower(trim((string) ($request?->ip() ?? 'unknown-ip')));
        $agent = strtolower(trim((string) ($request?->userAgent() ?? 'unknown-agent')));

        return hash('sha256', implode('|', [
            $ip,
            $agent,
            (string) $user->getKey(),
            $eventType,
            $platform,
            $destination,
            $coachContactId ?: 'direct',
        ]));
    }



    /**
     * Build coach-attributed profile-view rows directly from the local event table.
     * Totals and coach lists must use the same source of truth.
     */
    public function profileViewRows(User $user, int $limit = 500): array
    {
        return $this->attributedEventRows($user, 'profile_view', $limit)
            ->groupBy('coach_identity')
            ->map(function ($events): array {
                $latest = $events->sortByDesc('occurred_at')->first();
                $views = $events->count();
                $name = trim((string) ($latest['coach_name'] ?? '')) ?: 'Known coach contact';
                $school = trim((string) ($latest['school_name'] ?? ''));
                $initials = strtoupper(collect(preg_split('/\\s+/', $name) ?: [])
                    ->filter()->map(fn ($part) => mb_substr((string) $part, 0, 1))->take(2)->implode('') ?: 'PV');

                return [
                    'coach_id' => (string) $latest['coach_identity'],
                    'coach_contact_id' => $latest['coach_contact_id'],
                    'coach_email' => $latest['coach_email'],
                    'school_key' => $latest['school_business_id'] ?: ($school !== '' ? 'school:' . strtolower(trim($school)) : ''),
                    'school_id' => $latest['school_business_id'] ?: '',
                    'school' => $school,
                    'title' => $name,
                    'copy' => collect([$school, number_format($views) . ' tracked profile ' . \Illuminate\Support\Str::plural('view', $views)])
                        ->filter()->implode(' • '),
                    'views' => $views,
                    'type' => 'Website',
                    'logo' => $latest['school_logo_url'],
                    'initials' => $initials,
                    'time' => $latest['occurred_at'],
                    'time_label' => $this->timeLabel($latest['occurred_at']),
                ];
            })
            ->sortByDesc('views')
            ->values()
            ->map(fn (array $row, int $index): array => array_merge($row, ['rank' => $index + 1]))
            ->all();
    }

    /**
     * Build click rows from local events without requiring the coach to still be
     * present in the cached GHL coach collection.
     */
    public function coachEngagementRows(User $user, int $limit = 500): array
    {
        $platformConfig = [
            'instagram' => [
                'label' => 'Instagram',
                'class' => 'is-pink',
                'icon' => 'instagram.png',
            ],
            'youtube' => [
                'label' => 'YouTube',
                'class' => 'is-red',
                'icon' => 'youtube.png',
            ],
            'x' => [
                'label' => 'X',
                'class' => 'is-neutral',
                'icon' => 'x.png',
            ],
        ];

        return $this->attributedEventRows($user, 'link_click', $limit)
            ->filter(function (array $row): bool {
                return filled($row['message_uuid'] ?? null)
                    && in_array((string) ($row['platform'] ?? ''), ['instagram', 'youtube', 'x'], true);
            })
            ->groupBy(fn (array $row): string => $row['coach_identity'].'|'.$row['platform'])
            ->map(function ($events) use ($platformConfig): array {
                $latest = $events->sortByDesc('occurred_at')->first();

                $count = $events->count();

                $platform = strtolower((string) ($latest['platform'] ?? ''));
                $platform = match ($platform) {
                    'twitter' => 'x',
                    'ig' => 'instagram',
                    'yt' => 'youtube',
                    default => $platform,
                };

                $config = $platformConfig[$platform] ?? [
                    'label' => ucfirst($platform ?: 'Tracked link'),
                    'class' => 'is-blue',
                    'icon' => 'link.png',
                ];

                $name = trim((string) ($latest['coach_name'] ?? '')) ?: 'Known coach contact';
                $school = trim((string) ($latest['school_name'] ?? ''));

                return [
                    'coach_id' => (string) ($latest['coach_identity'] ?? ''),
                    'coach_contact_id' => $latest['coach_contact_id'] ?? null,
                    'coach_email' => $latest['coach_email'] ?? null,
                    'school_key' => ($latest['school_business_id'] ?? '') ?: ($school !== '' ? 'school:'.strtolower(trim($school)) : ''),
                    'school_id' => $latest['school_business_id'] ?? '',
                    'coach_name' => $name,
                    'school' => $school,
                    'title' => $name,
                    'copy' => collect([
                        $name.' clicked '.$config['label'].' '.number_format($count).' '.\Illuminate\Support\Str::plural('time', $count),
                        $school,
                    ])->filter()->implode(' • '),
                    'platform' => $config['label'],
                    'platform_key' => $platform,
                    'platform_class' => $config['class'],
                    'platform_icon_file' => $config['icon'],
                    'clicks' => $count,
                    'url' => (string) ($latest['destination_url'] ?? ''),
                    'time' => $latest['occurred_at'] ?? null,
                    'time_label' => $this->timeLabel($latest['occurred_at'] ?? null),
                    'message_uuid' => $latest['message_uuid'] ?? null,
                ];
            })
            ->sortByDesc('time')
            ->take(100)
            ->values()
            ->all();
    }

    public function dashboardCoachActivityRows(User $user, int $limit = 500): array
    {
        $profile = collect($this->profileViewRows($user, $limit))->map(fn (array $row): array => [
            'type' => 'profile_view',
            'title' => ($row['title'] ?? 'Coach contact') . ' viewed your profile',
            'copy' => $row['copy'] ?? 'Tracked profile view',
            'time' => $row['time'] ?? null,
            'coach_id' => $row['coach_id'] ?? null,
            'school_id' => $row['school_id'] ?? null,
            'url' => '#',
        ]);

        $clicks = collect($this->coachEngagementRows($user, $limit))->map(fn (array $row): array => [
            'type' => ($row['platform_key'] ?? '') === 'email' ? 'email_click' : 'social_click_' . ($row['platform_key'] ?? 'tracked'),
            'title' => ($row['coach_name'] ?? 'Coach contact') . ' clicked ' . ($row['platform'] ?? 'a tracked link'),
            'copy' => $row['copy'] ?? 'Tracked link click',
            'time' => $row['time'] ?? null,
            'coach_id' => $row['coach_id'] ?? null,
            'school_id' => $row['school_id'] ?? null,
            'platform_key' => $row['platform_key'] ?? null,
            'url' => $row['url'] ?? '#',
        ]);

        return $profile->merge($clicks)->sortByDesc('time')->take(50)->values()->all();
    }

    protected function attributedEventRows(User $user, string $eventType, int $limit)
    {
        if (! Schema::hasTable('coach_database_tracking_events')) {
            return collect();
        }

        return DB::table('coach_database_tracking_events')
            ->where('athlete_user_id', $user->getKey())
            ->where('event_type', $eventType)
            ->whereNotNull('coach_contact_id')
            ->where('coach_contact_id', '<>', '')
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get()
            ->map(function ($row): array {
                $metadata = [];

                if (is_string($row->metadata ?? null) && trim((string) $row->metadata) !== '') {
                    $decoded = json_decode((string) $row->metadata, true);
                    $metadata = is_array($decoded) ? $decoded : [];
                }

                $contactId = trim((string) ($row->coach_contact_id ?? ''));
                $coachEmail = strtolower(trim((string) ($metadata['coach_email'] ?? '')));
                $identity = $contactId !== '' ? $contactId : $coachEmail;

                $platform = strtolower(trim((string) ($row->platform ?? 'website'))) ?: 'website';
                $platform = match ($platform) {
                    'twitter' => 'x',
                    'ig' => 'instagram',
                    'yt' => 'youtube',
                    default => $platform,
                };

                return [
                    'event_id' => (int) ($row->id ?? 0),
                    'event_type' => trim((string) ($row->event_type ?? $eventType)),
                    'coach_identity' => $identity,
                    'coach_contact_id' => $contactId !== '' ? $contactId : null,
                    'coach_name' => trim((string) ($metadata['coach_name'] ?? '')),
                    'coach_email' => $coachEmail !== '' ? $coachEmail : null,
                    'school_name' => trim((string) ($metadata['school_name'] ?? $metadata['school'] ?? '')),
                    'school_logo_url' => $metadata['school_logo_url'] ?? $metadata['business_logo_url'] ?? null,
                    'school_business_id' => trim((string) ($row->school_business_id ?? '')),
                    'message_uuid' => trim((string) ($row->message_uuid ?? '')),
                    'platform' => $platform,
                    'destination_url' => $row->destination_url ?? null,
                    'source' => $row->source ?? null,
                    'occurred_at' => $row->occurred_at ?? $row->created_at ?? null,
                ];
            })
            ->filter(fn (array $row): bool => trim((string) ($row['coach_identity'] ?? '')) !== '')
            ->values();
    }

    protected function timeLabel(mixed $time): string
    {
        try {
            return $time ? \Illuminate\Support\Carbon::parse($time)->diffForHumans() : 'Recorded';
        } catch (\Throwable) {
            return 'Recorded';
        }
    }

    protected function resolveAthlete(array $payload): ?User
    {
        $athleteId = trim((string) ($payload['athlete_id'] ?? $payload['athlete_user_id'] ?? ''));
        if ($athleteId !== '' && ctype_digit($athleteId)) {
            if ($user = User::query()->find((int) $athleteId)) {
                return $user;
            }
        }

        $ghlContactId = trim((string) ($payload['athlete_ghl_contact_id'] ?? $payload['athlete_contact_id'] ?? ''));
        if ($ghlContactId !== '' && ($user = User::query()->where('ghl_contact_id', $ghlContactId)->first())) {
            return $user;
        }

        $email = strtolower(trim((string) ($payload['athlete_email'] ?? '')));
        if ($email !== '') {
            $user = User::query()->where(function ($query) use ($email): void {
                $query->whereRaw('LOWER(email) = ?', [$email])->orWhereRaw('LOWER(personal_email) = ?', [$email]);
            })->first();
            if ($user) {
                return $user;
            }
        }

        $locationId = trim((string) ($payload['athlete_ghl_location_id'] ?? $payload['ghl_location_id'] ?? ''));
        if ($locationId !== '') {
            $users = User::query()->where('ghl_location_id', $locationId)->limit(2)->get();
            if ($users->count() === 1) {
                return $users->first();
            }
        }

        return null;
    }

    protected function cleanUrl(string $url): string
    {
        $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = preg_replace('/[\x{00A0}\x{2007}\x{202F}\x{200B}\x{FEFF}]+/u', '', $url) ?? $url;
        return trim($url);
    }

    protected function text(mixed $value): ?string
    {
        if (! is_scalar($value) && ! $value instanceof \Stringable) {
            return null;
        }
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    protected function uuid(mixed $value): ?string
    {
        $value = $this->text($value);
        return $value && Str::isUuid($value) ? $value : null;
    }
}