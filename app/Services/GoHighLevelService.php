<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
     * Return all calendars for the given GHL location.
     *
     * If a website/player token is provided, it is used first. Otherwise this
     * falls back to the configured platform token or optional agency location token.
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
                'has_token_override' => filled($tokenOverride),
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
                'has_token_override' => filled($tokenOverride),
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        $data = $response->json() ?? [];
        $calendars = $data['calendars'] ?? $data['calendar'] ?? $data['data'] ?? $data;

        return collect($calendars)
            ->filter(fn ($calendar): bool => is_array($calendar))
            ->map(function (array $calendar) use ($locationId) {
                $id = $calendar['id']
                    ?? $calendar['_id']
                    ?? $calendar['calendarId']
                    ?? null;

                if (! $id) {
                    return null;
                }

                return [
                    'id' => (string) $id,
                    'name' => (string) ($calendar['name'] ?? $calendar['title'] ?? 'GHL Calendar'),
                    'location_id' => (string) ($calendar['locationId'] ?? $locationId),
                    'embed_url' => (string) ($calendar['embedUrl']
                        ?? $calendar['widgetUrl']
                        ?? $calendar['calendarUrl']
                        ?? $this->calendarEmbedUrl((string) $id)),
                    'is_active' => $this->isCalendarActive($calendar),
                    'is_personal' => $this->isCalendarPersonal($calendar),
                    'raw' => $calendar,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Dynamically pull the first active personal calendar for a website.
     * This does not write the result into the website record.
     */
    public function getFirstActivePersonalCalendarForWebsite(Website $website): ?array
    {
        $locationId = filled($website->ghl_location_id)
            ? trim((string) $website->ghl_location_id)
            : config('services.ghl.location_id');

        $tokenOverride = filled($website->ghl_api_token)
            ? trim((string) $website->ghl_api_token)
            : null;

        return $this->getFirstActivePersonalCalendar($locationId, $tokenOverride);
    }

    public function getFirstActivePersonalCalendar(?string $locationId = null, ?string $tokenOverride = null): ?array
    {
        $calendars = collect($this->getCalendars($locationId, $tokenOverride))
            ->filter(fn (array $calendar): bool => (bool) ($calendar['is_active'] ?? false))
            ->values();

        if ($calendars->isEmpty()) {
            return null;
        }

        return $calendars->first(fn (array $calendar): bool => (bool) ($calendar['is_personal'] ?? false))
            ?: $calendars->first();
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
     * Add to config/services.php if needed:
     * 'agency_token' => env('GHL_AGENCY_ACCESS_TOKEN'),
     * 'company_id' => env('GHL_COMPANY_ID'),
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

    private function isCalendarActive(array $calendar): bool
    {
        $status = strtolower((string) ($calendar['status'] ?? ''));

        if (array_key_exists('isActive', $calendar)) {
            return (bool) $calendar['isActive'];
        }

        if (array_key_exists('active', $calendar)) {
            return (bool) $calendar['active'];
        }

        if (array_key_exists('isDeleted', $calendar) && (bool) $calendar['isDeleted']) {
            return false;
        }

        if (array_key_exists('deleted', $calendar) && (bool) $calendar['deleted']) {
            return false;
        }

        if ($status === '') {
            return true;
        }

        return in_array($status, ['active', 'enabled', 'published'], true);
    }

    private function isCalendarPersonal(array $calendar): bool
    {
        $type = strtolower((string) ($calendar['calendarType'] ?? $calendar['type'] ?? $calendar['eventType'] ?? ''));

        if (str_contains($type, 'personal')) {
            return true;
        }

        $teamMembers = $calendar['teamMembers']
            ?? $calendar['teamMemberIds']
            ?? $calendar['teamMember']
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