<?php

namespace App\Services;

use App\Models\CoachDatabaseEmailMessage;
use App\Models\CoachDatabaseSchool;
use App\Models\CoachDatabaseTrackingEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ExternalTrackingAttributionService
{
    public function shouldTrack(Request $request): bool
    {
        return $request->boolean('rc_external')
            || $request->filled('rc_contact_id')
            || $request->filled('rc_email');
    }

    public function recordProfileView(User $player, Request $request): ?CoachDatabaseTrackingEvent
    {
        if (! $this->shouldTrack($request)) {
            return null;
        }

        $identity = $this->resolveIdentity($player, $request);

        $event = CoachDatabaseTrackingEvent::query()->create([
            'athlete_user_id' => $player->getKey(),
            'ghl_location_id' => trim((string) ($player->ghl_location_id ?? '')),
            'coach_contact_id' => $identity['coach_contact_id'],
            'school_business_id' => $identity['school_business_id'],
            'campaign_uuid' => null,
            'message_uuid' => null,
            'template_id' => null,
            'event_type' => 'profile_view',
            'platform' => 'website',
            'source' => $this->normalizedSource($request),
            'destination_url' => $this->cleanProfileDestination($request),
            'visitor_hash' => $this->visitorHash($player, $request, $identity['coach_contact_id']),
            'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'referer' => Str::limit((string) $request->headers->get('referer'), 2000, ''),
            'metadata' => [
                'tracking_mode' => 'external_campaign',
                'identity_type' => $identity['identity_type'],
                'coach_name' => $identity['coach_name'],
                'coach_email' => $identity['coach_email'],
                'coach_title' => $identity['coach_title'],
                'school_name' => $identity['school_name'],
                'school_logo_url' => $identity['school_logo_url'],
                'business_logo_url' => $identity['school_logo_url'],
                'coach_match_source' => $identity['match_source'],
                'recipient_email' => $identity['coach_email'],
                'recipient_name' => $identity['coach_name'],
                'ghl_contact_id' => $identity['supplied_contact_id'],
                'utm_source' => $request->query('utm_source'),
                'utm_medium' => $request->query('utm_medium'),
                'utm_campaign' => $request->query('utm_campaign'),
                'rc_destination' => $request->query('rc_destination'),
                'rc_external' => true,
                'request_host' => $request->getHost(),
                'request_path' => $request->path(),
            ],
            'occurred_at' => now(),
        ]);

        Log::info('External GHL profile view saved.', [
            'id' => $event->getKey(),
            'athlete_user_id' => $event->athlete_user_id,
            'coach_contact_id' => $event->coach_contact_id,
            'school_business_id' => $event->school_business_id,
            'coach_email' => $identity['coach_email'],
            'coach_name' => $identity['coach_name'],
            'match_source' => $identity['match_source'],
        ]);

        return $event;
    }

    protected function resolveIdentity(User $player, Request $request): array
    {
        $suppliedContactId = $this->normalizeMergeValue($request->query('rc_contact_id'));
        $email = $this->normalizeEmail($request->query('rc_email'));
        $locationId = trim((string) ($player->ghl_location_id ?? ''));

        // The Coach Database UI stores the complete coach dataset in this cache snapshot.
        // Search it first because coach_database_schools only stores one normalized head coach.
        $cachedCoach = $this->cachedCoachMatch($player, $suppliedContactId, $email);
        if ($cachedCoach !== null) {
            return $this->identityFromCoachRow($cachedCoach, $suppliedContactId, $email);
        }

        if (Schema::hasTable('coach_database_email_messages')) {
            $message = CoachDatabaseEmailMessage::query()
                ->where('athlete_user_id', $player->getKey())
                ->when($locationId !== '', fn ($query) => $query->where('ghl_location_id', $locationId))
                ->where(function ($query) use ($suppliedContactId, $email): void {
                    if ($email !== '') {
                        $query->whereRaw('LOWER(recipient_email) = ?', [$email]);
                        if ($suppliedContactId !== '') {
                            $query->orWhere('coach_contact_id', $suppliedContactId);
                        }
                        return;
                    }

                    if ($suppliedContactId !== '') {
                        $query->where('coach_contact_id', $suppliedContactId);
                        return;
                    }

                    $query->whereRaw('1 = 0');
                })
                ->latest('id')
                ->first();

            if ($message) {
                $school = $this->schoolByBusinessId($player, $locationId, $message->school_business_id);

                return [
                    'identity_type' => 'known_coach',
                    'coach_contact_id' => $message->coach_contact_id ?: ($suppliedContactId ?: $email),
                    'school_business_id' => $message->school_business_id,
                    'coach_name' => $message->recipient_name ?: $school?->head_coach_name ?: 'Known coach contact',
                    'coach_email' => $this->normalizeEmail($message->recipient_email) ?: ($email ?: null),
                    'coach_title' => $school?->head_coach_title,
                    'school_name' => $message->school_name ?: $school?->name,
                    'school_logo_url' => $school?->logo_url,
                    'match_source' => 'coach_database_email_messages',
                    'supplied_contact_id' => $suppliedContactId ?: null,
                ];
            }
        }

        if ($email !== '' && Schema::hasTable('coach_database_schools')) {
            $school = CoachDatabaseSchool::query()
                ->where('user_id', $player->getKey())
                ->when($locationId !== '', fn ($query) => $query->where('ghl_location_id', $locationId))
                ->whereRaw('LOWER(head_coach_email) = ?', [$email])
                ->first();

            if ($school) {
                return [
                    'identity_type' => 'known_coach',
                    'coach_contact_id' => $suppliedContactId ?: $email,
                    'school_business_id' => $school->business_id,
                    'coach_name' => $school->head_coach_name ?: 'Known coach contact',
                    'coach_email' => $email,
                    'coach_title' => $school->head_coach_title,
                    'school_name' => $school->name,
                    'school_logo_url' => $school->logo_url,
                    'match_source' => 'coach_database_schools.head_coach_email',
                    'supplied_contact_id' => $suppliedContactId ?: null,
                ];
            }
        }

        return [
            'identity_type' => ($email !== '' || $suppliedContactId !== '') ? 'external_recipient' : 'anonymous',
            'coach_contact_id' => null,
            'school_business_id' => null,
            'coach_name' => null,
            'coach_email' => $email ?: null,
            'coach_title' => null,
            'school_name' => null,
            'school_logo_url' => null,
            'match_source' => null,
            'supplied_contact_id' => $suppliedContactId ?: null,
        ];
    }

    protected function cachedCoachMatch(User $player, string $contactId, string $email): ?array
    {
        $locationSlug = Str::slug(trim((string) ($player->ghl_location_id ?? '')) ?: 'default');
        $snapshot = Cache::get('coach-database:v10:' . $player->getKey() . ':' . $locationSlug, []);

        if (! is_array($snapshot)) {
            return null;
        }

        $coaches = collect($snapshot['coaches'] ?? [])
            ->merge($snapshot['contacts'] ?? [])
            ->filter(fn ($row): bool => is_array($row));

        return $coaches->first(function (array $coach) use ($contactId, $email): bool {
            $ids = collect([
                $coach['id'] ?? null,
                $coach['contact_id'] ?? null,
                $coach['ghl_contact_id'] ?? null,
            ])->map(fn ($value): string => trim((string) $value))->filter();

            if ($contactId !== '' && $ids->contains($contactId)) {
                return true;
            }

            $coachEmail = $this->normalizeEmail($coach['email'] ?? $coach['contact_email'] ?? null);
            return $email !== '' && $coachEmail !== '' && $coachEmail === $email;
        });
    }

    protected function identityFromCoachRow(array $coach, string $suppliedContactId, string $email): array
    {
        $coachContactId = trim((string) (
            $coach['id']
            ?? $coach['contact_id']
            ?? $coach['ghl_contact_id']
            ?? $suppliedContactId
            ?? ''
        ));

        $coachEmail = $this->normalizeEmail($coach['email'] ?? $coach['contact_email'] ?? null) ?: ($email ?: null);
        $firstName = trim((string) ($coach['first_name'] ?? $coach['firstName'] ?? ''));
        $lastName = trim((string) ($coach['last_name'] ?? $coach['lastName'] ?? ''));
        $coachName = trim((string) ($coach['name'] ?? $coach['full_name'] ?? implode(' ', array_filter([$firstName, $lastName]))));
        $schoolName = trim((string) ($coach['school'] ?? $coach['school_name'] ?? $coach['company_name'] ?? $coach['business_name'] ?? ''));
        $businessId = trim((string) ($coach['business_id'] ?? $coach['company_id'] ?? $coach['school_id'] ?? ''));
        $logo = trim((string) ($coach['school_logo_url'] ?? $coach['business_logo_url'] ?? $coach['logo_url'] ?? ''));

        return [
            'identity_type' => 'known_coach',
            'coach_contact_id' => $coachContactId !== '' ? $coachContactId : ($suppliedContactId ?: $coachEmail),
            'school_business_id' => $businessId !== '' ? $businessId : null,
            'coach_name' => $coachName !== '' ? $coachName : 'Known coach contact',
            'coach_email' => $coachEmail,
            'coach_title' => trim((string) ($coach['title'] ?? $coach['job_title'] ?? '')) ?: null,
            'school_name' => $schoolName !== '' ? $schoolName : null,
            'school_logo_url' => $logo !== '' ? $logo : null,
            'match_source' => 'coach_database_cache_snapshot',
            'supplied_contact_id' => $suppliedContactId ?: null,
        ];
    }

    protected function schoolByBusinessId(User $player, string $locationId, mixed $businessId): ?CoachDatabaseSchool
    {
        $businessId = trim((string) $businessId);
        if ($businessId === '' || ! Schema::hasTable('coach_database_schools')) {
            return null;
        }

        return CoachDatabaseSchool::query()
            ->where('user_id', $player->getKey())
            ->when($locationId !== '', fn ($query) => $query->where('ghl_location_id', $locationId))
            ->where('business_id', $businessId)
            ->first();
    }

    protected function normalizedSource(Request $request): string
    {
        $raw = strtolower(trim((string) $request->query('utm_source', 'external')));

        return match ($raw) {
            'ghl', 'gohighlevel', 'highlevel' => 'ghl_external_campaign',
            '' => 'external_campaign',
            default => Str::limit($raw . '_external_campaign', 80, ''),
        };
    }

    protected function visitorHash(User $player, Request $request, ?string $coachContactId): string
    {
        return hash('sha256', implode('|', [
            strtolower((string) $request->ip()),
            strtolower((string) $request->userAgent()),
            (string) $player->getKey(),
            'profile_view',
            'website',
            $this->cleanProfileDestination($request),
            $coachContactId ?: 'external-anonymous',
        ]));
    }

    protected function normalizeMergeValue(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '' || str_contains($value, '{{') || str_contains($value, '}}')) {
            return '';
        }

        return Str::limit($value, 191, '');
    }

    protected function normalizeEmail(mixed $email): string
    {
        $email = strtolower($this->normalizeMergeValue($email));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    protected function cleanProfileDestination(Request $request): string
    {
        return $request->getSchemeAndHttpHost() . '/' . ltrim($request->path(), '/');
    }
}