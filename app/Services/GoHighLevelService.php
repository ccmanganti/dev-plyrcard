<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoHighLevelService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('ghl.base_url', 'https://services.leadconnectorhq.com'), '/');
    }

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

        if (config('ghl.profile_completion_field_id')) {
            $customField['id'] = config('ghl.profile_completion_field_id');
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

        if (config('ghl.site_status_field_id')) {
            $customField['id'] = config('ghl.site_status_field_id');
        }

        return $this->updateContactCustomFields($contactId, [$customField], [
            'action' => 'site_published',
            'user_id' => $user->id,
            'website_id' => $website->id,
        ]);
    }

    public function enabled(): bool
    {
        return filled(config('ghl.token')) && filled(config('ghl.location_id'));
    }

    public function dashboardCommands(): array
    {
        return collect(config('ghl.commands', []))
            ->map(fn (array $command, string $key): array => [
                'key' => $key,
                'label' => $command['label'] ?? str($key)->headline()->toString(),
                'description' => $command['description'] ?? null,
                'type' => $command['type'] ?? null,
                'tag' => $command['tag'] ?? null,
            ])
            ->values()
            ->all();
    }

    public function runDashboardCommand(User $user, string $commandKey): array
    {
        $command = config("ghl.commands.{$commandKey}");

        if (! $command) {
            return [
                'command' => $commandKey,
                'count' => 0,
                'contacts' => [],
                'error' => "Unknown GHL dashboard command [{$commandKey}].",
            ];
        }

        return match ($command['type'] ?? null) {
            'contacts_by_tag' => $this->getContactsByTagForUser(
                user: $user,
                tag: (string) $command['tag'],
            ),

            default => [
                'command' => $commandKey,
                'count' => 0,
                'contacts' => [],
                'error' => "Unsupported GHL dashboard command type [" . ($command['type'] ?? 'null') . "].",
            ],
        };
    }

    public function getViewedProfileContactsForUser(User $user): array
    {
        return $this->getContactsByTagForUser($user, 'viewed profile');
    }

    public function getViewedHighlightContactsForUser(User $user): array
    {
        return $this->getContactsByTagForUser($user, 'viewed highlights');
    }

    public function getContactsByTagForUser(User $user, string $tag): array
    {
        $locationId = filled($user->ghl_location_id ?? null)
            ? trim((string) $user->ghl_location_id)
            : config('ghl.location_id');

        $tokenOverride = filled($user->ghl_api_key ?? null)
            ? trim((string) $user->ghl_api_key)
            : null;

        if (! $locationId) {
            return [
                'tag' => $tag,
                'count' => 0,
                'contacts' => [],
                'error' => 'Missing GHL Location ID.',
                'debug' => [
                    'stage' => 'missing_location_id',
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'has_user_location_id' => filled($user->ghl_location_id ?? null),
                    'has_config_location_id' => filled(config('ghl.location_id')),
                ],
            ];
        }

        $token = $this->tokenForLocation($locationId, $tokenOverride);

        if (! $token) {
            return [
                'tag' => $tag,
                'count' => 0,
                'contacts' => [],
                'error' => 'Missing GHL API token.',
                'debug' => [
                    'stage' => 'missing_token',
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'location_id' => $locationId,
                    'has_user_api_key' => filled($user->ghl_api_key ?? null),
                    'has_config_token' => filled(config('ghl.token')),
                ],
            ];
        }

        return $this->getContactsByTag(
            tag: $tag,
            locationId: $locationId,
            tokenOverride: $tokenOverride,
            debugContext: [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'using_user_location_id' => filled($user->ghl_location_id ?? null),
                'using_user_api_key' => filled($user->ghl_api_key ?? null),
            ],
        );
    }

    public function getContactsByTag(
        string $tag,
        ?string $locationId = null,
        ?string $tokenOverride = null,
        int $pageLimit = 100,
        int $maxPages = 50,
        array $debugContext = [],
    ): array {
        $locationId = $locationId ?: config('ghl.location_id');
        $token = $this->tokenForLocation($locationId, $tokenOverride);

        if (! $locationId || ! $token) {
            return [
                'tag' => $tag,
                'count' => 0,
                'contacts' => [],
                'error' => 'Missing GHL Location ID or API token.',
                'debug' => array_merge($debugContext, [
                    'stage' => 'missing_location_or_token',
                    'location_id' => $locationId,
                    'has_token' => filled($token),
                ]),
            ];
        }

        $contacts = collect();
        $debugResponses = [];

        $page = 1;
        $total = null;

        do {
            $payload = [
                'locationId' => $locationId,
                'page' => $page,
                'pageLimit' => $pageLimit,
                'filters' => [
                    [
                        'field' => 'tags',
                        'operator' => 'eq',
                        'value' => $tag,
                    ],
                ],
            ];

            $response = Http::withHeaders([
                    'Version' => config('ghl.version', '2023-02-21'),
                ])
                ->timeout((int) config('ghl.timeout', 20))
                ->withToken($token)
                ->acceptJson()
                ->asJson()
                ->post("{$this->baseUrl}/contacts/search", $payload);

            $data = $response->json() ?? [];

            $rawContacts = $this->extractContactsFromResponse($data);

            $items = collect($rawContacts)
                ->filter(fn ($contact): bool => is_array($contact))
                ->filter(fn (array $contact): bool => $this->contactHasTag($contact, $tag))
                ->map(fn (array $contact): array => $this->transformDashboardContact($contact))
                ->values();

            $debugResponses[] = [
                'page' => $page,
                'endpoint' => "{$this->baseUrl}/contacts/search",
                'method' => 'POST',
                'status' => $response->status(),
                'successful' => $response->successful(),
                'payload' => $payload,
                'response_keys' => array_keys($data),
                'total_from_response' => $data['total'] ?? null,
                'raw_contacts_count' => count($rawContacts),
                'matched_contacts_count' => $items->count(),
                'sample_raw_contact' => $rawContacts[0] ?? null,
                'sample_parsed_contact' => $items->first(),
                'body_when_failed' => $response->failed() ? $response->body() : null,
            ];

            Log::info('GHL contacts/search request debug.', [
                'location_id' => $locationId,
                'tag' => $tag,
                'page' => $page,
                'status' => $response->status(),
                'raw_contacts_count' => count($rawContacts),
                'matched_contacts_count' => $items->count(),
                'response_keys' => array_keys($data),
            ]);

            if ($response->failed()) {
                Log::error('GHL contacts/search request failed.', [
                    'location_id' => $locationId,
                    'tag' => $tag,
                    'page' => $page,
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'payload' => $payload,
                ]);

                return [
                    'tag' => $tag,
                    'count' => $contacts->count(),
                    'contacts' => $contacts->values()->all(),
                    'location_id' => $locationId,
                    'error' => 'GHL request failed.',
                    'status' => $response->status(),
                    'debug' => array_merge($debugContext, [
                        'stage' => 'request_failed',
                        'has_token' => true,
                        'token_prefix' => substr($token, 0, 8),
                        'token_length' => strlen($token),
                        'responses' => $debugResponses,
                    ]),
                ];
            }

            $contacts = $contacts->merge($items);

            $total = isset($data['total']) ? (int) $data['total'] : $total;
            $rawContactCount = count($rawContacts);

            $page++;
        } while (
            $rawContactCount >= $pageLimit
            && $page <= $maxPages
            && (
                is_null($total)
                || $contacts->count() < $total
            )
        );

        $contacts = $contacts
            ->unique('id')
            ->values();

        return [
            'tag' => $tag,
            'count' => $contacts->count(),
            'contacts' => $contacts->all(),
            'location_id' => $locationId,
            'debug' => array_merge($debugContext, [
                'stage' => 'complete',
                'has_token' => true,
                'token_prefix' => substr($token, 0, 8),
                'token_length' => strlen($token),
                'operator' => 'eq',
                'responses' => $debugResponses,
            ]),
        ];
    }

    public function getCalendars(?string $locationId = null, ?string $tokenOverride = null): array
    {
        $locationId = $locationId ?: config('ghl.location_id');

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
            ->timeout((int) config('ghl.timeout', 20))
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

    public function getFirstActivePersonalCalendarForWebsite(Website $website): ?array
    {
        $locationId = filled($website->ghl_location_id)
            ? trim((string) $website->ghl_location_id)
            : config('ghl.location_id');

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
        $locationId = $locationId ?: config('ghl.location_id');
        $token = $this->tokenForLocation($locationId, $tokenOverride);

        if (! $token || ! $locationId) {
            return null;
        }

        $payload['locationId'] = $payload['locationId'] ?? $locationId;

        $response = Http::withHeaders([
                'Version' => '2021-07-28',
            ])
            ->timeout((int) config('ghl.timeout', 20))
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
        $token = $this->tokenForLocation($locationId ?: config('ghl.location_id'), $tokenOverride);

        if (! $token || ! $contactId || ! $body) {
            return false;
        }

        $response = Http::withHeaders([
                'Version' => '2021-07-28',
            ])
            ->timeout((int) config('ghl.timeout', 20))
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

        $locationId = $locationId ?: config('ghl.location_id');
        $defaultLocationId = config('ghl.location_id');
        $defaultToken = config('ghl.token');

        if (! $locationId || ! $defaultToken) {
            return $defaultToken ?: null;
        }

        if ($locationId === $defaultLocationId) {
            return $defaultToken;
        }

        return $this->getLocationAccessToken($locationId) ?: $defaultToken;
    }

    public function getLocationAccessToken(string $locationId): ?string
    {
        $agencyToken = config('ghl.agency_token');
        $companyId = config('ghl.company_id');

        if (! $agencyToken || ! $companyId || ! $locationId) {
            return null;
        }

        $response = Http::asForm()
            ->withHeaders([
                'Version' => config('ghl.version', '2023-02-21'),
            ])
            ->timeout((int) config('ghl.timeout', 20))
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

    private function extractContactsFromResponse(array $data): array
    {
        $contacts = $data['contacts']
            ?? $data['contact']
            ?? $data['data']
            ?? [];

        if (isset($contacts['id'])) {
            return [$contacts];
        }

        if (! is_array($contacts)) {
            return [];
        }

        return array_values(array_filter(
            $contacts,
            fn ($contact): bool => is_array($contact)
        ));
    }

    private function contactHasTag(array $contact, string $tag): bool
    {
        $needle = strtolower(trim($tag));

        return collect($contact['tags'] ?? [])
            ->map(fn ($contactTag): string => strtolower(trim((string) $contactTag)))
            ->contains($needle);
    }

    private function transformDashboardContact(array $contact): array
    {
        $customFields = collect($contact['customFields'] ?? []);

        return [
            'id' => $contact['id'] ?? null,
            'location_id' => $contact['locationId'] ?? null,

            'name' => $contact['contactName']
                ?? trim(($contact['firstName'] ?? '') . ' ' . ($contact['lastName'] ?? '')),

            'first_name' => $contact['firstName'] ?? null,
            'last_name' => $contact['lastName'] ?? null,
            'email' => $contact['email'] ?? null,
            'phone' => $contact['phone'] ?? null,

            'type' => $contact['type'] ?? null,
            'source' => $contact['source'] ?? null,
            'tags' => $contact['tags'] ?? [],

            'school_or_company' => $customFields->get(0)['value'] ?? null,
            'title' => $customFields->get(1)['value'] ?? null,
            'conference' => $customFields->get(2)['value'] ?? null,
            'division' => $customFields->get(3)['value'] ?? null,

            'valid_email' => $contact['validEmail'] ?? null,
            'dnd' => $contact['dnd'] ?? false,
            'date_added' => $contact['dateAdded'] ?? null,
            'date_updated' => $contact['dateUpdated'] ?? null,
        ];
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
        $token = config('ghl.token');

        if (! $token) {
            Log::warning('GHL custom field sync skipped. Missing token.', array_merge($context, [
                'contact_id' => $contactId,
            ]));

            return false;
        }

        $response = Http::withHeaders([
                'Version' => '2021-07-28',
            ])
            ->timeout((int) config('ghl.timeout', 20))
            ->withToken($token)
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

        $locationId = config('ghl.location_id');
        $token = config('ghl.token');

        if (! $locationId || ! $token) {
            return null;
        }

        $response = Http::withHeaders([
                'Version' => '2021-07-28',
            ])
            ->timeout((int) config('ghl.timeout', 20))
            ->withToken($token)
            ->acceptJson()
            ->get("{$this->baseUrl}/contacts/search/duplicate", [
                'locationId' => $locationId,
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