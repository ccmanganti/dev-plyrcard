<?php

namespace App\Services;

use App\Models\CoachDatabaseEmailMessage;
use App\Models\CoachDatabaseTrackingEvent;
use App\Models\User;
use App\Models\Website;
use Illuminate\Http\Request;
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
        $user = $website->user;
        if (! $user) {
            return null;
        }

        return $this->record([
            'athlete_id' => $user->getKey(),
            'athlete_email' => $user->email ?: $user->personal_email,
            'athlete_ghl_contact_id' => $user->ghl_contact_id,
            'athlete_ghl_location_id' => $user->ghl_location_id,
            'event_type' => 'profile_view',
            'platform' => 'website',
            'source' => 'direct_website_visit',
            'destination_url' => $request->fullUrlWithoutQuery(['rc_tracked', 'rc_source']),
            'website_id' => $website->getKey(),
            'website_name' => $website->name,
        ], $request, 'profile_view');
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

        $query = DB::table('coach_database_tracking_events')->where('athlete_user_id', $user->getKey());
        $eventCount = fn (string $event): int => (clone $query)->where('event_type', $event)->count();
        $clicks = (clone $query)->where('event_type', 'link_click');
        $profile = (clone $query)->where('event_type', 'profile_view');
        $totalProfileViews = (clone $profile)->count();
        $uniqueProfileViews = (clone $profile)->whereNotNull('visitor_hash')->distinct()->count('visitor_hash');
        $knownCoachProfileViews = (clone $profile)->whereNotNull('coach_contact_id')->count();

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
            'email_click_count' => (clone $clicks)->count(),
            'website_click_count' => (clone $clicks)->where('platform', 'website')->count(),
            'instagram_click_count' => (clone $clicks)->where('platform', 'instagram')->count(),
            'youtube_click_count' => (clone $clicks)->where('platform', 'youtube')->count(),
            'x_click_count' => (clone $clicks)->where('platform', 'x')->count(),
            'link_clicks' => (clone $clicks)->count(),
            'trigger_link_clicks' => (clone $clicks)->count(),
            'unique_contact_clicks' => (clone $clicks)->whereNotNull('coach_contact_id')->distinct()->count('coach_contact_id'),
            'unique_link_click_contacts' => (clone $clicks)->whereNotNull('coach_contact_id')->distinct()->count('coach_contact_id'),
            'unique_profile_view_contacts' => (clone $profile)->whereNotNull('coach_contact_id')->distinct()->count('coach_contact_id'),
            'profile_view_unique_contact_count' => (clone $profile)->whereNotNull('coach_contact_id')->distinct()->count('coach_contact_id'),
            'profile_view_unique_school_count' => (clone $profile)->whereNotNull('school_business_id')->distinct()->count('school_business_id'),
            'school_clicks_total' => (clone $clicks)->whereNotNull('school_business_id')->count(),
            'overall_school_clicks' => (clone $clicks)->whereNotNull('school_business_id')->count(),
            'schools_with_clicks' => (clone $clicks)->whereNotNull('school_business_id')->distinct()->count('school_business_id'),
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
            'website' => ['label' => 'Website', 'class' => 'is-blue', 'icon' => 'website.png'],
            'instagram' => ['label' => 'Instagram', 'class' => 'is-pink', 'icon' => 'instagram.png'],
            'youtube' => ['label' => 'YouTube', 'class' => 'is-red', 'icon' => 'youtube.png'],
            'x' => ['label' => 'X', 'class' => 'is-neutral', 'icon' => 'x.png'],
            'email' => ['label' => 'Email link', 'class' => 'is-coral', 'icon' => 'email.png'],
        ];

        return $this->attributedEventRows($user, 'link_click', $limit)
            ->groupBy(fn (array $row): string => $row['coach_identity'] . '|' . $row['platform'])
            ->map(function ($events) use ($platformConfig): array {
                $latest = $events->sortByDesc('occurred_at')->first();
                $count = $events->count();
                $platform = strtolower((string) ($latest['platform'] ?? 'website'));
                $platform = match ($platform) { 'twitter' => 'x', 'ig' => 'instagram', default => $platform };
                $config = $platformConfig[$platform] ?? ['label' => ucfirst($platform ?: 'Tracked link'), 'class' => 'is-blue', 'icon' => 'link.png'];
                $name = trim((string) ($latest['coach_name'] ?? '')) ?: 'Known coach contact';
                $school = trim((string) ($latest['school_name'] ?? ''));

                return [
                    'coach_id' => (string) $latest['coach_identity'],
                    'coach_contact_id' => $latest['coach_contact_id'],
                    'coach_email' => $latest['coach_email'],
                    'school_key' => $latest['school_business_id'] ?: ($school !== '' ? 'school:' . strtolower(trim($school)) : ''),
                    'school_id' => $latest['school_business_id'] ?: '',
                    'coach_name' => $name,
                    'school' => $school,
                    'title' => $name,
                    'copy' => collect([$name . ' clicked ' . $config['label'] . ' ' . number_format($count) . ' ' . \Illuminate\Support\Str::plural('time', $count), $school])
                        ->filter()->implode(' • '),
                    'platform' => $config['label'],
                    'platform_key' => $platform,
                    'platform_class' => $config['class'],
                    'platform_icon_file' => $config['icon'],
                    'clicks' => $count,
                    'url' => (string) ($latest['destination_url'] ?? ''),
                    'time' => $latest['occurred_at'],
                    'time_label' => $this->timeLabel($latest['occurred_at']),
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

                return [
                    'coach_identity' => $identity,
                    'coach_contact_id' => $contactId !== '' ? $contactId : null,
                    'coach_name' => trim((string) ($metadata['coach_name'] ?? '')),
                    'coach_email' => $coachEmail !== '' ? $coachEmail : null,
                    'school_name' => trim((string) ($metadata['school_name'] ?? '')),
                    'school_logo_url' => $metadata['school_logo_url'] ?? null,
                    'school_business_id' => trim((string) ($row->school_business_id ?? '')),
                    'platform' => strtolower(trim((string) ($row->platform ?? 'website'))) ?: 'website',
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