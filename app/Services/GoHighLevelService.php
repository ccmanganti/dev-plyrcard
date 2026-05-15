<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoHighLevelService
{
    private string $baseUrl = 'https://services.leadconnectorhq.com';

    public function syncProfileCompletion(User $user, int $completion): bool
    {
        $contactId = $this->resolveContactId($user);

        if (! $contactId) {
            Log::warning('GHL profile completion sync skipped. Contact not found.', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return false;
        }

        $customField = [
            'key' => 'profile_completion_threshold',
            'field_value' => $completion,
        ];

        if (config('services.ghl.profile_completion_field_id')) {
            $customField['id'] = config('services.ghl.profile_completion_field_id');
        }

        return $this->updateContactCustomFields($contactId, [$customField], [
            'action' => 'profile_completion',
            'user_id' => $user->id,
            'completion' => $completion,
        ]);
    }

    public function syncSitePublished(User $user, Website $website): bool
    {
        $contactId = $this->resolveContactId($user);

        if (! $contactId) {
            Log::warning('GHL site published sync skipped. Contact not found.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'website_id' => $website->id,
            ]);

            return false;
        }

        $customField = [
            'key' => 'site_status',
            'field_value' => 'published',
        ];

        if (config('services.ghl.site_status_field_id')) {
            $customField['id'] = config('services.ghl.site_status_field_id');
        }

        return $this->updateContactCustomFields($contactId, [$customField], [
            'action' => 'site_published',
            'user_id' => $user->id,
            'website_id' => $website->id,
        ]);
    }

    public function enabled(): bool
    {
        return filled(config('services.ghl.token')) && filled(config('services.ghl.location_id'));
    }

    /**
     * List calendars for a GHL location.
     *
     * Pass $tokenOverride for a player's manually configured sub-account Private Integration Token.
     */
    public function getCalendars(?string $locationId = null, ?string $tokenOverride = null): array
    {
        $locationId = $locationId ?: config('services.ghl.location_id');

        if (! $locationId) {
            return [];
        }

        $token = $this->tokenForLocation($locationId, $tokenOverride);

        if (! $token) {
            Log::warning('GHL calendar list skipped. Missing token.', [
                'location_id' => $locationId,
                'has_manual_token' => filled($tokenOverride),
            ]);

            return [];
        }

        $response = Http::withHeaders([
                'Version' => '2021-04-15',
            ])
            ->withToken($token)
            ->acceptJson()
            ->get("{$this->baseUrl}/calendars/", [
                'locationId' => $locationId,
            ]);

        if ($response->failed()) {
            Log::error('GHL calendar list failed.', [
                'location_id' => $locationId,
                'has_manual_token' => filled($tokenOverride),
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        $data = $response->json() ?? [];
        $calendars = $data['calendars'] ?? $data['data'] ?? [];

        return collect($calendars)
            ->map(function (array $calendar) use ($locationId) {
                $id = $calendar['id'] ?? $calendar['_id'] ?? null;

                if (! $id) {
                    return null;
                }

                return [
                    'id' => $id,
                    'name' => $calendar['name'] ?? $calendar['title'] ?? 'Calendar',
                    'location_id' => $calendar['locationId'] ?? $locationId,
                    'is_active' => $this->calendarIsActive($calendar),
                    'is_personal' => $this->calendarIsPersonal($calendar),
                    'embed_url' => $this->calendarEmbedUrl($id),
                    'raw' => $calendar,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Pull the first active personal calendar from a sub-account.
     *
     * "Personal" is detected defensively because GHL responses may vary:
     * - calendarType/type/calendar_type equals personal, OR
     * - exactly one team member is attached, which matches GHL's personal-calendar rule.
     */
    public function getFirstActivePersonalCalendar(?string $locationId = null, ?string $tokenOverride = null): ?array
    {
        $calendars = collect($this->getCalendars($locationId, $tokenOverride));

        $personalActive = $calendars
            ->filter(fn (array $calendar) => (bool) ($calendar['is_active'] ?? false))
            ->filter(fn (array $calendar) => (bool) ($calendar['is_personal'] ?? false))
            ->values();

        if ($personalActive->isNotEmpty()) {
            return $personalActive->first();
        }

        /*
         * Fallback: if GHL did not expose personal-calendar metadata clearly,
         * use the first active calendar so the admin can still get a working widget.
         */
        return $calendars
            ->filter(fn (array $calendar) => (bool) ($calendar['is_active'] ?? false))
            ->values()
            ->first();
    }

    /**
     * Resolve and store the first active personal calendar for a Website.
     */
    public function syncFirstActivePersonalCalendarForWebsite(Website $website): array
    {
        $locationId = $website->ghl_location_id ?: config('services.ghl.location_id');
        $token = $website->ghl_api_token ?: null;

        if (blank($locationId)) {
            return [
                'ok' => false,
                'message' => 'Missing GHL Location ID.',
            ];
        }

        if (blank($token) && blank(config('services.ghl.token'))) {
            return [
                'ok' => false,
                'message' => 'Missing GHL Private Integration Token.',
            ];
        }

        $calendar = $this->getFirstActivePersonalCalendar($locationId, $token);

        if (! $calendar) {
            return [
                'ok' => false,
                'message' => 'No active personal calendar was found for this GHL location.',
            ];
        }

        $website->forceFill([
            'ghl_location_id' => $locationId,
            'ghl_calendar_id' => $calendar['id'] ?? null,
            'ghl_calendar_name' => $calendar['name'] ?? null,
            'ghl_calendar_embed_url' => $calendar['embed_url'] ?? $this->calendarEmbedUrl($calendar['id'] ?? ''),
        ])->saveQuietly();

        return [
            'ok' => true,
            'message' => 'First active personal calendar synced.',
            'calendar' => $calendar,
        ];
    }

    public function calendarEmbedUrl(string $calendarId): string
    {
        return 'https://systems.plyrcard.com/widget/booking/' . ltrim(trim($calendarId), '/');
    }

    public function findCalendarById(string $calendarId, ?string $locationId = null, ?string $tokenOverride = null): ?array
    {
        return collect($this->getCalendars($locationId, $tokenOverride))->firstWhere('id', $calendarId);
    }

    public function upsertContact(array $payload, ?string $locationId = null, ?string $tokenOverride = null): ?string
    {
        $locationId = $locationId ?: config('services.ghl.location_id');
        $token = $this->tokenForLocation($locationId, $tokenOverride);

        if (! $token || ! $locationId) {
            return null;
        }

        $payload['locationId'] = $payload['locationId'] ?? $locationId;

        $response = Http::withHeaders([
                'Version' => '2021-07-28',
            ])
            ->withToken($token)
            ->acceptJson()
            ->post("{$this->baseUrl}/contacts/upsert", $payload);

        if ($response->failed()) {
            Log::error('GHL contact upsert failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payload' => $payload,
            ]);

            return null;
        }

        $data = $response->json() ?? [];

        return $data['contact']['id'] ?? $data['id'] ?? null;
    }

    public function addContactNote(string $contactId, string $body, ?string $locationId = null, ?string $tokenOverride = null): bool
    {
        $token = $this->tokenForLocation($locationId ?: config('services.ghl.location_id'), $tokenOverride);

        if (! $token || ! $contactId || ! $body) {
            return false;
        }

        $response = Http::withHeaders([
                'Version' => '2021-07-28',
            ])
            ->withToken($token)
            ->acceptJson()
            ->post("{$this->baseUrl}/contacts/{$contactId}/notes", [
                'body' => $body,
            ]);

        if ($response->failed()) {
            Log::error('GHL contact note failed.', [
                'contact_id' => $contactId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }

    public function tokenForLocation(?string $locationId = null, ?string $tokenOverride = null): ?string
    {
        if (filled($tokenOverride)) {
            return trim((string) $tokenOverride);
        }

        $locationId = $locationId ?: config('services.ghl.location_id');
        $defaultLocationId = config('services.ghl.location_id');
        $defaultToken = config('services.ghl.token');

        if (! $locationId || ! $defaultToken) {
            return $defaultToken ?: null;
        }

        if ($locationId === $defaultLocationId) {
            return $defaultToken;
        }

        return $this->getLocationAccessToken($locationId) ?: $defaultToken;
    }

    /**
     * Optional agency-level support for calendars across multiple GHL sub-accounts.
     */
    public function getLocationAccessToken(string $locationId): ?string
    {
        $agencyToken = config('services.ghl.agency_token');
        $companyId = config('services.ghl.company_id');

        if (! $agencyToken || ! $companyId || ! $locationId) {
            return null;
        }

        $response = Http::asForm()
            ->withHeaders([
                'Version' => '2023-02-21',
            ])
            ->withToken($agencyToken)
            ->acceptJson()
            ->post("{$this->baseUrl}/oauth/locationToken", [
                'companyId' => $companyId,
                'locationId' => $locationId,
            ]);

        if ($response->failed()) {
            Log::error('GHL location token request failed.', [
                'location_id' => $locationId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        return $response->json('access_token');
    }

    private function calendarIsActive(array $calendar): bool
    {
        foreach (['isActive', 'is_active', 'active'] as $key) {
            if (array_key_exists($key, $calendar)) {
                return filter_var($calendar[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        $status = strtolower((string) ($calendar['status'] ?? $calendar['calendarStatus'] ?? ''));

        if (in_array($status, ['inactive', 'disabled', 'archived', 'deleted'], true)) {
            return false;
        }

        return true;
    }

    private function calendarIsPersonal(array $calendar): bool
    {
        $type = strtolower((string) (
            $calendar['calendarType']
            ?? $calendar['calendar_type']
            ?? $calendar['type']
            ?? $calendar['kind']
            ?? ''
        ));

        if (in_array($type, ['personal', 'personal_calendar'], true)) {
            return true;
        }

        $teamMembers = $calendar['teamMembers']
            ?? $calendar['team_members']
            ?? $calendar['users']
            ?? [];

        if (is_array($teamMembers) && count($teamMembers) === 1) {
            return true;
        }

        return false;
    }

    private function resolveContactId(User $user): ?string
    {
        $contactId = $user->ghl_contact_id ?: $this->findContactIdByEmail($user->email);

        if ($contactId && ! $user->ghl_contact_id) {
            $user->forceFill([
                'ghl_contact_id' => $contactId,
            ])->saveQuietly();
        }

        return $contactId;
    }

    private function updateContactCustomFields(string $contactId, array $customFields, array $context = []): bool
    {
        $response = Http::withHeaders([
                'Version' => '2021-07-28',
            ])
            ->withToken(config('services.ghl.token'))
            ->acceptJson()
            ->put("{$this->baseUrl}/contacts/{$contactId}", [
                'customFields' => $customFields,
            ]);

        if ($response->failed()) {
            Log::error('GHL custom field sync failed.', array_merge($context, [
                'contact_id' => $contactId,
                'status' => $response->status(),
                'body' => $response->body(),
                'custom_fields' => $customFields,
            ]));

            return false;
        }

        Log::info('GHL custom field sync successful.', array_merge($context, [
            'contact_id' => $contactId,
            'custom_fields' => $customFields,
        ]));

        return true;
    }

    private function findContactIdByEmail(?string $email): ?string
    {
        if (! $email) {
            return null;
        }

        $email = trim($email);

        $response = Http::withHeaders([
                'Version' => '2021-07-28',
            ])
            ->withToken(config('services.ghl.token'))
            ->acceptJson()
            ->get("{$this->baseUrl}/contacts/search/duplicate", [
                'locationId' => config('services.ghl.location_id'),
                'email' => $email,
            ]);

        if ($response->failed()) {
            Log::error('GHL contact search failed.', [
                'email' => $email,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json() ?? [];

        $contacts = collect(
            $data['contacts'] ??
            (isset($data['contact']) ? [$data['contact']] : [])
        );

        $matched = $contacts->first(function ($contact) use ($email) {
            return strtolower(trim($contact['email'] ?? '')) === strtolower($email);
        });

        return $matched['id'] ?? null;
    }
}
