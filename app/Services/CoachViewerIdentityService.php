<?php

namespace App\Services;

use App\Models\CoachDatabaseEmailMessage;
use App\Models\CoachDatabaseSchool;
use App\Models\CoachDatabaseTrackingEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CoachViewerIdentityService
{
    /**
     * Resolve an ACTUAL coach for a profile view / social click notification.
     *
     * Anonymous/direct traffic intentionally returns matched=false. The normal
     * recruiting analytics may still record those visits; this service only
     * decides whether the athlete should receive an email notification.
     */
    public function resolve(User $player, Request $request, string $eventType, string $platform): array
    {
        $identity = $this->resolveFromRequest($player, $request);

        if ($identity['matched']) {
            return $identity;
        }

        // Tracked email/profile redirect links may record the coach on the
        // tracking request and then redirect to a clean player URL. In that
        // case the public page no longer carries rc_* identity parameters, so
        // use the coach-attributed event that was just persisted for the same
        // visitor/IP as a safe fallback.
        return $this->resolveFromRecentTrackingEvent($player, $request, $eventType, $platform);
    }

    protected function resolveFromRequest(User $player, Request $request): array
    {
        $contactId = $this->value(
            $request->query('rc_contact_id')
                ?: $request->query('rc_ghl_contact_id')
        );
        $email = $this->email(
            $request->query('rc_email')
                ?: $request->query('rc_coach_email')
        );

        // No coach attribution at all = anonymous/direct visitor.
        if ($contactId === '' && $email === '') {
            return $this->unmatched();
        }

        $locationId = trim((string) ($player->ghl_location_id ?? ''));

        // 1) Same hydrated Coach Database cache used by the current UI.
        $cacheKey = 'coach-database:v10:' . $player->getKey() . ':'
            . Str::slug($locationId !== '' ? $locationId : 'default');
        $snapshot = Cache::get($cacheKey, []);
        $coaches = is_array($snapshot) && is_array($snapshot['coaches'] ?? null)
            ? $snapshot['coaches']
            : [];

        $coach = collect($coaches)
            ->filter(fn ($row): bool => is_array($row))
            ->first(function (array $row) use ($contactId, $email): bool {
                $rowContactId = $this->value(
                    $row['id'] ?? $row['contact_id'] ?? $row['ghl_contact_id'] ?? null
                );
                $rowEmail = $this->email($row['email'] ?? $row['contact_email'] ?? null);

                return ($contactId !== '' && $rowContactId !== '' && hash_equals($contactId, $rowContactId))
                    || ($email !== '' && $rowEmail !== '' && hash_equals($email, $rowEmail));
            });

        if (is_array($coach)) {
            return $this->matched([
                'contact_id' => $coach['id'] ?? $coach['contact_id'] ?? $coach['ghl_contact_id'] ?? $contactId,
                'email' => $coach['email'] ?? $coach['contact_email'] ?? $email,
                'name' => $coach['name'] ?? collect([
                    $coach['first_name'] ?? null,
                    $coach['last_name'] ?? null,
                ])->filter()->implode(' '),
                'school' => $coach['school'] ?? $coach['school_name'] ?? $coach['company_name'] ?? $coach['business_name'] ?? null,
                'source' => 'coach_database_cache',
            ]);
        }

        // 2) A recipient we previously emailed from Coach Database.
        if (Schema::hasTable('coach_database_email_messages')) {
            $message = CoachDatabaseEmailMessage::query()
                ->where('athlete_user_id', $player->getKey())
                ->when($locationId !== '', fn ($query) => $query->where('ghl_location_id', $locationId))
                ->where(function ($query) use ($contactId, $email): void {
                    if ($contactId !== '') {
                        $query->where('coach_contact_id', $contactId);
                    }

                    if ($email !== '') {
                        if ($contactId !== '') {
                            $query->orWhereRaw('LOWER(recipient_email) = ?', [$email]);
                        } else {
                            $query->whereRaw('LOWER(recipient_email) = ?', [$email]);
                        }
                    }
                })
                ->latest('id')
                ->first();

            if ($message) {
                return $this->matched([
                    'contact_id' => $message->coach_contact_id ?: $contactId,
                    'email' => $message->recipient_email ?: $email,
                    'name' => $message->recipient_name,
                    'school' => null,
                    'source' => 'coach_database_email_messages',
                ]);
            }
        }

        // 3) A school/head-coach record already belonging to this player's DB.
        if ($email !== '' && Schema::hasTable('coach_database_schools')) {
            $school = CoachDatabaseSchool::query()
                ->where('user_id', $player->getKey())
                ->when($locationId !== '', fn ($query) => $query->where('ghl_location_id', $locationId))
                ->whereRaw('LOWER(head_coach_email) = ?', [$email])
                ->first();

            if ($school) {
                return $this->matched([
                    'contact_id' => $contactId,
                    'email' => $email,
                    'name' => $school->head_coach_name,
                    'school' => $school->name ?? $school->school_name ?? null,
                    'source' => 'coach_database_schools',
                ]);
            }
        }

        // A random rc_email / rc_contact_id value is not enough. It must match
        // a coach already known to PLYRCARD.
        return $this->unmatched();
    }

    protected function resolveFromRecentTrackingEvent(
        User $player,
        Request $request,
        string $eventType,
        string $platform,
    ): array {
        if (! Schema::hasTable('coach_database_tracking_events')) {
            return $this->unmatched();
        }

        $storedEventType = $eventType === 'profile_view' ? 'profile_view' : 'link_click';
        $platform = strtolower(trim($platform)) ?: 'website';
        $ipHash = hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));

        $event = CoachDatabaseTrackingEvent::query()
            ->where('athlete_user_id', $player->getKey())
            ->where('event_type', $storedEventType)
            ->where('platform', $platform)
            ->whereNotNull('coach_contact_id')
            ->where('coach_contact_id', '<>', '')
            ->where('ip_hash', $ipHash)
            ->where('occurred_at', '>=', now()->subMinutes(2))
            ->whereNotIn('source', ['direct_website_visit', 'anonymous', 'direct'])
            ->latest('id')
            ->first();

        if (! $event) {
            return $this->unmatched();
        }

        $metadata = is_array($event->metadata) ? $event->metadata : [];

        return $this->matched([
            'contact_id' => $event->coach_contact_id,
            'email' => $metadata['coach_email'] ?? $metadata['recipient_email'] ?? null,
            'name' => $metadata['coach_name'] ?? $metadata['recipient_name'] ?? null,
            'school' => $metadata['school_name'] ?? null,
            'source' => 'recent_coach_tracking_event',
        ]);
    }

    protected function matched(array $identity): array
    {
        return [
            'matched' => true,
            'contact_id' => $this->value($identity['contact_id'] ?? null) ?: null,
            'email' => $this->email($identity['email'] ?? null) ?: null,
            'name' => $this->value($identity['name'] ?? null) ?: null,
            'school' => $this->value($identity['school'] ?? null) ?: null,
            'source' => $this->value($identity['source'] ?? null) ?: 'matched',
        ];
    }

    protected function unmatched(): array
    {
        return [
            'matched' => false,
            'contact_id' => null,
            'email' => null,
            'name' => null,
            'school' => null,
            'source' => 'anonymous_or_unmatched',
        ];
    }

    protected function value(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '' || str_contains($value, '{{') || str_contains($value, '}}')) {
            return '';
        }

        return Str::limit($value, 191, '');
    }

    protected function email(mixed $value): string
    {
        $value = strtolower($this->value($value));

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
    }
}
