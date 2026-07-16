<?php

namespace App\Services;

use App\Models\CoachDatabaseEmailMessage;
use App\Models\CoachDatabaseTrackingEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LocalRecruitingTrackingService
{
    public function record(array $payload, ?Request $request = null, ?string $fallbackEventType = null): ?CoachDatabaseTrackingEvent
    {
        if (! Schema::hasTable('coach_database_tracking_events')) {
            Log::warning('Recruiting tracking event table is missing.');
            return null;
        }

        $user = $this->resolveAthlete($payload);

        if (! $user) {
            Log::warning('Recruiting tracking athlete could not be resolved.', [
                'athlete_id' => $payload['athlete_id'] ?? null,
                'athlete_email' => $payload['athlete_email'] ?? null,
                'athlete_ghl_contact_id' => $payload['athlete_ghl_contact_id'] ?? null,
                'athlete_ghl_location_id' => $payload['athlete_ghl_location_id'] ?? null,
                'tracking_host' => $request?->getHost(),
            ]);

            return null;
        }

        $destination = $this->cleanUrl((string) ($payload['destination_url'] ?? ''));
        $eventType = trim((string) ($payload['event_type'] ?? $fallbackEventType ?? 'link_click')) ?: 'link_click';
        $platform = trim((string) ($payload['platform'] ?? 'website')) ?: 'website';
        $ip = $request?->ip();
        $agent = substr((string) $request?->userAgent(), 0, 500);
        $fingerprintSeed = implode('|', [
            strtolower((string) $ip),
            strtolower($agent),
            (string) $user->getKey(),
            $eventType,
            $destination,
        ]);

        try {
            return CoachDatabaseTrackingEvent::query()->create([
                'athlete_user_id' => $user->getKey(),
                'ghl_location_id' => $this->text(
                    $payload['athlete_ghl_location_id']
                    ?? $payload['ghl_location_id']
                    ?? $user->ghl_location_id
                    ?? null
                ),
                'coach_contact_id' => $this->text(
                    $payload['coach_contact_id']
                    ?? $payload['contact_id']
                    ?? $payload['ghl_contact_id']
                    ?? null
                ),
                'school_business_id' => $this->text(
                    $payload['school_business_id']
                    ?? $payload['business_id']
                    ?? $payload['ghl_business_id']
                    ?? $payload['company_id']
                    ?? null
                ),
                'campaign_uuid' => $this->uuid($payload['campaign_uuid'] ?? $payload['campaign_id'] ?? null),
                'message_uuid' => $this->uuid($payload['message_uuid'] ?? $payload['message_id'] ?? null),
                'template_id' => $this->text($payload['template_id'] ?? $payload['template_uuid'] ?? null),
                'event_type' => $eventType,
                'platform' => $platform,
                'source' => trim((string) ($payload['source'] ?? 'tracked_link')) ?: 'tracked_link',
                'destination_url' => $destination !== '' ? $destination : null,
                'visitor_hash' => hash('sha256', $fingerprintSeed),
                'ip_hash' => $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null,
                'user_agent' => $agent !== '' ? $agent : null,
                'referer' => $request?->headers->get('referer'),
                'metadata' => array_filter([
                    'token_athlete_id' => $payload['athlete_id'] ?? null,
                    'resolved_athlete_id' => $user->getKey(),
                    'athlete_email' => $payload['athlete_email'] ?? $user->email ?? null,
                    'coach_name' => $payload['coach_name'] ?? null,
                    'coach_email' => $payload['coach_email'] ?? null,
                    'school_name' => $payload['school_name'] ?? $payload['school'] ?? null,
                    'email_subject' => $payload['email_subject'] ?? null,
                    'tracking_host' => $request?->getHost(),
                    'token_tracking_host' => $payload['tracking_host'] ?? null,
                    'tracking_signature_valid' => $payload['_tracking_signature_valid'] ?? null,
                ], fn ($value): bool => $value !== null && $value !== ''),
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Recruiting tracking event insert failed.', [
                'athlete_user_id' => $user->getKey(),
                'event_type' => $eventType,
                'platform' => $platform,
                'destination_url' => $destination,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
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

        $query = CoachDatabaseTrackingEvent::query()
            ->where('athlete_user_id', $user->getKey());

        $count = fn (string $event, ?string $platform = null): int => (clone $query)
            ->where('event_type', $event)
            ->when($platform, fn ($builder) => $builder->where('platform', $platform))
            ->count();

        $clicks = (clone $query)->where('event_type', 'link_click');
        $profile = (clone $query)->where('event_type', 'profile_view');

        return [
            'profile_views' => (clone $profile)->count(),
            'view_profile_total' => (clone $profile)->count(),
            'view_profile_website' => (clone $profile)->where('platform', 'website')->count(),
            'view_profile_instagram' => (clone $profile)->where('platform', 'instagram')->count(),
            'view_profile_youtube' => (clone $profile)->where('platform', 'youtube')->count(),
            'view_profile_x' => (clone $profile)->where('platform', 'x')->count(),
            'email_sent_count' => $count('email_sent'),
            'emails_sent' => $count('email_sent'),
            'email_open_count' => $count('email_open'),
            'email_opens' => $count('email_open'),
            'email_click_count' => (clone $clicks)->count(),
            'website_click_count' => (clone $clicks)->where('platform', 'website')->count(),
            'instagram_click_count' => (clone $clicks)->where('platform', 'instagram')->count(),
            'youtube_click_count' => (clone $clicks)->where('platform', 'youtube')->count(),
            'x_click_count' => (clone $clicks)->where('platform', 'x')->count(),
            'link_clicks' => (clone $clicks)->count(),
            'trigger_link_clicks' => (clone $clicks)->count(),
            'unique_contact_clicks' => (clone $clicks)->whereNotNull('coach_contact_id')->distinct('coach_contact_id')->count('coach_contact_id'),
            'unique_link_click_contacts' => (clone $clicks)->whereNotNull('coach_contact_id')->distinct('coach_contact_id')->count('coach_contact_id'),
            'unique_profile_view_contacts' => (clone $profile)->whereNotNull('coach_contact_id')->distinct('coach_contact_id')->count('coach_contact_id'),
            'profile_view_unique_contact_count' => (clone $profile)->whereNotNull('coach_contact_id')->distinct('coach_contact_id')->count('coach_contact_id'),
            'profile_view_unique_school_count' => (clone $profile)->whereNotNull('school_business_id')->distinct('school_business_id')->count('school_business_id'),
            'school_clicks_total' => (clone $clicks)->whereNotNull('school_business_id')->count(),
            'overall_school_clicks' => (clone $clicks)->whereNotNull('school_business_id')->count(),
            'schools_with_clicks' => (clone $clicks)->whereNotNull('school_business_id')->distinct('school_business_id')->count('school_business_id'),
        ];
    }

    protected function resolveAthlete(array $payload): ?User
    {
        $athleteId = trim((string) ($payload['athlete_id'] ?? $payload['athlete_user_id'] ?? ''));

        if ($athleteId !== '' && ctype_digit($athleteId)) {
            $user = User::query()->find((int) $athleteId);
            if ($user) {
                return $user;
            }
        }

        $ghlContactId = trim((string) (
            $payload['athlete_ghl_contact_id']
            ?? $payload['athlete_contact_id']
            ?? ''
        ));

        if ($ghlContactId !== '') {
            $user = User::query()->where('ghl_contact_id', $ghlContactId)->first();
            if ($user) {
                return $user;
            }
        }

        $email = strtolower(trim((string) ($payload['athlete_email'] ?? '')));

        if ($email !== '') {
            $user = User::query()
                ->where(function ($query) use ($email): void {
                    $query->whereRaw('LOWER(email) = ?', [$email])
                        ->orWhereRaw('LOWER(personal_email) = ?', [$email]);
                })
                ->first();

            if ($user) {
                return $user;
            }
        }

        $locationId = trim((string) (
            $payload['athlete_ghl_location_id']
            ?? $payload['ghl_location_id']
            ?? ''
        ));

        if ($locationId !== '') {
            $users = User::query()
                ->where('ghl_location_id', $locationId)
                ->limit(2)
                ->get();

            // Use location as a final fallback only when it uniquely identifies one
            // local athlete. This avoids assigning a click to the wrong user.
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