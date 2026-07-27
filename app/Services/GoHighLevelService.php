<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use App\Support\TrackingLinkRewriter;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoHighLevelService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('ghl.base_url', 'https://services.leadconnectorhq.com'), '/');
    }

    public function enabled(): bool
    {
        return filled(config('ghl.token')) && filled(config('ghl.location_id'));
    }

    public function getRecruitingRemoteCountsForUser(User $user): array
    {
        $credentials = $this->credentialsForUser($user);
        $locationId = $credentials['location_id'];
        $tokenOverride = $credentials['token_override'];

        if (! $locationId || ! $this->tokenForLocation($locationId, $tokenOverride)) {
            return [
                'success' => false,
                'schools_total' => null,
                'coaches_total' => null,
                'error' => 'Missing recruiting data connection.',
            ];
        }

        $schoolsResult = $this->getBusinessesPage(
            locationId: $locationId,
            tokenOverride: $tokenOverride,
            skip: 0,
            limit: 1,
        );

        $contactsResult = $this->getContactsPage(
            locationId: $locationId,
            tokenOverride: $tokenOverride,
            startAfter: null,
            startAfterId: null,
            limit: 1,
        );

        $schoolsTotal = $schoolsResult['total'] ?? null;
        $coachesTotal = $contactsResult['total'] ?? null;

        return [
            'success' => (bool) (($schoolsResult['success'] ?? false) && ($contactsResult['success'] ?? false)),
            'schools_total' => is_numeric($schoolsTotal) ? (int) $schoolsTotal : (int) ($schoolsResult['count'] ?? 0),
            'coaches_total' => is_numeric($coachesTotal) ? (int) $coachesTotal : (int) ($contactsResult['count'] ?? 0),
            'schools_error' => $schoolsResult['error'] ?? null,
            'coaches_error' => $contactsResult['error'] ?? null,
            'checked_at' => now()->toDateTimeString(),
        ];
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
                'error' => "Unknown Recruiting Center dashboard command [{$commandKey}].",
            ];
        }

        return match ($command['type'] ?? null) {
            'contacts_by_tag' => $this->getContactsByTagForUser($user, (string) $command['tag']),
            default => [
                'command' => $commandKey,
                'count' => 0,
                'contacts' => [],
                'error' => "Unsupported Recruiting Center dashboard command type [" . ($command['type'] ?? 'null') . "].",
            ],
        };
    }

    public function getViewedProfileContactsForUser(User $user): array
    {
        return $this->getContactsByTagForUser($user, config('ghl.coach_database.tags.viewed_profile', 'viewed profile'));
    }

    public function getViewedHighlightContactsForUser(User $user): array
    {
        return $this->getContactsByTagForUser($user, config('ghl.coach_database.tags.viewed_highlights', 'viewed highlights'));
    }

    public function getContactsByTagForUser(User $user, string $tag): array
    {
        $credentials = $this->credentialsForUser($user);
        $tag = trim($tag);

        if ($tag === '') {
            return [
                'success' => false,
                'tag' => $tag,
                'count' => 0,
                'contacts' => [],
                'error' => 'Missing tag.',
                'debug' => [],
            ];
        }

        $result = $this->searchAllContactsByTag(
            locationId: $credentials['location_id'],
            tokenOverride: $credentials['token_override'],
            tag: $tag,
            pageLimit: (int) config('ghl.coach_database.tag_search_page_limit', config('ghl.coach_database.page_limit', 100)),
            maxPages: (int) config('ghl.coach_database.tag_search_max_pages', 20),
        );

        $trackingFieldMap = $this->recruitingTrackingFieldMapForLocation($credentials['location_id'], $credentials['token_override']);

        // Do not re-filter by local tags here. HighLevel's contacts/search can return
        // contacts matched by the tag filter without including the full tags array in
        // every row. Trust the API-side tag filter and inject the searched tag into the
        // normalized coach row so Favorites/Saved/Lists can rebuild immediately.
        $contacts = collect($result['contacts'] ?? [])
            ->filter(fn ($contact): bool => is_array($contact))
            ->map(fn (array $contact): array => $this->transformCoachContact($this->ensureContactCarriesTag($contact, $tag), $trackingFieldMap))
            ->filter(fn (array $coach): bool => filled($coach['id'] ?? null))
            ->unique('id')
            ->values()
            ->all();

        return [
            'success' => (bool) ($result['success'] ?? false),
            'tag' => $tag,
            'count' => count($contacts),
            'contacts' => $contacts,
            'error' => $result['error'] ?? null,
            'debug' => $result['debug'] ?? [],
        ];
    }

    public function getContactsByTagsForUser(User $user, array $tags): array
    {
        $tags = collect($tags)
            ->map(fn ($tag): string => trim((string) $tag))
            ->filter()
            ->unique(fn (string $tag): string => strtolower($tag))
            ->values()
            ->all();

        $contacts = collect();
        $byTag = [];
        $debug = [];
        $success = true;
        $errors = [];

        foreach ($tags as $tag) {
            $result = $this->getContactsByTagForUser($user, $tag);
            $tagContacts = collect($result['contacts'] ?? [])
                ->filter(fn ($coach): bool => is_array($coach))
                ->values();

            $byTag[$tag] = [
                'success' => (bool) ($result['success'] ?? false),
                'count' => $tagContacts->count(),
                'contacts' => $tagContacts->all(),
                'error' => $result['error'] ?? null,
            ];

            if (! ($result['success'] ?? false)) {
                $success = false;
                if (filled($result['error'] ?? null)) {
                    $errors[] = $tag . ': ' . $result['error'];
                }
            }

            $contacts = $contacts->merge($tagContacts);
            $debug[$tag] = $result['debug'] ?? [];
        }

        return [
            'success' => $success || $contacts->isNotEmpty(),
            'contacts' => $contacts->unique('id')->values()->all(),
            'count' => $contacts->unique('id')->count(),
            'by_tag' => $byTag,
            'error' => $success ? null : implode(' | ', array_slice($errors, 0, 3)),
            'debug' => $debug,
        ];
    }

    public function getCoachContactsForUser(User $user, array $extraFilters = []): array
    {
        $credentials = $this->credentialsForUser($user);

        $result = $this->getAllContacts(
            locationId: $credentials['location_id'],
            tokenOverride: $credentials['token_override'],
            limit: (int) config('ghl.coach_database.page_limit', 100),
            maxPages: (int) config('ghl.coach_database.max_pages', 25),
        );

        $trackingFieldMap = $this->recruitingTrackingFieldMapForLocation($credentials['location_id'], $credentials['token_override']);

        $contacts = collect($result['contacts'] ?? [])
            ->filter(fn ($contact): bool => is_array($contact))
            ->map(fn (array $contact): array => $this->transformCoachContact($contact, $trackingFieldMap))
            ->filter(fn (array $coach): bool => filled($coach['id'] ?? $coach['email'] ?? null) && (
                filled($coach['school'] ?? $coach['school_name'] ?? $coach['company_name'] ?? $coach['business_name'] ?? null)
                || filled($coach['business_id'] ?? $coach['company_id'] ?? $coach['ghl_business_id'] ?? $coach['school_id'] ?? null)
            ))
            ->unique(fn (array $coach): string => (string) ($coach['id'] ?? $coach['email'] ?? md5(json_encode($coach))))
            ->values();

        return [
            'success' => (bool) ($result['success'] ?? false),
            'contacts' => $contacts->all(),
            'count' => $contacts->count(),
            'error' => $result['error'] ?? null,
        ];
    }



    public function getCoachContactsPageForUser(
        User $user,
        ?int $startAfter = null,
        ?string $startAfterId = null,
        int $limit = 100,
    ): array {
        $credentials = $this->credentialsForUser($user);

        $result = $this->getContactsPage(
            locationId: $credentials['location_id'],
            tokenOverride: $credentials['token_override'],
            startAfter: $startAfter,
            startAfterId: $startAfterId,
            limit: $limit,
        );

        $trackingFieldMap = $this->recruitingTrackingFieldMapForLocation($credentials['location_id'], $credentials['token_override']);

        $contacts = collect($result['contacts'] ?? [])
            ->filter(fn ($contact): bool => is_array($contact))
            ->map(fn (array $contact): array => $this->transformCoachContact($contact, $trackingFieldMap))
            ->filter(fn (array $coach): bool => filled($coach['id'] ?? $coach['email'] ?? null) && (
                filled($coach['school'] ?? $coach['school_name'] ?? $coach['company_name'] ?? $coach['business_name'] ?? null)
                || filled($coach['business_id'] ?? $coach['company_id'] ?? $coach['ghl_business_id'] ?? $coach['school_id'] ?? null)
            ))
            ->unique('id')
            ->values();

        return [
            'success' => (bool) ($result['success'] ?? false),
            'contacts' => $contacts->all(),
            'count' => $contacts->count(),
            'total' => $result['total'] ?? null,
            'next_start_after' => $result['next_start_after'] ?? null,
            'next_start_after_id' => $result['next_start_after_id'] ?? null,
            'has_more' => (bool) ($result['has_more'] ?? false),
            'error' => $result['error'] ?? null,
        ];
    }

    public function getContactsPage(
        ?string $locationId,
        ?string $tokenOverride,
        ?int $startAfter = null,
        ?string $startAfterId = null,
        int $limit = 100,
    ): array {
        $locationId = $locationId ?: config('ghl.location_id');
        $token = $this->tokenForLocation($locationId, $tokenOverride);
        $limit = min(max($limit, 1), (int) config('ghl.coach_database.contact_page_limit_max', 50));

        if (! $locationId || ! $token) {
            return [
                'success' => false,
                'contacts' => [],
                'count' => 0,
                'total' => null,
                'has_more' => false,
                'error' => 'Missing recruiting data connection.',
            ];
        }

        $query = [
            'locationId' => $locationId,
            'limit' => $limit,
        ];

        if (filled($startAfter)) {
            $query['startAfter'] = $startAfter;
        }

        if (filled($startAfterId)) {
            $query['startAfterId'] = $startAfterId;
        }

        try {
            $response = Http::withHeaders([
                    'Version' => config('ghl.contacts_search_version', 'v3'),
                ])
                ->connectTimeout((int) config('ghl.coach_database.http_connect_timeout', 5))
                ->timeout((int) config('ghl.coach_database.http_timeout', 12))
                ->retry(
                    (int) config('ghl.coach_database.http_retries', 1),
                    (int) config('ghl.coach_database.http_retry_sleep_ms', 350),
                    throw: false,
                )
                ->withToken($token)
                ->acceptJson()
                ->get("{$this->baseUrl}/contacts/", $query);
        } catch (\Throwable $exception) {
            Log::warning('Recruiting contacts page request timed out or failed before response.', [
                'location_id' => $locationId,
                'query' => $query,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'contacts' => [],
                'count' => 0,
                'total' => null,
                'next_start_after' => $startAfter,
                'next_start_after_id' => $startAfterId,
                'has_more' => true,
                'temporary_failure' => true,
                'error' => 'Recruiting Center contacts timed out. Kept existing cached data; try again shortly.',
            ];
        }

        $data = $response->json() ?? [];

        if ($response->failed()) {
            Log::error('Recruiting contacts page request failed.', [
                'location_id' => $locationId,
                'status' => $response->status(),
                'query' => $query,
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'contacts' => [],
                'count' => 0,
                'total' => null,
                'has_more' => false,
                'error' => 'Unable to load recruiting contacts.',
            ];
        }

        $contacts = $this->extractContactsFromResponse($data);
        $meta = $data['meta'] ?? [];
        $lastContact = collect($contacts)->last() ?: [];
        $lastContactStartAfter = is_array($lastContact['startAfter'] ?? null)
            ? $lastContact['startAfter']
            : [];

        $nextStartAfter = $meta['startAfter']
            ?? $data['startAfter']
            ?? $lastContactStartAfter[0]
            ?? null;

        $nextStartAfterId = $meta['startAfterId']
            ?? $data['startAfterId']
            ?? $lastContactStartAfter[1]
            ?? ($lastContact['id'] ?? null);

        $hasMore = filled($meta['nextPageUrl'] ?? null)
            || (
                count($contacts) >= $limit
                && filled($nextStartAfter)
                && filled($nextStartAfterId)
                && ((string) $nextStartAfter !== (string) $startAfter || (string) $nextStartAfterId !== (string) $startAfterId)
            );

        return [
            'success' => true,
            'contacts' => $contacts,
            'count' => count($contacts),
            'total' => $meta['total'] ?? null,
            'next_start_after' => $nextStartAfter,
            'next_start_after_id' => $nextStartAfterId,
            'has_more' => $hasMore,
        ];
    }

    public function getAllContacts(
        ?string $locationId,
        ?string $tokenOverride,
        int $limit = 100,
        int $maxPages = 25,
    ): array {
        $locationId = $locationId ?: config('ghl.location_id');
        $token = $this->tokenForLocation($locationId, $tokenOverride);
        $limit = min(max($limit, 1), 100);

        if (! $locationId || ! $token) {
            return [
                'success' => false,
                'contacts' => [],
                'count' => 0,
                'error' => 'Missing recruiting data connection.',
                'debug' => [[
                    'stage' => 'missing_location_or_token',
                    'location_id' => $locationId,
                    'has_token' => filled($token),
                ]],
            ];
        }

        $contacts = collect();
        $debug = [];
        $page = 1;
        $startAfter = null;
        $startAfterId = null;
        $total = null;

        do {
            $query = [
                'locationId' => $locationId,
                'limit' => $limit,
            ];

            if (filled($startAfter)) {
                $query['startAfter'] = $startAfter;
            }

            if (filled($startAfterId)) {
                $query['startAfterId'] = $startAfterId;
            }

            $response = Http::withHeaders([
                    'Version' => config('ghl.version', '2023-02-21'),
                ])
                ->timeout((int) config('ghl.timeout', 20))
                ->withToken($token)
                ->acceptJson()
                ->get("{$this->baseUrl}/contacts/", $query);

            $data = $response->json() ?? [];

            if ($response->failed()) {
                Log::error('Recruiting Center get contacts request failed.', [
                    'location_id' => $locationId,
                    'status' => $response->status(),
                    'query' => $query,
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'contacts' => $contacts->values()->all(),
                    'count' => $contacts->count(),
                    'error' => 'Recruiting Center get contacts request failed.',
                    'status' => $response->status(),
                    'debug' => array_merge($debug, [[
                        'stage' => 'contacts_request_failed',
                        'page' => $page,
                        'endpoint' => "{$this->baseUrl}/contacts/",
                        'method' => 'GET',
                        'query' => $query,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]]),
                ];
            }

            $pageContacts = $this->extractContactsFromResponse($data);
            $contacts = $contacts->merge($pageContacts);

            $meta = $data['meta'] ?? [];
            $total = $meta['total'] ?? $data['total'] ?? $total;

            $lastContact = collect($pageContacts)->last();
            $lastContactStartAfter = is_array($lastContact['startAfter'] ?? null)
                ? $lastContact['startAfter']
                : null;

            $nextStartAfter = $meta['startAfter']
                ?? $data['startAfter']
                ?? ($lastContactStartAfter[0] ?? null)
                ?? $lastContact['dateAdded']
                ?? null;

            $nextStartAfterId = $meta['startAfterId']
                ?? $data['startAfterId']
                ?? ($lastContactStartAfter[1] ?? null)
                ?? $lastContact['id']
                ?? null;

            $debug[] = [
                'stage' => 'contacts_page_loaded',
                'page' => $page,
                'endpoint' => "{$this->baseUrl}/contacts/",
                'method' => 'GET',
                'version' => config('ghl.version', '2023-02-21'),
                'status' => $response->status(),
                'query' => $query,
                'response_keys' => array_keys($data),
                'meta' => $meta,
                'page_contacts_count' => count($pageContacts),
                'total_loaded' => $contacts->count(),
                'reported_total' => $total,
                'next_start_after' => $nextStartAfter,
                'next_start_after_id' => $nextStartAfterId,
            ];

            if (empty($pageContacts)) {
                break;
            }

            if (! $nextStartAfter || ! $nextStartAfterId) {
                break;
            }

            if ((string) $nextStartAfter === (string) $startAfter && (string) $nextStartAfterId === (string) $startAfterId) {
                break;
            }

            $startAfter = $nextStartAfter;
            $startAfterId = $nextStartAfterId;
            $page++;
        } while (
            count($pageContacts) >= $limit
            && $page <= $maxPages
            && (
                is_null($total)
                || $contacts->count() < (int) $total
            )
        );

        $contacts = $contacts
            ->filter(fn ($contact): bool => is_array($contact))
            ->unique('id')
            ->values();

        return [
            'success' => true,
            'contacts' => $contacts->all(),
            'count' => $contacts->count(),
            'total' => $total,
            'debug' => $debug,
        ];
    }

    public function getBusinessesForLocation(
        ?string $locationId,
        ?string $tokenOverride,
        int $limit = 100,
        int $maxPages = 25,
    ): array {
        $locationId = $locationId ?: config('ghl.location_id');
        $token = $this->tokenForLocation($locationId, $tokenOverride);
        $limit = min(max($limit, 1), 100);

        if (! $locationId || ! $token) {
            return [
                'success' => false,
                'businesses' => [],
                'count' => 0,
                'error' => 'Missing recruiting data connection.',
                'debug' => [[
                    'stage' => 'missing_location_or_token',
                    'location_id' => $locationId,
                    'has_token' => filled($token),
                ]],
            ];
        }

        $businesses = collect();
        $debug = [];
        $page = 1;
        $skip = 0;

        do {
            $query = [
                'locationId' => $locationId,
                'limit' => $limit,
                'skip' => $skip,
            ];

            $response = Http::withHeaders([
                    'Version' => 'v3',
                ])
                ->timeout((int) config('ghl.timeout', 20))
                ->withToken($token)
                ->acceptJson()
                ->get("{$this->baseUrl}/businesses/", $query);

            $data = $response->json() ?? [];

            if ($response->failed()) {
                Log::error('Recruiting Center businesses request failed.', [
                    'location_id' => $locationId,
                    'status' => $response->status(),
                    'query' => $query,
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'businesses' => $businesses->values()->all(),
                    'count' => $businesses->count(),
                    'error' => 'Recruiting Center businesses request failed.',
                    'status' => $response->status(),
                    'debug' => array_merge($debug, [[
                        'stage' => 'businesses_request_failed',
                        'page' => $page,
                        'query' => $query,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]]),
                ];
            }

            $pageBusinesses = $this->extractBusinessesFromResponse($data);
            $businesses = $businesses->merge($pageBusinesses);

            $debug[] = [
                'stage' => 'businesses_page_loaded',
                'page' => $page,
                'skip' => $skip,
                'endpoint' => "{$this->baseUrl}/businesses/",
                'method' => 'GET',
                'version' => 'v3',
                'status' => $response->status(),
                'query' => $query,
                'response_keys' => array_keys($data),
                'page_businesses_count' => count($pageBusinesses),
                'total_loaded' => $businesses->count(),
            ];

            $skip += $limit;
            $page++;
        } while (
            count($pageBusinesses) >= $limit
            && $page <= $maxPages
        );

        $businesses = $businesses
            ->filter(fn ($business): bool => is_array($business))
            ->unique(fn (array $business): ?string => $business['id'] ?? $business['_id'] ?? null)
            ->values();

        return [
            'success' => true,
            'businesses' => $businesses->all(),
            'count' => $businesses->count(),
            'debug' => $debug,
        ];
    }

    public function getContactsByBusinessId(
        string $businessId,
        ?string $locationId,
        ?string $tokenOverride,
        int $limit = 100,
        int $maxPages = 25,
    ): array {
        $locationId = $locationId ?: config('ghl.location_id');
        $token = $this->tokenForLocation($locationId, $tokenOverride);
        $limit = min(max($limit, 1), 100);

        if (! $businessId || ! $locationId || ! $token) {
            return [
                'success' => false,
                'contacts' => [],
                'count' => 0,
                'error' => 'Missing Business ID, Recruiting Center Location ID, or API token.',
            ];
        }

        $contacts = collect();
        $debug = [];
        $page = 1;
        $skip = 0;
        $startAfter = null;

        do {
            $query = [
                'locationId' => $locationId,
                'limit' => $limit,
                'skip' => $skip,
            ];

            if (filled($startAfter)) {
                $query['startAfter'] = $startAfter;
            }

            try {
                $response = Http::withHeaders([
                        'Version' => config('ghl.version', '2023-02-21'),
                    ])
                    ->connectTimeout((int) config('ghl.connect_timeout', 5))
                    ->timeout((int) config('ghl.timeout', 12))
                    ->retry((int) config('ghl.retries', 1), (int) config('ghl.retry_sleep_ms', 250), throw: false)
                    ->withToken($token)
                    ->acceptJson()
                    ->get("{$this->baseUrl}/contacts/business/{$businessId}", $query);
            } catch (\Illuminate\Http\Client\ConnectionException $exception) {
                Log::warning('Recruiting Center contacts by business request timed out.', [
                    'business_id' => $businessId,
                    'location_id' => $locationId,
                    'query' => $query,
                    'error' => $exception->getMessage(),
                ]);

                return [
                    'success' => false,
                    'contacts' => $contacts->values()->all(),
                    'count' => $contacts->count(),
                    'error' => 'Recruiting Center timed out while loading coaches for this school.',
                    'timed_out' => true,
                    'debug' => array_merge($debug, [[
                        'stage' => 'business_contacts_timeout',
                        'business_id' => $businessId,
                        'query' => $query,
                    ]]),
                ];
            }

            $data = $response->json() ?? [];

            if ($response->failed()) {
                Log::error('Recruiting Center contacts by business request failed.', [
                    'business_id' => $businessId,
                    'location_id' => $locationId,
                    'status' => $response->status(),
                    'query' => $query,
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'contacts' => $contacts->values()->all(),
                    'count' => $contacts->count(),
                    'error' => 'Recruiting Center contacts by business request failed.',
                    'status' => $response->status(),
                    'debug' => array_merge($debug, [[
                        'stage' => 'business_contacts_request_failed',
                        'business_id' => $businessId,
                        'query' => $query,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]]),
                ];
            }

            $pageContacts = $this->extractContactsFromResponse($data);
            $contacts = $contacts->merge($pageContacts);

            $meta = $data['meta'] ?? [];
            $nextStartAfter = $meta['startAfter']
                ?? $data['startAfter']
                ?? null;

            $debug[] = [
                'stage' => 'business_contacts_page_loaded',
                'business_id' => $businessId,
                'page' => $page,
                'skip' => $skip,
                'endpoint' => "{$this->baseUrl}/contacts/business/{$businessId}",
                'method' => 'GET',
                'version' => config('ghl.version', '2023-02-21'),
                'status' => $response->status(),
                'query' => $query,
                'response_keys' => array_keys($data),
                'page_contacts_count' => count($pageContacts),
                'total_loaded' => $contacts->count(),
                'next_start_after' => $nextStartAfter,
            ];

            if ($nextStartAfter && $nextStartAfter === $startAfter) {
                break;
            }

            $startAfter = $nextStartAfter;
            $skip += $limit;
            $page++;
        } while (
            count($pageContacts) >= $limit
            && $page <= $maxPages
        );

        $contacts = $contacts
            ->filter(fn ($contact): bool => is_array($contact))
            ->unique('id')
            ->values();

        return [
            'success' => true,
            'contacts' => $contacts->all(),
            'count' => $contacts->count(),
            'debug' => $debug,
        ];
    }

    public function searchContacts(
        ?string $locationId,
        ?string $tokenOverride,
        array $filters = [],
        int $page = 1,
        int $pageLimit = 100,
    ): array {
        $locationId = $locationId ?: config('ghl.location_id');
        $token = $this->tokenForLocation($locationId, $tokenOverride);

        if (! $locationId || ! $token) {
            return [
                'success' => false,
                'contacts' => [],
                'total' => 0,
                'error' => 'Missing recruiting data connection.',
                'debug' => [
                    'location_id' => $locationId,
                    'has_token' => filled($token),
                ],
            ];
        }

        $payload = [
            'locationId' => $locationId,
            'page' => $page,
            'pageLimit' => $pageLimit,
        ];

        if (! empty($filters)) {
            $payload['filters'] = $filters;
        }

        try {
            $response = Http::withHeaders([
                    'Version' => 'v3',
                    'Content-Type' => 'application/json',
                ])
                ->connectTimeout((int) config('coach-database-sync.http.connect_timeout', 5))
                ->timeout((int) config('coach-database-sync.http.request_timeout', config('ghl.timeout', 15)))
                ->withToken($token)
                ->acceptJson()
                ->asJson()
                ->post("{$this->baseUrl}/contacts/search", $payload);
        } catch (\Throwable $exception) {
            Log::warning('Recruiting Center contacts search connection failed safely.', [
                'location_id' => $locationId,
                'payload' => $payload,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'contacts' => [],
                'total' => 0,
                'error' => 'Recruiting Center contacts search timed out or could not connect. Cached Recruiting Center data was preserved.',
                'connection_error' => true,
                'debug' => [
                    'payload' => $payload,
                    'location_id' => $locationId,
                    'exception' => $exception->getMessage(),
                ],
            ];
        }

        $data = $response->json() ?? [];

        if ($response->failed()) {
            Log::error('Recruiting Center contacts search failed.', [
                'location_id' => $locationId,
                'status' => $response->status(),
                'payload' => $payload,
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'contacts' => [],
                'total' => 0,
                'error' => 'Recruiting Center contacts search failed.',
                'status' => $response->status(),
                'debug' => [
                    'payload' => $payload,
                    'body' => $response->body(),
                ],
            ];
        }

        return [
            'success' => true,
            'contacts' => $this->extractContactsFromResponse($data),
            'total' => (int) ($data['total'] ?? 0),
            'raw' => $data,
            'debug' => [
                'payload' => $payload,
                'location_id' => $locationId,
                'version' => 'v3',
                'response_keys' => array_keys($data),
                'total' => $data['total'] ?? null,
            ],
        ];
    }

    public function searchAllContacts(
        ?string $locationId,
        ?string $tokenOverride,
        array $filters = [],
        int $pageLimit = 100,
        int $maxPages = 25,
    ): array {
        $allContacts = collect();
        $page = 1;
        $total = null;
        $debug = [];

        do {
            $result = $this->searchContacts(
                locationId: $locationId,
                tokenOverride: $tokenOverride,
                filters: $filters,
                page: $page,
                pageLimit: $pageLimit,
            );

            $debug[] = [
                'page' => $page,
                'success' => (bool) ($result['success'] ?? false),
                'count' => count($result['contacts'] ?? []),
                'total' => $result['total'] ?? null,
                'payload' => $result['debug']['payload'] ?? null,
            ];

            if (! ($result['success'] ?? false)) {
                return [
                    'success' => false,
                    'contacts' => $allContacts->values()->all(),
                    'total' => $allContacts->count(),
                    'error' => $result['error'] ?? 'Recruiting Center contacts search failed.',
                    'debug' => array_merge($debug, [$result['debug'] ?? []]),
                ];
            }

            $contacts = $result['contacts'] ?? [];
            $allContacts = $allContacts->merge($contacts);
            $total = $result['total'] ?? $total;
            $page++;
        } while (
            count($contacts) >= $pageLimit
            && $page <= $maxPages
            && (
                is_null($total)
                || $allContacts->count() < $total
            )
        );

        return [
            'success' => true,
            'contacts' => $allContacts
                ->filter(fn ($contact): bool => is_array($contact))
                ->unique('id')
                ->values()
                ->all(),
            'total' => $allContacts->count(),
            'debug' => $debug,
        ];
    }

    public function searchAllContactsByTag(
        ?string $locationId,
        ?string $tokenOverride,
        string $tag,
        int $pageLimit = 100,
        int $maxPages = 20,
    ): array {
        $tag = trim($tag);

        if ($tag === '') {
            return [
                'success' => false,
                'contacts' => [],
                'total' => 0,
                'error' => 'Missing tag.',
                'debug' => [],
            ];
        }

        // HighLevel contacts/search v3 uses the smart-list filter shape.
        // This must match the working request shape:
        // filters: [{ field: 'tags', operator: 'contains', value: '<tag>' }].
        // The tag written by save/favorite/list actions is the same exact value used here.
        $filterSets = [
            [[ 'field' => 'tags', 'operator' => 'contains', 'value' => $tag ]],
        ];

        $best = [
            'success' => false,
            'contacts' => [],
            'total' => 0,
            'error' => null,
            'debug' => [],
        ];

        foreach ($filterSets as $filters) {
            $result = $this->searchAllContacts(
                locationId: $locationId,
                tokenOverride: $tokenOverride,
                filters: $filters,
                pageLimit: $pageLimit,
                maxPages: $maxPages,
            );

            // Trust the API-side tag filter. Some search rows do not include
            // the tags array, so local tag filtering would incorrectly hide valid rows.
            $matching = collect($result['contacts'] ?? [])
                ->filter(fn ($contact): bool => is_array($contact))
                ->map(fn (array $contact): array => $this->ensureContactCarriesTag($contact, $tag))
                ->unique(fn (array $contact): string => (string) ($contact['id'] ?? $contact['_id'] ?? $contact['contactId'] ?? spl_object_id((object) $contact)))
                ->values()
                ->all();

            $best['debug'][] = [
                'filters' => $filters,
                'success' => (bool) ($result['success'] ?? false),
                'returned' => count($result['contacts'] ?? []),
                'matching' => count($matching),
                'error' => $result['error'] ?? null,
            ];

            if (($result['success'] ?? false) && ! empty($matching)) {
                return [
                    'success' => true,
                    'contacts' => $matching,
                    'total' => count($matching),
                    'error' => null,
                    'debug' => $best['debug'],
                ];
            }

            if (($result['success'] ?? false) && empty($best['contacts'])) {
                $best['success'] = true;
                $best['contacts'] = $matching;
                $best['total'] = count($matching);
            }

            if (! ($result['success'] ?? false) && ! $best['error']) {
                $best['error'] = $result['error'] ?? 'Unable to search contacts by tag.';
            }
        }

        return $best;
    }

    public function addTagsToContactForUser(User $user, string $contactId, array $tags): array
    {
        return $this->updateContactTagsForUser($user, [$contactId], $tags, 'add');
    }

    public function removeTagsFromContactForUser(User $user, string $contactId, array $tags): array
    {
        return $this->updateContactTagsForUser($user, [$contactId], $tags, 'remove');
    }

    public function updateContactTagsForUser(User $user, array $contactIds, array $tags, string $type = 'add'): array
    {
        $credentials = $this->credentialsForUser($user);

        return $this->updateContactTags(
            locationId: $credentials['location_id'],
            tokenOverride: $credentials['token_override'],
            contactIds: $contactIds,
            tags: $tags,
            type: $type,
        );
    }

    public function updateContactTags(
        ?string $locationId,
        ?string $tokenOverride,
        array $contactIds,
        array $tags,
        string $type = 'add',
    ): array {
        $locationId = $locationId ?: config('ghl.location_id');
        $token = $this->tokenForLocation($locationId, $tokenOverride);
        $type = strtolower($type);
        $requestedContactIds = array_values(array_unique(array_filter(array_map('strval', $contactIds))));
        $tags = array_values(array_unique(array_filter(array_map('strval', $tags))));

        if (! in_array($type, ['add', 'remove'], true)) {
            return ['success' => false, 'error' => 'Invalid tag update type.'];
        }

        if (! $locationId || ! $token) {
            return ['success' => false, 'error' => 'Missing recruiting data connection.'];
        }

        if (empty($requestedContactIds) || empty($tags)) {
            return ['success' => false, 'error' => 'Missing contacts or tags.'];
        }

        // Official bulk endpoint expects "contacts", not "contactIds".
        $payloads = [[
            'locationId' => $locationId,
            'contacts' => $requestedContactIds,
            'tags' => $tags,
            'removeAllTags' => false,
        ], [
            // Compatibility fallback for older/internal shapes.
            'locationId' => $locationId,
            'contactIds' => $requestedContactIds,
            'tags' => $tags,
            'removeAllTags' => false,
        ]];

        $lastResponse = null;
        $lastData = [];

        foreach ($payloads as $payload) {
            $response = Http::withHeaders([
                    'Version' => config('ghl.tag_update_version', config('ghl.version', '2023-02-21')),
                ])
                ->timeout((int) config('ghl.timeout', 20))
                ->withToken($token)
                ->acceptJson()
                ->asJson()
                ->post("{$this->baseUrl}/contacts/bulk/tags/update/{$type}", $payload);

            $data = $response->json() ?? [];
            $lastResponse = $response;
            $lastData = $data;

            if ($response->successful()) {
                $failedContactIds = $this->extractFailedContactIds($data, $requestedContactIds);
                $staleContactIds = $this->extractStaleContactIds($data, $requestedContactIds);

                return [
                    'success' => true,
                    'requested_contact_ids' => $requestedContactIds,
                    'updated_contact_ids' => array_values(array_diff($requestedContactIds, $failedContactIds)),
                    'failed_contact_ids' => $failedContactIds,
                    'stale_contact_ids' => $staleContactIds,
                    'raw' => $data,
                ];
            }
        }

        // Single-contact fallback uses the v3 tags endpoint.
        if (count($requestedContactIds) <= (int) config('ghl.coach_database.single_tag_fallback_limit', 25)) {
            $single = $this->updateContactTagsIndividually($locationId, $token, $requestedContactIds, $tags, $type);
            if ($single['attempted'] ?? false) {
                return $single;
            }
        }

        Log::error('Recruiting contact tag update failed.', [
            'location_id' => $locationId,
            'type' => $type,
            'status' => $lastResponse?->status(),
            'body' => $lastResponse?->body(),
        ]);

        return [
            'success' => false,
            'error' => 'Unable to update recruiting list. No contacts were removed from cache because the remote update did not confirm they were unavailable.',
            'status' => $lastResponse?->status(),
            'requested_contact_ids' => $requestedContactIds,
            'updated_contact_ids' => [],
            'failed_contact_ids' => [],
            'stale_contact_ids' => [],
            'raw' => $lastData,
        ];
    }

    private function updateContactTagsIndividually(string $locationId, string $token, array $contactIds, array $tags, string $type): array
    {
        $updated = [];
        $failed = [];
        $stale = [];
        $responses = [];

        foreach ($contactIds as $contactId) {
            $request = Http::withHeaders(['Version' => 'v3'])
                ->timeout((int) config('ghl.timeout', 20))
                ->withToken($token)
                ->acceptJson()
                ->asJson();

            $url = "{$this->baseUrl}/contacts/{$contactId}/tags";
            $response = $type === 'remove'
                ? $request->send('DELETE', $url, ['json' => ['tags' => $tags]])
                : $request->post($url, ['tags' => $tags]);

            $body = $response->json() ?? [];
            $responses[] = ['contactId' => $contactId, 'status' => $response->status(), 'body' => $body ?: $response->body()];

            if ($response->successful()) {
                $updated[] = $contactId;
                continue;
            }

            $failed[] = $contactId;
            $bodyText = strtolower(json_encode($body) ?: $response->body());
            if ($response->status() === 404 || str_contains($bodyText, 'deleted') || str_contains($bodyText, 'not found') || str_contains($bodyText, 'does not belong')) {
                $stale[] = $contactId;
            }
        }

        return [
            'attempted' => true,
            'success' => count($updated) > 0 && count($failed) === 0,
            'partial_success' => count($updated) > 0 && count($failed) > 0,
            'error' => count($failed) ? 'Some recruiting records could not be updated.' : null,
            'requested_contact_ids' => $contactIds,
            'updated_contact_ids' => array_values(array_unique($updated)),
            'failed_contact_ids' => array_values(array_unique($failed)),
            'stale_contact_ids' => array_values(array_unique($stale)),
            'raw' => ['responses' => $responses],
        ];
    }

    private function extractFailedContactIds(array $data, array $requestedContactIds): array
    {
        $failed = [];
        $responses = $data['responses'] ?? $data['results'] ?? $data['data']['responses'] ?? [];

        if (is_array($responses)) {
            foreach ($responses as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $contactId = $item['contactId'] ?? $item['contact_id'] ?? $item['id'] ?? null;
                $type = strtolower((string) ($item['type'] ?? $item['status'] ?? ''));
                $message = strtolower((string) ($item['message'] ?? $item['error'] ?? ''));
                if ($contactId && (str_contains($type, 'error') || str_contains($message, 'error') || str_contains($message, 'deleted') || str_contains($message, 'invalid') || str_contains($message, 'does not belong'))) {
                    $failed[] = (string) $contactId;
                }
            }
        }

        return collect($failed)->intersect($requestedContactIds)->unique()->values()->all();
    }

    private function extractStaleContactIds(array $data, array $requestedContactIds): array
    {
        $stale = [];
        $responses = $data['responses'] ?? $data['results'] ?? $data['data']['responses'] ?? [];

        if (is_array($responses)) {
            foreach ($responses as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $contactId = $item['contactId'] ?? $item['contact_id'] ?? $item['id'] ?? null;
                $message = strtolower((string) ($item['message'] ?? $item['error'] ?? ''));
                if ($contactId && (str_contains($message, 'deleted') || str_contains($message, 'not found') || str_contains($message, 'does not belong'))) {
                    $stale[] = (string) $contactId;
                }
            }
        }

        return collect($stale)->intersect($requestedContactIds)->unique()->values()->all();
    }

    public function getCalendars(?string $locationId = null, ?string $tokenOverride = null): array
    {
        $locationId = $locationId ?: config('ghl.location_id');

        if (! $locationId) {
            return [];
        }

        $token = $this->tokenForLocation($locationId, $tokenOverride);

        if (! $token) {
            Log::warning('Recruiting Center calendar list skipped. Missing token.', [
                'location_id' => $locationId,
                'has_token_override' => filled($tokenOverride),
            ]);

            return [];
        }

        $response = Http::withHeaders(['Version' => '2021-04-15'])
            ->timeout((int) config('ghl.timeout', 20))
            ->withToken($token)
            ->acceptJson()
            ->get("{$this->baseUrl}/calendars/", ['locationId' => $locationId]);

        if ($response->failed()) {
            Log::error('Recruiting Center calendar list failed.', [
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
                $id = $calendar['id'] ?? $calendar['_id'] ?? $calendar['calendarId'] ?? null;

                if (! $id) {
                    return null;
                }

                return [
                    'id' => (string) $id,
                    'name' => (string) ($calendar['name'] ?? $calendar['title'] ?? 'Recruiting Center Calendar'),
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

        $response = Http::withHeaders(['Version' => '2021-07-28'])
            ->timeout((int) config('ghl.timeout', 20))
            ->withToken($token)
            ->acceptJson()
            ->post("{$this->baseUrl}/contacts/upsert", $payload);

        if ($response->failed()) {
            Log::error('Recruiting Center contact upsert failed.', [
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

        $response = Http::withHeaders(['Version' => '2021-07-28'])
            ->timeout((int) config('ghl.timeout', 20))
            ->withToken($token)
            ->acceptJson()
            ->post("{$this->baseUrl}/contacts/{$contactId}/notes", ['body' => $body]);

        if ($response->failed()) {
            Log::error('Recruiting Center contact note failed.', [
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
            ->withHeaders(['Version' => config('ghl.version', '2023-02-21')])
            ->timeout((int) config('ghl.timeout', 20))
            ->withToken($agencyToken)
            ->acceptJson()
            ->post("{$this->baseUrl}/oauth/locationToken", [
                'companyId' => $companyId,
                'locationId' => $locationId,
            ]);

        if ($response->failed()) {
            Log::error('Recruiting Center location token request failed.', [
                'location_id' => $locationId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        return $response->json('access_token');
    }

    public function syncProfileCompletion(User $user, int $completion): bool
    {
        $contactId = $this->resolveContactId($user);

        if (! $contactId) {
            Log::warning('Recruiting Center profile completion sync skipped. Contact not found.', [
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
            Log::warning('Recruiting Center site published sync skipped. Contact not found.', [
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

    private function credentialsForUser(User $user): array
    {
        return [
            'location_id' => filled($user->ghl_location_id ?? null)
                ? trim((string) $user->ghl_location_id)
                : config('ghl.location_id'),
            'token_override' => filled($user->ghl_api_key ?? null)
                ? trim((string) $user->ghl_api_key)
                : null,
        ];
    }

    private function extractBusinessesFromResponse(array $data): array
    {
        $businesses = $data['businesses']
            ?? $data['business']
            ?? $data['data']
            ?? [];

        if (isset($businesses['id']) || isset($businesses['_id'])) {
            return [$businesses];
        }

        if (! is_array($businesses)) {
            return [];
        }

        return array_values(array_filter($businesses, fn ($business): bool => is_array($business)));
    }

    private function extractContactsFromResponse(array $data): array
    {
        $candidates = [
            $data['contacts']['contacts'] ?? null,
            $data['contacts']['data'] ?? null,
            $data['contacts']['items'] ?? null,
            $data['data']['contacts'] ?? null,
            $data['data']['data'] ?? null,
            $data['data']['items'] ?? null,
            $data['data']['results'] ?? null,
            $data['items']['contacts'] ?? null,
            $data['results']['contacts'] ?? null,
            $data['contacts'] ?? null,
            $data['contact'] ?? null,
            $data['data'] ?? null,
            $data['items'] ?? null,
            $data['results'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $items = $this->normalizeResponseList($candidate, ['id', '_id', 'contactId', 'email', 'firstName', 'lastName', 'phone']);
            if (! empty($items)) {
                return $items;
            }
        }

        return [];
    }

    private function extractConversationsFromResponse(array $data): array
    {
        $candidates = [
            $data['conversations']['conversations'] ?? null,
            $data['conversations']['data'] ?? null,
            $data['conversations']['items'] ?? null,
            $data['data']['conversations'] ?? null,
            $data['data']['data'] ?? null,
            $data['data']['items'] ?? null,
            $data['data']['results'] ?? null,
            $data['items']['conversations'] ?? null,
            $data['results']['conversations'] ?? null,
            $data['conversations'] ?? null,
            $data['conversation'] ?? null,
            $data['data'] ?? null,
            $data['items'] ?? null,
            $data['results'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $items = $this->normalizeResponseList($candidate, ['id', '_id', 'conversationId', 'contactId', 'lastMessageDate', 'lastMessageAt']);
            if (! empty($items)) {
                return $items;
            }
        }

        return [];
    }

    private function extractConversationMessagesFromResponse(array $data): array
    {
        $candidates = [
            $data['messages']['messages'] ?? null,
            $data['messages']['data'] ?? null,
            $data['messages']['items'] ?? null,
            $data['data']['messages'] ?? null,
            $data['data']['data'] ?? null,
            $data['data']['items'] ?? null,
            $data['data']['results'] ?? null,
            $data['items']['messages'] ?? null,
            $data['results']['messages'] ?? null,
            $data['messages'] ?? null,
            $data['message'] ?? null,
            $data['data'] ?? null,
            $data['items'] ?? null,
            $data['results'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $items = $this->normalizeResponseList($candidate, ['id', '_id', 'messageId', 'body', 'message', 'html', 'text', 'type', 'messageType']);
            if (! empty($items)) {
                return $items;
            }
        }

        return [];
    }

    private function extractTemplatesFromResponse(array $data): array
    {
        $items = [];
        $this->collectTemplateEntities($data, $items);

        return collect($items)
            ->filter(fn ($item): bool => is_array($item))
            ->unique(function (array $item): string {
                $id = (string) ($item['id'] ?? $item['_id'] ?? $item['templateId'] ?? $item['template_id'] ?? data_get($item, 'data.id') ?? data_get($item, 'template.id') ?? '');
                $name = (string) ($item['name'] ?? $item['title'] ?? $item['templateName'] ?? data_get($item, 'data.name') ?? data_get($item, 'template.name') ?? '');

                return $id !== '' ? 'id:' . $id : 'name:' . strtolower($name);
            })
            ->values()
            ->all();
    }

    private function collectTemplateEntities(mixed $value, array &$items, int $depth = 0): void
    {
        if ($depth > 10 || ! is_array($value)) {
            return;
        }

        if ($this->arrayLooksLikeTemplateEntity($value)) {
            $items[] = $value;
        }

        foreach ($value as $child) {
            if (is_array($child)) {
                $this->collectTemplateEntities($child, $items, $depth + 1);
            }
        }
    }

    private function arrayLooksLikeTemplateEntity(array $value): bool
    {
        $id = $value['id'] ?? $value['_id'] ?? $value['templateId'] ?? $value['template_id'] ?? data_get($value, 'data.id') ?? data_get($value, 'template.id') ?? null;
        $name = $value['name'] ?? $value['title'] ?? $value['templateName'] ?? data_get($value, 'data.name') ?? data_get($value, 'template.name') ?? null;

        if (! filled($id) && ! filled($name)) {
            return false;
        }

        if (array_key_exists('templates', $value) || array_key_exists('items', $value) || array_key_exists('results', $value)) {
            return filled($name) || filled($id) && (array_key_exists('type', $value) || array_key_exists('templateType', $value));
        }

        foreach (['type', 'templateType', 'resourceType', 'editorType', 'editor', 'subject', 'subjectLine', 'html', 'body', 'updatedAt', 'createdAt', 'isFolder', 'folderId', 'templateData', 'builderData', 'design', 'editorContentUrl'] as $key) {
            if (array_key_exists($key, $value)) {
                return true;
            }
        }

        return filled($name) && filled($id);
    }

    private function normalizeResponseList(mixed $candidate, array $entityKeys): array
    {
        if (! is_array($candidate)) {
            return [];
        }

        if ($this->arrayLooksLikeEntity($candidate, $entityKeys)) {
            return [$candidate];
        }

        if (! array_is_list($candidate)) {
            return [];
        }

        return array_values(array_filter($candidate, function ($item) use ($entityKeys): bool {
            return is_array($item) && $this->arrayLooksLikeEntity($item, $entityKeys);
        }));
    }

    private function arrayLooksLikeEntity(array $value, array $entityKeys): bool
    {
        foreach ($entityKeys as $key) {
            if (array_key_exists($key, $value) && filled($value[$key])) {
                return true;
            }
        }

        return false;
    }


    private function ensureContactCarriesTag(array $contact, string $tag): array
    {
        $tag = trim($tag);

        if ($tag === '') {
            return $contact;
        }

        $tags = $contact['tags']
            ?? $contact['Tags']
            ?? $contact['contactTags']
            ?? $contact['contact_tags']
            ?? [];

        if (is_string($tags)) {
            $tags = preg_split('/[,|;]/', $tags) ?: [];
        }

        if (! is_array($tags)) {
            $tags = [];
        }

        $hasTag = collect($tags)->contains(function ($item) use ($tag): bool {
            $value = is_array($item)
                ? ($item['name'] ?? $item['tag'] ?? $item['value'] ?? $item['label'] ?? $item['id'] ?? null)
                : $item;

            return strtolower(trim((string) $value)) === strtolower($tag);
        });

        if (! $hasTag) {
            $tags[] = $tag;
        }

        $contact['tags'] = array_values($tags);

        return $contact;
    }

    private function contactHasTag(array $contact, ?string $tag): bool
    {
        $needle = strtolower(trim((string) $tag));

        if ($needle === '') {
            return false;
        }

        $tags = $contact['tags']
            ?? $contact['Tags']
            ?? $contact['contactTags']
            ?? $contact['contact_tags']
            ?? [];

        if (is_string($tags)) {
            $tags = preg_split('/[,|;]/', $tags) ?: [];
        }

        if (! is_array($tags)) {
            return false;
        }

        foreach ($tags as $item) {
            if (is_array($item)) {
                $value = $item['name']
                    ?? $item['tag']
                    ?? $item['value']
                    ?? $item['label']
                    ?? $item['id']
                    ?? null;
            } else {
                $value = $item;
            }

            if (strtolower(trim((string) $value)) === $needle) {
                return true;
            }
        }

        return false;
    }


    private function contactHasAnyTag(array $contact, array $tags): bool
    {
        foreach ($tags as $tag) {
            if ($this->contactHasTag($contact, $tag)) {
                return true;
            }
        }

        return false;
    }

    private function transformEmailTemplate(array $item): array
    {
        $body = $this->extractTemplateHtmlFromKnownFields($item);

        $subject = $this->extractFirstTemplateString($item, [
            'subjectLine', 'subject', 'emailSubject', 'campaignSubject', 'title', 'data.subjectLine', 'data.subject', 'data.campaignSubject', 'email.subjectLine', 'email.subject',
            'settings.subjectLine', 'settings.subject', 'template.subjectLine', 'template.subject',
        ]);

        $previewText = $this->extractFirstTemplateString($item, [
            'previewText', 'preview', 'preview_text', 'data.previewText', 'data.preview', 'email.previewText', 'settings.previewText',
        ]);

        $fromName = $this->extractFirstTemplateString($item, [
            'fromName', 'senderName', 'data.fromName', 'data.senderName', 'email.fromName', 'settings.fromName',
        ]);

        $fromEmail = $this->extractFirstTemplateString($item, [
            'fromEmail', 'senderEmail', 'data.fromEmail', 'data.senderEmail', 'email.fromEmail', 'settings.fromEmail',
        ]);

        $type = strtolower(trim((string) ($item['type'] ?? $item['templateType'] ?? $item['resourceType'] ?? $item['data']['type'] ?? '')));
        $name = (string) ($item['name'] ?? $item['title'] ?? $item['templateName'] ?? $item['campaignName'] ?? $item['campaignTitle'] ?? $item['data']['name'] ?? $item['data']['title'] ?? $item['data']['campaignName'] ?? 'Untitled Email');
        $isFolder = (bool) ($item['isFolder'] ?? $item['folder'] ?? false)
            || in_array($type, ['folder', 'template_folder', 'email_folder'], true);

        $plainPreview = trim(Str::of((string) $body)->stripTags()->limit(160)->toString());
        $id = (string) ($item['id'] ?? $item['_id'] ?? $item['templateId'] ?? $item['template_id'] ?? $item['campaignId'] ?? $item['emailCampaignId'] ?? $item['data']['id'] ?? $item['data']['_id'] ?? $item['data']['campaignId'] ?? '');
        $previewUrl = (string) ($item['previewUrl'] ?? $item['preview_url'] ?? $item['data']['previewUrl'] ?? $item['data']['preview_url'] ?? $item['template']['previewUrl'] ?? $item['email']['previewUrl'] ?? $item['url'] ?? $item['data']['url'] ?? '');
        $editorContentUrl = (string) ($item['editorContentUrl'] ?? $item['editor_content_url'] ?? $item['editorUrl'] ?? $item['editor_url'] ?? $item['contentUrl'] ?? $item['content_url'] ?? $item['data']['editorContentUrl'] ?? $item['data']['editor_content_url'] ?? $item['data']['editorUrl'] ?? $item['data']['contentUrl'] ?? $item['template']['editorContentUrl'] ?? $item['template']['editorUrl'] ?? $item['email']['editorContentUrl'] ?? '');
        $templateDataUrl = (string) ($item['templateDataUrl'] ?? $item['template_data_url'] ?? $item['designUrl'] ?? $item['design_url'] ?? $item['builderUrl'] ?? $item['builder_url'] ?? $item['data']['templateDataUrl'] ?? $item['data']['template_data_url'] ?? $item['data']['designUrl'] ?? $item['template']['templateDataUrl'] ?? '');

        return [
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'is_folder' => $isFolder,
            'subject' => (string) $subject,
            'subjectLine' => (string) $subject,
            'previewText' => (string) $previewText,
            'body' => (string) $body,
            'html' => (string) $body,
            'fromName' => (string) $fromName,
            'fromEmail' => (string) $fromEmail,
            'previewUrl' => $previewUrl,
            'editorContentUrl' => $editorContentUrl,
            'editor_content_url' => $editorContentUrl,
            'templateDataUrl' => $templateDataUrl,
            'preview' => $previewText !== '' ? (string) $previewText : $plainPreview,
            'updated_at' => $item['updatedAt'] ?? $item['lastUpdated'] ?? $item['dateUpdated'] ?? $item['modifiedAt'] ?? $item['data']['updatedAt'] ?? null,
            'created_at' => $item['createdAt'] ?? $item['dateAdded'] ?? $item['data']['createdAt'] ?? null,
            'raw' => $item,
        ];
    }

    private function extractFirstTemplateString(array $item, array $paths): string
    {
        foreach ($paths as $path) {
            $value = data_get($item, $path);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    private function extractTemplateHtmlFromKnownFields(array $item): string
    {
        foreach ([
            'html', 'body', 'htmlBody', 'html_body', 'htmlContent', 'html_content', 'emailBody', 'email_body', 'content', 'message', 'campaignHtml', 'emailContent', 'compiledHtml', 'compiled_html', 'template.html', 'template.body',
            'template.content', 'template.message', 'template.emailContent', 'template.htmlContent', 'template.emailBody', 'template.compiledHtml', 'template.templateData', 'template.builderData',
            'email.html', 'email.body', 'email.htmlContent', 'email.emailContent', 'email.emailBody', 'email.content', 'email.message', 'email.templateData', 'email.builderData',
            'campaign.html', 'campaign.body', 'campaign.htmlContent', 'campaign.emailBody', 'campaign.content', 'campaign.message', 'campaign.emailContent', 'campaign.templateData',
            'data.html', 'data.body', 'data.htmlContent', 'data.emailBody', 'data.compiledHtml', 'data.content', 'data.message', 'data.emailContent', 'data.email.html', 'data.email.body', 'data.email.htmlContent', 'data.email.emailBody',
            'design.html', 'builder.html', 'templateData.html', 'contentData.html', 'previewHtml',
            'editorContent', 'editor.content', 'editor.html', 'editorHtml', 'dnd', 'dndData',
            'templateData', 'templateContent', 'unlayer', 'unlayerData', 'builderData', 'data.editorContent',
            'data.templateData', 'data.templateContent', 'data.builderData', 'data.dnd', 'data.dndData', 'data.design', 'data.builder',
        ] as $path) {
            $html = $this->extractTemplateHtml(data_get($item, $path));
            if ($html !== '') {
                return $html;
            }
        }

        foreach (['design', 'builder', 'data', 'email', 'template', 'templateData', 'contentData', 'editorContent', 'builderData', 'dnd', 'dndData'] as $path) {
            $html = $this->extractTemplateHtml(data_get($item, $path));
            if ($html !== '') {
                return $html;
            }
        }

        return '';
    }

    private function extractTemplateHtml(mixed $value): string
    {
        if (is_string($value)) {
            $value = trim($value);

            if ($value === '' || $this->looksLikeIdentifier($value)) {
                return '';
            }

            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $nested = $this->extractTemplateHtml($decoded);
                if ($nested !== '') {
                    return $nested;
                }
            }

            if (strlen($value) > 80 && preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $value)) {
                $base64 = base64_decode($value, true);
                if (is_string($base64) && trim($base64) !== '' && $base64 !== $value) {
                    $nested = $this->extractTemplateHtml($base64);
                    if ($nested !== '') {
                        return $nested;
                    }
                }
            }

            if (str_contains($value, '<html') || str_contains($value, '<body') || str_contains($value, '<table') || str_contains($value, '<p') || str_contains($value, '<div') || str_contains($value, '<span') || str_contains($value, '<br') || str_contains($value, '{{')) {
                return $value;
            }

            if ($this->looksLikeReadableTemplateText($value)) {
                return nl2br(e($value), false);
            }

            return '';
        }

        if (! is_array($value)) {
            return '';
        }

        foreach ([
            'html', 'body', 'htmlBody', 'html_body', 'htmlContent', 'html_content', 'emailBody', 'email_body', 'emailContent', 'compiledHtml', 'compiled_html', 'ampHtml', 'mjml', 'designHtml', 'templateHtml', 'renderedHtml', 'content', 'message', 'previewHtml', 'text', 'editorContent',
            'editorHtml', 'templateData', 'templateContent', 'builderData', 'dnd', 'dndData',
        ] as $key) {
            if (array_key_exists($key, $value)) {
                $html = $this->extractTemplateHtml($value[$key]);
                if ($html !== '') {
                    return $html;
                }
            }
        }

        foreach (['props', 'data', 'attributes', 'values', 'properties', 'settings'] as $key) {
            if (isset($value[$key]) && is_array($value[$key])) {
                foreach (['html', 'htmlContent', 'emailBody', 'emailContent', 'compiledHtml', 'ampHtml', 'mjml', 'designHtml', 'templateHtml', 'renderedHtml', 'text', 'content', 'body', 'value', 'label', 'heading', 'paragraph', 'buttonText'] as $nestedKey) {
                    if (array_key_exists($nestedKey, $value[$key])) {
                        $html = $this->extractTemplateHtml($value[$key][$nestedKey]);
                        if ($html !== '') {
                            return $html;
                        }
                    }
                }
            }
        }

        foreach (['children', 'blocks', 'rows', 'columns', 'elements', 'nodes', 'values', 'items', 'cells', 'contents'] as $key) {
            if (isset($value[$key]) && is_array($value[$key])) {
                $parts = [];
                foreach ($value[$key] as $child) {
                    $html = $this->extractTemplateHtml($child);
                    if ($html !== '') {
                        $parts[] = $html;
                    }
                }
                if (! empty($parts)) {
                    return implode("\n", $parts);
                }
            }
        }

        foreach (['design', 'builder', 'data', 'email', 'template', 'editor', 'root', 'document', 'templateData', 'contentData', 'unlayer', 'unlayerData'] as $key) {
            if (isset($value[$key])) {
                $html = $this->extractTemplateHtml($value[$key]);
                if ($html !== '') {
                    return $html;
                }
            }
        }

        $designPreview = $this->renderTemplateDesignPreview($value);
        if ($designPreview !== '') {
            return $designPreview;
        }

        $textParts = $this->collectReadableTemplateText($value);
        if (! empty($textParts)) {
            return collect($textParts)
                ->unique()
                ->map(fn (string $text): string => '<p>' . e($text) . '</p>')
                ->implode("\n");
        }

        return '';
    }

    private function renderTemplateDesignPreview(array $value): string
    {
        $fragments = [];
        $this->collectTemplateDesignFragments($value, $fragments);

        if (empty($fragments)) {
            return '';
        }

        $html = collect($fragments)
            ->unique(fn (array $fragment): string => ($fragment['type'] ?? '') . ':' . md5((string) ($fragment['value'] ?? '')))
            ->take(80)
            ->map(function (array $fragment): string {
                $type = (string) ($fragment['type'] ?? 'text');
                $value = trim((string) ($fragment['value'] ?? ''));

                if ($value === '') {
                    return '';
                }

                if ($type === 'html') {
                    return $value;
                }

                if ($type === 'image') {
                    return '<div style="margin:14px 0;text-align:center"><img src="' . e($value) . '" alt="" style="max-width:100%;height:auto;border-radius:10px;display:inline-block" /></div>';
                }

                if ($type === 'link') {
                    return '<p><a href="' . e($value) . '">' . e($value) . '</a></p>';
                }

                return '<p>' . nl2br(e($value), false) . '</p>';
            })
            ->filter()
            ->implode("
");

        return $html !== '' ? '<div style="font-family:Arial,Helvetica,sans-serif;line-height:1.55;color:#111827">' . $html . '</div>' : '';
    }

    private function collectTemplateDesignFragments(mixed $value, array &$fragments, int $depth = 0): void
    {
        if ($depth > 14) {
            return;
        }

        if (is_string($value)) {
            $text = trim($value);
            if ($text === '' || $this->looksLikeTemplateIdentifier($text)) {
                return;
            }

            $decoded = json_decode($text, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->collectTemplateDesignFragments($decoded, $fragments, $depth + 1);
                return;
            }

            if (preg_match('/<\s*(html|body|table|tr|td|div|p|span|img|a|br|h[1-6])/i', $text)) {
                $fragments[] = ['type' => 'html', 'value' => $text];
                return;
            }

            if ($this->looksLikeReadableTemplateText($text) || str_contains($text, '{')) {
                $fragments[] = ['type' => 'text', 'value' => $text];
            }

            return;
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $item) {
            $key = (string) $key;

            if (is_string($item)) {
                $text = trim($item);
                if ($text === '' || $this->looksLikeTemplateIdentifier($text)) {
                    continue;
                }

                $isUrl = Str::startsWith($text, ['http://', 'https://']);
                $imageKey = in_array($key, ['src', 'image', 'imageUrl', 'image_url', 'backgroundImage', 'background_image', 'thumbnail', 'thumbnailUrl'], true);
                $linkKey = in_array($key, ['href', 'link', 'url', 'redirectUrl'], true);

                if ($isUrl && ($imageKey || preg_match('/\.(png|jpe?g|gif|webp|svg)(\?|$)/i', $text))) {
                    $fragments[] = ['type' => 'image', 'value' => $text];
                    continue;
                }

                if ($isUrl && $linkKey) {
                    $fragments[] = ['type' => 'link', 'value' => $text];
                    continue;
                }

                if (in_array($key, ['html', 'htmlContent', 'emailBody', 'emailContent', 'compiledHtml', 'body', 'content', 'text', 'value', 'message', 'label', 'title', 'alt', 'heading', 'paragraph'], true)) {
                    $this->collectTemplateDesignFragments($text, $fragments, $depth + 1);
                }
            } elseif (is_array($item)) {
                $this->collectTemplateDesignFragments($item, $fragments, $depth + 1);
            }
        }
    }

    private function collectReadableTemplateText(array $value): array
    {
        $parts = [];

        foreach ($value as $key => $item) {
            if (is_string($item) && in_array((string) $key, ['text', 'content', 'body', 'message', 'value', 'label', 'title'], true) && $this->looksLikeReadableTemplateText($item)) {
                $parts[] = trim(strip_tags($item));
            } elseif (is_array($item)) {
                $parts = array_merge($parts, $this->collectReadableTemplateText($item));
            }
        }

        return $parts;
    }

    private function looksLikeIdentifier(string $value): bool
    {
        $value = trim($value);

        return (bool) preg_match('/^[a-f0-9]{16,}$/i', $value)
            || (bool) preg_match('/^[A-Za-z0-9_-]{18,}$/', $value) && ! str_contains($value, ' ');
    }

    private function looksLikeReadableTemplateText(string $value): bool
    {
        $value = trim(strip_tags($value));

        if (strlen($value) < 25 || ! str_contains($value, ' ')) {
            return false;
        }

        if ($this->looksLikeIdentifier($value)) {
            return false;
        }

        return (bool) preg_match('/[.!?,]|\s(the|and|you|your|coach|school|hi|hello|thanks)\s/i', ' ' . $value . ' ');
    }

    private function transformCoachContact(array $contact, array $trackingFieldMap = []): array
    {
        $customFields = collect($contact['customFields'] ?? []);
        // Keep contact-list transforms fast. Full tracking-field maps are resolved only
        // by dashboard/tracking methods that need them; this prevents per-contact field
        // API calls while loading the coach list. CSV/dashboard exports pass one shared
        // map so Recruiting Center custom-field IDs are read correctly.
        $trackingFieldMap = is_array($trackingFieldMap) ? $trackingFieldMap : [];

        $defaultFieldIds = [
            'school_name' => 'mVRCvtpkuGo8eCgj2EkW',
            'school_conference' => '0fPOQNgzOiFmemKNwQ4k',
            'school_division' => config('ghl.coach_database.custom_fields.school_division'),
            'coach_title' => 'r0iC4KEiNp0JFygWViui',
            'coach_external_id' => 'D5Ca9PLSFG3dZdrsaIlV',
        ];

        $fieldValue = function (array $field): mixed {
            return $field['value']
                ?? $field['fieldValue']
                ?? $field['field_value']
                ?? $field['valueString']
                ?? $field['value_string']
                ?? $field['stringValue']
                ?? $field['text']
                ?? null;
        };

        $getCustomField = function (string $configKey) use ($customFields, $defaultFieldIds, $fieldValue): mixed {
            $fieldIds = collect([
                    config("ghl.coach_database.custom_fields.{$configKey}"),
                    $defaultFieldIds[$configKey] ?? null,
                ])
                ->filter()
                ->unique()
                ->values();

            foreach ($fieldIds as $fieldId) {
                $match = $customFields->first(function ($field) use ($fieldId): bool {
                    if (! is_array($field)) {
                        return false;
                    }

                    return (string) ($field['id'] ?? '') === (string) $fieldId
                        || (string) ($field['customFieldId'] ?? '') === (string) $fieldId
                        || (string) ($field['fieldId'] ?? '') === (string) $fieldId
                        || (string) ($field['key'] ?? '') === (string) $fieldId;
                });

                if (is_array($match)) {
                    $value = $fieldValue($match);

                    if (filled($value)) {
                        return $value;
                    }
                }
            }

            return null;
        };

        $schoolCustom = $this->stringCustomFieldFromRecord($contact, [
            'school_name',
            'contact.school_name',
            'School Name',
            'School',
            'College',
            'College Name',
            'University',
        ]) ?: $getCustomField('school_name');

        // The recruiting school custom field is authoritative. Recruiting Center's companyName can
        // contain a generic organization value and was incorrectly winning before it.
        $school = $schoolCustom
            ?? $contact['schoolName']
            ?? $contact['school_name']
            ?? data_get($contact, 'school.name')
            ?? $contact['companyName']
            ?? $contact['company_name']
            ?? $contact['businessName']
            ?? $contact['business_name']
            ?? data_get($contact, 'business.name')
            ?? data_get($contact, 'company.name')
            ?? data_get($contact, 'business.title')
            ?? data_get($contact, 'company.title')
            ?? data_get($contact, 'business.businessName')
            ?? data_get($contact, 'company.companyName')
            ?? data_get($contact, 'associations.business.name')
            ?? data_get($contact, 'associations.company.name')
            ?? data_get($contact, 'businesses.0.name')
            ?? data_get($contact, 'companies.0.name');

        $schoolAliases = collect([
            $schoolCustom,
            $contact['schoolName'] ?? null,
            $contact['school_name'] ?? null,
            data_get($contact, 'school.name'),
            $contact['companyName'] ?? null,
            $contact['company_name'] ?? null,
            $contact['businessName'] ?? null,
            $contact['business_name'] ?? null,
            data_get($contact, 'business.name'),
            data_get($contact, 'company.name'),
            data_get($contact, 'associations.business.name'),
            data_get($contact, 'associations.company.name'),
            data_get($contact, 'businesses.0.name'),
            data_get($contact, 'companies.0.name'),
        ])->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique(fn (string $value): string => strtolower($value))
            ->values()
            ->all();

        $schoolLogoUrl = $this->schoolLogoUrlFromRecord($contact);
        $contactConference = $this->stringCustomFieldFromRecord($contact, ['contact.conference', 'school_conference', 'conference', 'Conference', 'School Conference']) ?: $getCustomField('school_conference');
        $contactDivision = $this->stringCustomFieldFromRecord($contact, ['contact.division', 'school_division', 'division', 'Division', 'School Division']) ?: $getCustomField('school_division');

        $name = $contact['contactName']
            ?? trim(($contact['firstName'] ?? '') . ' ' . ($contact['lastName'] ?? ''));

        $contactBusinessId = $contact['businessId']
            ?? $contact['business_id']
            ?? $contact['companyId']
            ?? $contact['company_id']
            ?? data_get($contact, 'business.id')
            ?? data_get($contact, 'business._id')
            ?? data_get($contact, 'company.id')
            ?? data_get($contact, 'company._id')
            ?? data_get($contact, 'associations.business.id')
            ?? data_get($contact, 'associations.company.id')
            ?? data_get($contact, 'businesses.0.id')
            ?? data_get($contact, 'businesses.0._id')
            ?? data_get($contact, 'companies.0.id')
            ?? data_get($contact, 'companies.0._id')
            ?? $getCustomField('business_id')
            ?? $getCustomField('company_id')
            ?? $getCustomField('school_id');

        $contactBusinessName = $contact['businessName']
            ?? $contact['business_name']
            ?? $contact['companyName']
            ?? $contact['company_name']
            ?? data_get($contact, 'business.name')
            ?? data_get($contact, 'company.name')
            ?? data_get($contact, 'associations.business.name')
            ?? data_get($contact, 'associations.company.name')
            ?? data_get($contact, 'businesses.0.name')
            ?? data_get($contact, 'companies.0.name')
            ?? $school;

        return [
            'id' => $contact['id'] ?? null,
            'location_id' => $contact['locationId'] ?? null,
            'name' => trim($name),
            'first_name' => $contact['firstName'] ?? null,
            'last_name' => $contact['lastName'] ?? null,
            'email' => $contact['email'] ?? null,
            'phone' => $contact['phone'] ?? null,
            'type' => $contact['type'] ?? null,
            'source' => $contact['source'] ?? null,
            'school' => $school,
            'school_name' => $school,
            'company_name' => $school,
            'business_name' => $contactBusinessName,
            'school_or_company' => $school,
            'school_aliases' => $schoolAliases,
            'school_id' => $contactBusinessId,
            'business_id' => $contactBusinessId,
            'company_id' => $contactBusinessId,
            'ghl_business_id' => $contactBusinessId,
            'school_logo_url' => $schoolLogoUrl,
            'business_logo_url' => $schoolLogoUrl,
            'logo_url' => $schoolLogoUrl,
            'title' => $getCustomField('coach_title'),
            'sport' => $getCustomField('coach_sport'),
            'conference' => $contactConference,
            'division' => $contactDivision,
            'external_id' => $getCustomField('coach_external_id'),
            'state' => $getCustomField('school_state'),
            'city' => $getCustomField('school_city'),
            'tags' => $contact['tags'] ?? [],
            'is_saved_school' => $this->contactHasTag($contact, config('ghl.coach_database.tags.saved_school', 'saved school')),
            'is_favorite_school' => $this->contactHasTag($contact, config('ghl.coach_database.tags.favorite_school', 'favorite school')),
            'is_saved_coach' => $this->contactHasTag($contact, config('ghl.coach_database.tags.saved_coach', 'saved coach')),
            'is_favorite_coach' => $this->contactHasTag($contact, config('ghl.coach_database.tags.favorite_coach', 'favorite coach')),
            'viewed_profile' => $this->contactHasAnyTag($contact, [config('ghl.coach_database.tags.viewed_profile', 'viewed profile'), 'profile viewed', 'viewed player profile']),
            'viewed_highlights' => $this->contactHasAnyTag($contact, [config('ghl.coach_database.tags.viewed_highlights', 'viewed highlights'), 'highlight viewed', 'video viewed', 'youtube clicked']),
            'engaged' => $this->contactHasAnyTag($contact, [config('ghl.coach_database.tags.engaged', 'engaged'), 'clicked', 'opened', 'replied']),
            'replied' => $this->contactHasAnyTag($contact, [config('ghl.coach_database.tags.replied', 'replied'), 'coach replied', 'email replied']),
            'trigger_link_clicked' => $this->contactHasAnyTag($contact, [config('ghl.coach_database.tags.trigger_link_clicked', 'trigger link clicked'), 'trigger link clicked', 'profile link clicked', 'website clicked', 'social clicked', 'instagram clicked', 'x clicked', 'youtube clicked', 'highlight link clicked']),
            'profile_view_count' => $this->numericCustomFieldFromContact($contact, ['profile_view_count', 'profileViews', 'profile_views', 'plyrcard_profile_views']),
            'highlight_view_count' => $this->numericCustomFieldFromContact($contact, ['highlight_view_count', 'highlightViews', 'highlight_views', 'youtube_clicks']),
            'trigger_link_click_count' => $this->numericCustomFieldFromContact($contact, ['trigger_link_click_count', 'triggerLinkClicks', 'trigger_link_clicks', 'link_clicks', 'website_clicks', 'social_clicks', 'youtube_clicks', 'instagram_clicks', 'x_clicks', 'plyrcard_link_clicks', 'stats.clicks']),
            'view_profile_total' => $this->numericCustomFieldFromContact($contact, ['view_profile_total'], $trackingFieldMap),
            'view_profile_website' => $this->numericCustomFieldFromContact($contact, ['view_profile_website'], $trackingFieldMap),
            'view_profile_instagram' => $this->numericCustomFieldFromContact($contact, ['view_profile_instagram'], $trackingFieldMap),
            'view_profile_youtube' => $this->numericCustomFieldFromContact($contact, ['view_profile_youtube'], $trackingFieldMap),
            'view_profile_x' => $this->numericCustomFieldFromContact($contact, ['view_profile_x'], $trackingFieldMap),
            'view_profile_email_link' => $this->numericCustomFieldFromContact($contact, ['view_profile_email_link'], $trackingFieldMap),
            'view_profile_qr' => $this->numericCustomFieldFromContact($contact, ['view_profile_qr'], $trackingFieldMap),
            'profile_view_unique_contact_count' => $this->numericCustomFieldFromContact($contact, ['profile_view_unique_contact_count', 'unique_profile_view_count'], $trackingFieldMap),
            'profile_view_unique_school_count' => $this->numericCustomFieldFromContact($contact, ['profile_view_unique_school_count'], $trackingFieldMap),
            'profile_view_school_click_count' => $this->numericCustomFieldFromContact($contact, ['profile_view_school_click_count', 'school_profile_view_count', 'school_click_count'], $trackingFieldMap),
            'last_profile_view_at' => $this->stringCustomFieldFromContact($contact, ['last_profile_view_at'], $trackingFieldMap),
            'last_profile_view_source' => $this->stringCustomFieldFromContact($contact, ['last_profile_view_source'], $trackingFieldMap),
            'last_profile_view_platform' => $this->stringCustomFieldFromContact($contact, ['last_profile_view_platform'], $trackingFieldMap),
            'last_profile_view_url' => $this->stringCustomFieldFromContact($contact, ['last_profile_view_url'], $trackingFieldMap),
            'last_profile_view_referrer' => $this->stringCustomFieldFromContact($contact, ['last_profile_view_referrer'], $trackingFieldMap),
            'last_clicked_platform' => $this->stringCustomFieldFromContact($contact, ['last_clicked_platform'], $trackingFieldMap),
            'last_clicked_url' => $this->stringCustomFieldFromContact($contact, ['last_clicked_url'], $trackingFieldMap),
            'email_sent_count' => $this->numericCustomFieldFromContact($contact, ['email_sent_count'], $trackingFieldMap),
            'email_open_count' => $this->numericCustomFieldFromContact($contact, ['email_open_count'], $trackingFieldMap),
            'email_click_count' => $this->numericCustomFieldFromContact($contact, ['email_click_count'], $trackingFieldMap),
            'website_click_count' => $this->numericCustomFieldFromContact($contact, ['website_click_count'], $trackingFieldMap),
            'instagram_click_count' => $this->numericCustomFieldFromContact($contact, ['instagram_click_count'], $trackingFieldMap),
            'youtube_click_count' => $this->numericCustomFieldFromContact($contact, ['youtube_click_count'], $trackingFieldMap),
            'x_click_count' => $this->numericCustomFieldFromContact($contact, ['x_click_count'], $trackingFieldMap),
            'email_delivered_count' => $this->numericCustomFieldFromContact($contact, ['email_delivered_count'], $trackingFieldMap),
            'email_failed_count' => $this->numericCustomFieldFromContact($contact, ['email_failed_count'], $trackingFieldMap),
            'coach_reply_count' => $this->numericCustomFieldFromContact($contact, ['coach_reply_count', 'replyCount', 'replies', 'email_replies', 'plyrcard_coach_replies', 'stats.replies']),
            'valid_email' => $contact['validEmail'] ?? null,
            'dnd' => $contact['dnd'] ?? false,
            'date_added' => $contact['dateAdded'] ?? null,
            'date_updated' => $contact['dateUpdated'] ?? null,
        ];
    }

    private function transformCoachContactFromBusiness(array $contact, array $business, array $trackingFieldMap = []): array
    {
        $transformed = $this->transformCoachContact($contact, $trackingFieldMap);

        $businessId = $business['id']
            ?? $business['_id']
            ?? $contact['businessId']
            ?? null;

        $businessName = $business['name']
            ?? $business['businessName']
            ?? $business['companyName']
            ?? $business['title']
            ?? $transformed['school']
            ?? null;

        $businessLogoUrl = $this->schoolLogoUrlFromRecord($business);
        $logoUrl = $transformed['school_logo_url'] ?? $businessLogoUrl;

        $transformed['business_id'] = $businessId;
        $transformed['company_id'] = $businessId;
        $transformed['ghl_business_id'] = $businessId;
        $transformed['school_id'] = $businessId;
        $transformed['school'] = $businessName;
        $transformed['school_name'] = $businessName;
        $transformed['company_name'] = $businessName;
        $transformed['business_name'] = $businessName;
        $transformed['school_or_company'] = $businessName;
        $transformed['school_logo_url'] = $logoUrl;
        $transformed['business_logo_url'] = $businessLogoUrl ?: $logoUrl;
        $transformed['logo_url'] = $logoUrl;

        $transformed['school_email'] = $business['email'] ?? null;
        $transformed['school_phone'] = $business['phone'] ?? null;
        $transformed['school_website'] = $business['website'] ?? null;
        $transformed['school_address'] = $business['address'] ?? null;
        $transformed['school_city'] = $business['city'] ?? null;
        $transformed['school_state'] = $business['state'] ?? null;
        $transformed['school_postal_code'] = $business['postalCode'] ?? null;
        $transformed['school_country'] = $business['country'] ?? null;

        return $transformed;
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

        return is_array($teamMembers) && count($teamMembers) === 1;
    }

    private function resolveContactId(User $user): ?string
    {
        $contactId = $user->ghl_contact_id ?: $this->findContactIdByEmail($user->email);

        if ($contactId && ! $user->ghl_contact_id) {
            $user->forceFill(['ghl_contact_id' => $contactId])->saveQuietly();
        }

        return $contactId;
    }

    private function updateContactCustomFields(string $contactId, array $customFields, array $context = []): bool
    {
        $token = config('ghl.token');

        if (! $token) {
            Log::warning('Recruiting Center custom field sync skipped. Missing token.', array_merge($context, [
                'contact_id' => $contactId,
            ]));

            return false;
        }

        $response = Http::withHeaders(['Version' => '2021-07-28'])
            ->timeout((int) config('ghl.timeout', 20))
            ->withToken($token)
            ->acceptJson()
            ->put("{$this->baseUrl}/contacts/{$contactId}", ['customFields' => $customFields]);

        if ($response->failed()) {
            Log::error('Recruiting Center custom field sync failed.', array_merge($context, [
                'contact_id' => $contactId,
                'status' => $response->status(),
                'body' => $response->body(),
                'custom_fields' => $customFields,
            ]));

            return false;
        }

        return true;
    }

    private function findContactIdByEmail(?string $email, ?string $locationId = null, ?string $tokenOverride = null): ?string
    {
        if (! $email) {
            return null;
        }

        $email = trim($email);
        $locationId = $locationId ?: config('ghl.location_id');
        $token = $this->tokenForLocation($locationId, $tokenOverride);

        if (! $locationId || ! $token) {
            return null;
        }

        $response = Http::withHeaders(['Version' => '2021-07-28'])
            ->timeout((int) config('ghl.timeout', 20))
            ->withToken($token)
            ->acceptJson()
            ->get("{$this->baseUrl}/contacts/search/duplicate", [
                'locationId' => $locationId,
                'email' => $email,
            ]);

        if ($response->failed()) {
            Log::error('Recruiting Center contact search failed.', [
                'email' => $email,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json() ?? [];
        $contacts = collect($data['contacts'] ?? (isset($data['contact']) ? [$data['contact']] : []));

        $matched = $contacts->first(function ($contact) use ($email) {
            return strtolower(trim($contact['email'] ?? '')) === strtolower($email);
        });

        return $matched['id'] ?? null;
    }


    public function getSchoolBusinessesPageForUser(User $user, int $skip = 0, int $limit = 50): array
    {
        $credentials = $this->credentialsForUser($user);

        return $this->getBusinessesPage(
            locationId: $credentials['location_id'],
            tokenOverride: $credentials['token_override'],
            skip: $skip,
            limit: $limit,
        );
    }

    public function getBusinessesPage(?string $locationId, ?string $tokenOverride, int $skip = 0, int $limit = 50): array
    {
        $locationId = $locationId ?: config('ghl.location_id');
        $token = $this->tokenForLocation($locationId, $tokenOverride);
        $limit = min(max($limit, 1), 100);
        $skip = max($skip, 0);

        if (! $locationId || ! $token) {
            return ['success' => false, 'businesses' => [], 'schools' => [], 'has_more' => false, 'next_skip' => null, 'error' => 'Missing recruiting data connection.'];
        }

        try {
            $response = Http::withHeaders(['Version' => config('ghl.businesses_version', 'v3')])
                ->connectTimeout((int) config('ghl.coach_database.http_connect_timeout', 5))
                ->timeout((int) config('ghl.coach_database.http_timeout', 12))
                ->retry(
                    (int) config('ghl.coach_database.http_retries', 1),
                    (int) config('ghl.coach_database.http_retry_sleep_ms', 350),
                    throw: false,
                )
                ->withToken($token)
                ->acceptJson()
                ->get("{$this->baseUrl}/businesses/", [
                    'locationId' => $locationId,
                    'limit' => $limit,
                    'skip' => $skip,
                ]);
        } catch (\Throwable $exception) {
            Log::warning('Recruiting school/company request timed out or failed before response.', [
                'location_id' => $locationId,
                'skip' => $skip,
                'limit' => $limit,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'businesses' => [],
                'schools' => [],
                'count' => 0,
                'total' => null,
                'has_more' => true,
                'next_skip' => $skip,
                'temporary_failure' => true,
                'error' => 'Recruiting Center schools timed out. Kept existing cached data; try again shortly.',
            ];
        }

        $data = $response->json() ?? [];

        if ($response->failed()) {
            Log::error('Recruiting school/company request failed.', [
                'location_id' => $locationId,
                'status' => $response->status(),
                'skip' => $skip,
                'limit' => $limit,
                'body' => $response->body(),
            ]);

            return ['success' => false, 'businesses' => [], 'schools' => [], 'has_more' => false, 'next_skip' => null, 'error' => 'Unable to load schools.'];
        }

        $businesses = collect($data['businesses'] ?? $data['data'] ?? [])
            ->filter(fn ($business): bool => is_array($business) && filled($business['id'] ?? null))
            ->map(fn (array $business): array => $this->transformSchoolBusiness($business))
            ->values()
            ->all();

        $returned = count($businesses);
        $total = $data['total'] ?? $data['meta']['total'] ?? null;
        $hasMore = $returned >= $limit;

        if (is_numeric($total)) {
            $hasMore = ($skip + $returned) < (int) $total;
        }

        return [
            'success' => true,
            'businesses' => $businesses,
            'schools' => $businesses,
            'count' => $returned,
            'total' => $total,
            'skip' => $skip,
            'next_skip' => $hasMore ? $skip + $returned : null,
            'has_more' => $hasMore,
            'error' => null,
        ];
    }

    public function getContactsForBusinessForUser(User $user, string $businessId, int $skip = 0, int $limit = 100, ?array $school = null): array
    {
        $credentials = $this->credentialsForUser($user);

        return $this->getContactsForBusiness(
            locationId: $credentials['location_id'],
            tokenOverride: $credentials['token_override'],
            businessId: $businessId,
            skip: $skip,
            limit: $limit,
            school: $school,
        );
    }

    /**
     * Read exact contact totals for a page of Recruiting Center businesses without downloading
     * every contact. HighLevel's v3 business-contact endpoint returns a top-level
     * `count`; requesting limit=1 keeps each lookup small. Requests are pooled so a
     * 25-school Discover page costs roughly one network round trip rather than 25.
     */
    public function getBusinessContactCountsForUser(User $user, array $schools): array
    {
        $credentials = $this->credentialsForUser($user);
        $locationId = $credentials['location_id'];
        $token = $this->tokenForLocation($locationId, $credentials['token_override']);

        $schools = collect($schools)
            ->filter(fn ($school): bool => is_array($school))
            ->map(function (array $school): array {
                $businessId = trim((string) ($school['business_id'] ?? $school['company_id'] ?? $school['id'] ?? ''));
                return [
                    'business_id' => $businessId,
                    'name' => trim((string) ($school['name'] ?? $school['school_name'] ?? '')),
                    'school' => $school,
                ];
            })
            ->filter(fn (array $row): bool => $row['business_id'] !== '')
            ->unique('business_id')
            ->take(25)
            ->values();

        if (! $locationId || ! $token) {
            return ['success' => false, 'counts' => [], 'sample_coaches' => [], 'error' => 'Missing recruiting data connection.'];
        }

        if ($schools->isEmpty()) {
            return ['success' => true, 'counts' => [], 'sample_coaches' => [], 'error' => null];
        }

        try {
            $responses = Http::pool(function (Pool $pool) use ($schools, $locationId, $token): array {
                return $schools->map(function (array $row) use ($pool, $locationId, $token) {
                    $businessId = $row['business_id'];

                    return $pool->as($businessId)
                        ->withHeaders(['Version' => config('ghl.business_contacts_version', 'v3')])
                        ->connectTimeout((int) config('ghl.coach_database.http_connect_timeout', 5))
                        ->timeout((int) config('ghl.coach_database.http_timeout', 12))
                        ->withToken($token)
                        ->acceptJson()
                        ->get("{$this->baseUrl}/contacts/business/{$businessId}", [
                            'locationId' => $locationId,
                            'limit' => 1,
                            'skip' => 0,
                        ]);
                })->all();
            });
        } catch (\Throwable $exception) {
            Log::warning('Recruiting business contact-count pool failed.', [
                'location_id' => $locationId,
                'business_count' => $schools->count(),
                'error' => $exception->getMessage(),
            ]);

            return ['success' => false, 'counts' => [], 'sample_coaches' => [], 'temporary_failure' => true, 'error' => 'Unable to read school coach counts from Recruiting Center.'];
        }

        $counts = [];
        $sampleCoaches = [];
        $errors = [];

        foreach ($schools as $row) {
            $businessId = $row['business_id'];
            $response = $responses[$businessId] ?? null;

            if (! $response instanceof \Illuminate\Http\Client\Response || $response->failed()) {
                $errors[$businessId] = $response instanceof \Illuminate\Http\Client\Response
                    ? 'HTTP ' . $response->status()
                    : 'No response';
                continue;
            }

            $data = $response->json() ?? [];
            $contacts = $this->extractContactsFromResponse($data);
            $reportedCount = $data['count']
                ?? $data['total']
                ?? data_get($data, 'meta.total')
                ?? data_get($data, 'contacts.count')
                ?? count($contacts);

            $counts[$businessId] = max(0, (int) $reportedCount);

            $sample = collect($contacts)->first(fn ($contact): bool => is_array($contact));
            if (is_array($sample)) {
                $business = array_merge($row['school'], [
                    'id' => $businessId,
                    'business_id' => $businessId,
                    'name' => $row['name'] !== '' ? $row['name'] : ($row['school']['name'] ?? 'School'),
                ]);
                $sampleCoaches[$businessId] = $this->transformCoachContactFromBusiness($sample, $business);
            }
        }

        return [
            'success' => count($counts) > 0 || empty($errors),
            'counts' => $counts,
            'sample_coaches' => $sampleCoaches,
            'errors' => $errors,
            'error' => count($counts) > 0 ? null : 'Recruiting Center did not return school coach counts.',
        ];
    }

    public function getContactsForBusiness(?string $locationId, ?string $tokenOverride, string $businessId, int $skip = 0, int $limit = 100, ?array $school = null): array
    {
        $locationId = $locationId ?: config('ghl.location_id');
        $token = $this->tokenForLocation($locationId, $tokenOverride);
        $limit = min(max($limit, 1), 100);
        $skip = max($skip, 0);

        if (! $locationId || ! $token || ! $businessId) {
            return ['success' => false, 'contacts' => [], 'coaches' => [], 'has_more' => false, 'next_skip' => null, 'error' => 'Missing recruiting data connection.'];
        }

        $contacts = collect();
        $page = 0;
        $maxPages = (int) config('ghl.coach_database.business_contacts_max_pages', 25);
        $nextSkip = $skip;
        $hasMore = false;
        $total = null;

        do {
            $query = [
                'locationId' => $locationId,
                'limit' => $limit,
                'skip' => $nextSkip,
            ];

            try {
                $response = Http::withHeaders(['Version' => config('ghl.business_contacts_version', 'v3')])
                    ->connectTimeout((int) config('ghl.connect_timeout', 5))
                    ->timeout((int) config('ghl.timeout', 12))
                    ->retry((int) config('ghl.retries', 1), (int) config('ghl.retry_sleep_ms', 250), throw: false)
                    ->withToken($token)
                    ->acceptJson()
                    ->get("{$this->baseUrl}/contacts/business/{$businessId}", $query);
            } catch (\Illuminate\Http\Client\ConnectionException $exception) {
                Log::warning('Recruiting school coach request timed out.', [
                    'location_id' => $locationId,
                    'business_id' => $businessId,
                    'query' => $query,
                    'error' => $exception->getMessage(),
                ]);

                return [
                    'success' => false,
                    'contacts' => $contacts->values()->all(),
                    'coaches' => [],
                    'has_more' => false,
                    'next_skip' => null,
                    'error' => 'Recruiting Center timed out while loading coaches for this school.',
                    'timed_out' => true,
                ];
            }

            $data = $response->json() ?? [];

            if ($response->failed()) {
                Log::error('Recruiting school coach request failed.', [
                    'location_id' => $locationId,
                    'business_id' => $businessId,
                    'status' => $response->status(),
                    'query' => $query,
                    'body' => $response->body(),
                ]);

                return ['success' => false, 'contacts' => [], 'coaches' => [], 'has_more' => false, 'next_skip' => null, 'error' => 'Unable to load coaches for this school.'];
            }

            $pageContacts = $this->extractContactsFromResponse($data);
            $contacts = $contacts->merge($pageContacts);
            $returned = count($pageContacts);
            $total = $data['count'] ?? $data['total'] ?? $data['meta']['total'] ?? $data['contacts']['total'] ?? $data['contacts']['count'] ?? $total;
            $hasMore = $returned >= $limit;

            if (is_numeric($total)) {
                $hasMore = ($nextSkip + $returned) < (int) $total;
            }

            $nextSkip += $returned ?: $limit;
            $page++;
        } while ($hasMore && $page < $maxPages);

        $coaches = $contacts
            ->filter(fn ($contact): bool => is_array($contact))
            ->map(function (array $contact) use ($businessId, $school): array {
                $coach = $this->transformCoachContact($contact);
                $coach['business_id'] = $businessId;
                if ($school) {
                    $schoolLogoUrl = $school['logo_url'] ?? $school['school_logo_url'] ?? $school['business_logo_url'] ?? null;
                    $coach['school_id'] = $school['id'] ?? $businessId;
                    $coach['school'] = $school['name'] ?? ($coach['school'] ?? null);
                    $coach['conference'] = $school['conference'] ?? ($coach['conference'] ?? null);
                    $coach['division'] = $school['division'] ?? ($coach['division'] ?? null);
                    $coach['school_logo_url'] = $coach['school_logo_url'] ?? $schoolLogoUrl;
                    $coach['business_logo_url'] = $coach['business_logo_url'] ?? $schoolLogoUrl;
                    $coach['logo_url'] = $coach['logo_url'] ?? $schoolLogoUrl;
                }
                return $coach;
            })
            ->filter(fn (array $coach): bool => filled($coach['id'] ?? null))
            ->unique('id')
            ->values()
            ->all();

        return [
            'success' => true,
            'contacts' => $coaches,
            'coaches' => $coaches,
            'count' => is_numeric($total) ? (int) $total : count($coaches),
            'total' => is_numeric($total) ? (int) $total : count($coaches),
            'skip' => $skip,
            'next_skip' => $hasMore ? $nextSkip : null,
            'has_more' => $hasMore,
            'error' => null,
        ];
    }

    public function getConversationsForUser(User $user, array $query = []): array
    {
        $credentials = $this->credentialsForUser($user);
        $locationId = $credentials['location_id'];
        $token = $this->tokenForLocation($locationId, $credentials['token_override']);

        if (! $locationId || ! $token) {
            return ['success' => false, 'conversations' => [], 'error' => 'Missing recruiting data connection.'];
        }

        $requestedLimit = (int) ($query['limit'] ?? 100);
        $requestedLimit = min(max($requestedLimit, 1), 100);
        $fetchAll = (bool) ($query['fetch_all'] ?? false);
        $maxRows = (int) ($query['max_rows'] ?? 500);
        $maxRows = min(max($maxRows, $requestedLimit), 1000);

        $baseParams = array_filter([
            'locationId' => $locationId,
            'contactId' => $query['contactId'] ?? null,
            'query' => $query['search'] ?? null,
            'status' => $query['status'] ?? 'all',
            'sortBy' => $query['sortBy'] ?? 'last_message_date',
            'sort' => $query['sort'] ?? 'desc',
        ], fn ($value) => filled($value));

        $versions = array_values(array_unique(array_filter([
            trim((string) config('ghl.conversations_search_version', '2023-02-21')),
            '2023-02-21',
            'v3',
        ])));

        $lastError = null;
        $lastStatus = null;
        $lastRaw = null;

        foreach ($versions as $version) {
            $allItems = [];
            $skip = 0;
            $page = 0;
            $pageSuccess = false;
            $total = 0;

            do {
                $params = array_merge($baseParams, ['limit' => $requestedLimit]);

                if ($skip > 0) {
                    $params['skip'] = $skip;
                }

                $response = Http::withHeaders(['Version' => $version])
                    ->timeout((int) config('ghl.timeout', 20))
                    ->withToken($token)
                    ->acceptJson()
                    ->get("{$this->baseUrl}/conversations/search", $params);

                $data = $response->json() ?? [];
                $lastRaw = $data;

                if ($response->failed()) {
                    $lastStatus = $response->status();
                    $lastError = $response->body();

                    Log::error('Recruiting conversations request failed.', [
                        'status' => $response->status(),
                        'version' => $version,
                        'params' => $params,
                        'body' => $response->body(),
                    ]);

                    break;
                }

                $pageSuccess = true;
                $items = $this->extractConversationsFromResponse($data);

                foreach ($items as $item) {
                    if (is_array($item)) {
                        $id = (string) ($item['id'] ?? $item['_id'] ?? $item['conversationId'] ?? md5(json_encode($item) ?: uniqid('', true)));
                        $allItems[$id] = $item;
                    }
                }

                $total = (int) ($data['total'] ?? $data['meta']['total'] ?? data_get($data, 'conversations.total') ?? 0);
                $count = count($items);
                $skip += $requestedLimit;
                $page++;

                $hasMore = $fetchAll
                    && $count >= $requestedLimit
                    && count($allItems) < $maxRows
                    && ($total === 0 || count($allItems) < $total)
                    && $page < 10;
            } while ($hasMore);

            if ($pageSuccess) {
                $conversations = collect(array_values($allItems))
                    ->filter(fn ($item) => is_array($item))
                    ->map(fn (array $item): array => $this->transformConversation($item))
                    ->filter(fn (array $item): bool => filled($item['id'] ?? null))
                    ->values()
                    ->all();

                return [
                    'success' => true,
                    'conversations' => $conversations,
                    'total' => $total ?: count($conversations),
                    'error' => null,
                ];
            }
        }

        return [
            'success' => false,
            'conversations' => [],
            'error' => 'Unable to load conversations.',
            'status' => $lastStatus,
            'raw' => $lastRaw,
            'last_error' => $lastError,
        ];
    }

    public function getConversationMessagesForUser(User $user, string $conversationId, ?string $lastMessageId = null, int $limit = 50): array
    {
        $credentials = $this->credentialsForUser($user);
        $token = $this->tokenForLocation($credentials['location_id'], $credentials['token_override']);

        if (! $token || ! $conversationId) {
            return ['success' => false, 'messages' => [], 'error' => 'Missing conversation connection.'];
        }

        $params = ['limit' => min(max($limit, 1), 100), 'type' => 'TYPE_EMAIL'];
        if ($lastMessageId) {
            $params['lastMessageId'] = $lastMessageId;
        }

        $versions = array_values(array_unique(array_filter([
            trim((string) config('ghl.conversations_messages_version', '2021-04-15')),
            '2021-04-15',
            '2023-02-21',
            'v3',
        ])));

        $lastStatus = null;
        $lastData = null;

        foreach ($versions as $version) {
            $response = Http::withHeaders(['Version' => $version])
                ->timeout((int) config('ghl.timeout', 20))
                ->withToken($token)
                ->acceptJson()
                ->get("{$this->baseUrl}/conversations/{$conversationId}/messages", $params);

            $data = $response->json() ?? [];
            $lastData = $data;

            if ($response->failed()) {
                $lastStatus = $response->status();
                continue;
            }

            $items = collect($this->extractConversationMessagesFromResponse($data))
                ->filter(fn ($item): bool => is_array($item))
                ->values();

            /*
             * A single HighLevel conversation-message row may represent more than one
             * real email. This commonly happens after a coach replies: the flattened
             * row contains every email ID in meta.email.messageIds, while the previous
             * implementation reconstructed only messageIds[0] (normally the sent
             * email). Expand every email ID so inbound replies are rebuilt through the
             * same individual-email endpoint used for outbound messages.
             */
            $emailIds = $items
                ->flatMap(fn (array $item): array => $this->conversationEmailMessageIds($item))
                ->filter(fn (string $id): bool => $id !== '' && ! str_contains($id, 'Over 9 levels deep'))
                ->unique()
                ->values();

            $detailsByEmailId = collect();

            if ($emailIds->isNotEmpty()) {
                try {
                    $detailResponses = Http::pool(function ($pool) use ($emailIds, $token, $version) {
                        return $emailIds->mapWithKeys(function (string $emailId) use ($pool, $token, $version): array {
                            return [
                                $emailId => $pool
                                    ->as($emailId)
                                    ->withHeaders(['Version' => $version ?: '2021-04-15'])
                                    ->withToken($token)
                                    ->acceptJson()
                                    ->timeout((int) config('ghl.message_detail_timeout', 20))
                                    ->get($this->baseUrl . '/conversations/messages/email/' . rawurlencode($emailId)),
                            ];
                        })->all();
                    });

                    foreach ($detailResponses as $emailId => $detailResponse) {
                        if (! $detailResponse instanceof \Illuminate\Http\Client\Response || $detailResponse->failed()) {
                            continue;
                        }

                        $detailData = $detailResponse->json() ?? [];
                        $emailMessage = data_get($detailData, 'emailMessage');

                        if (! is_array($emailMessage)) {
                            $emailMessage = data_get($detailData, 'message');
                        }

                        if (! is_array($emailMessage)) {
                            continue;
                        }

                        $detailsByEmailId->put((string) $emailId, $emailMessage);
                    }
                } catch (\Throwable $exception) {
                    Log::warning('Recruiting email HTML detail batch failed.', [
                        'conversation_id' => $conversationId,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            $messages = $items
                ->flatMap(function (array $item) use ($detailsByEmailId): array {
                    $emailIdsForItem = $this->conversationEmailMessageIds($item);

                    if ($emailIdsForItem === []) {
                        return [$this->transformConversationMessage($item)];
                    }

                    $expanded = [];

                    foreach ($emailIdsForItem as $emailId) {
                        $detail = $detailsByEmailId->get($emailId);

                        if (! is_array($detail)) {
                            continue;
                        }

                        // Retain list-level metadata while letting the authoritative
                        // individual email object supply body, sender, recipients,
                        // direction, subject, status, attachments, and timestamps.
                        $reconstructed = $item;
                        $reconstructed['id'] = $emailId;
                        $reconstructed['messageId'] = $emailId;
                        $reconstructed['email_message_id'] = $emailId;
                        $reconstructed['emailMessage'] = $detail;
                        $reconstructed['html_body'] = (string) (
                            $detail['body']
                            ?? $detail['htmlBody']
                            ?? $detail['html']
                            ?? ''
                        );
                        $reconstructed['body'] = $reconstructed['html_body'] !== ''
                            ? $reconstructed['html_body']
                            : ($detail['textBody'] ?? $detail['text'] ?? ($item['body'] ?? ''));
                        $reconstructed['direction'] = $detail['direction']
                            ?? data_get($detail, 'meta.direction')
                            ?? data_get($item, 'meta.email.direction')
                            ?? ($item['direction'] ?? '');
                        $reconstructed['subject'] = $detail['subject']
                            ?? data_get($item, 'meta.email.subject')
                            ?? ($item['subject'] ?? '');
                        $reconstructed['status'] = $detail['status'] ?? ($item['status'] ?? '');
                        $reconstructed['from'] = $detail['from']
                            ?? $detail['fromEmail']
                            ?? data_get($detail, 'sender.email')
                            ?? ($item['from'] ?? '');
                        $reconstructed['fromName'] = $detail['fromName']
                            ?? data_get($detail, 'sender.name')
                            ?? ($item['fromName'] ?? '');
                        $reconstructed['to'] = $detail['to']
                            ?? $detail['toEmail']
                            ?? data_get($detail, 'recipients')
                            ?? ($item['to'] ?? '');
                        $reconstructed['dateAdded'] = $detail['dateAdded']
                            ?? $detail['createdAt']
                            ?? $detail['timestamp']
                            ?? ($item['dateAdded'] ?? $item['createdAt'] ?? null);
                        $reconstructed['attachments'] = $detail['attachments']
                            ?? ($item['attachments'] ?? []);

                        $expanded[] = $this->transformConversationMessage($reconstructed);
                    }

                    // Keep the generic row only when no individual email detail could
                    // be reconstructed. This preserves compatibility without creating
                    // an outbound duplicate beside the expanded emails.
                    return $expanded !== []
                        ? $expanded
                        : [$this->transformConversationMessage($item)];
                })
                ->filter(fn ($item): bool => is_array($item) && filled($item['id'] ?? null))
                ->unique(fn (array $item): string => (string) ($item['id'] ?? md5(json_encode($item) ?: '')))
                ->sortBy(function (array $item): int {
                    $value = $item['created_at'] ?? null;

                    if (is_numeric($value)) {
                        $number = (int) $value;
                        return $number > 9999999999 ? (int) floor($number / 1000) : $number;
                    }

                    try {
                        return $value ? \Illuminate\Support\Carbon::parse($value)->getTimestamp() : 0;
                    } catch (\Throwable) {
                        return 0;
                    }
                })
                ->values()
                ->all();

            return [
                'success' => true,
                'messages' => $messages,
                // Pagination must continue using the generic conversation-message
                // cursor, not an individual email ID generated during expansion.
                'last_message_id' => collect($items)->last()['id'] ?? $lastMessageId,
                'has_more' => count($items) >= $params['limit'],
                'error' => null,
            ];
        }

        return ['success' => false, 'messages' => [], 'error' => 'Unable to load messages.', 'status' => $lastStatus, 'raw' => $lastData];
    }

    /**
     * Return every real email ID represented by a flattened conversation row.
     * HighLevel may provide one ID, an array of IDs, or alternate scalar fields.
     *
     * @return array<int, string>
     */
    protected function conversationEmailMessageIds(array $item): array
    {
        $rawIds = data_get($item, 'meta.email.messageIds', []);

        if (is_string($rawIds) || is_numeric($rawIds)) {
            $rawIds = [$rawIds];
        }

        if (! is_array($rawIds)) {
            $rawIds = [];
        }

        $rawIds[] = data_get($item, 'emailMessageId');
        $rawIds[] = data_get($item, 'email_message_id');
        $rawIds[] = data_get($item, 'emailMessage.id');

        return collect($rawIds)
            ->map(fn ($id): string => is_scalar($id) ? trim((string) $id) : '')
            ->filter(fn (string $id): bool => $id !== '' && ! str_contains($id, 'Over 9 levels deep'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Log the untouched responses returned by GHL's individual email-message API.
     *
     * This method is intentionally diagnostic-only. It does not merge, normalize,
     * sanitize, cache, or render the returned data. The bearer token is never logged.
     * Set GHL_RAW_EMAIL_LOG_LIMIT in the environment to control how many unique
     * email IDs are requested per conversation load (default: 20, maximum: 100).
     */
    protected function logRawConversationEmailDetails(
        string $token,
        string $conversationId,
        array $conversationMessages,
        string $version = '2021-04-15',
    ): void {
        $limit = min(max((int) env('GHL_RAW_EMAIL_LOG_LIMIT', 20), 0), 100);

        if ($limit === 0 || $conversationMessages === []) {
            return;
        }

        $seenEmailIds = [];
        $logged = 0;

        foreach ($conversationMessages as $message) {
            if (! is_array($message) || $logged >= $limit) {
                break;
            }

            $conversationMessageId = trim((string) ($message['id'] ?? ''));
            $messageType = strtoupper(trim((string) ($message['messageType'] ?? $message['message_type'] ?? '')));
            $contentType = strtolower(trim((string) ($message['contentType'] ?? $message['content_type'] ?? '')));

            $rawEmailIds = data_get($message, 'meta.email.messageIds', []);
            if (is_string($rawEmailIds)) {
                $rawEmailIds = [$rawEmailIds];
            }
            if (! is_array($rawEmailIds)) {
                $rawEmailIds = [];
            }

            $emailIds = collect($rawEmailIds)
                ->flatten()
                ->filter(fn ($value): bool => is_scalar($value))
                ->map(fn ($value): string => trim((string) $value))
                ->filter(fn (string $value): bool => $value !== '' && ! str_contains($value, 'Over 9 levels deep'))
                ->unique()
                ->values()
                ->all();

            if ($emailIds === [] && $messageType !== 'TYPE_EMAIL' && ! str_contains($contentType, 'html')) {
                continue;
            }

            foreach ($emailIds as $emailMessageId) {
                if ($logged >= $limit || isset($seenEmailIds[$emailMessageId])) {
                    continue;
                }

                $seenEmailIds[$emailMessageId] = true;
                $logged++;

                $url = "{$this->baseUrl}/conversations/messages/email/" . rawurlencode($emailMessageId);
                $startedAt = microtime(true);

                try {
                    $response = Http::withHeaders(['Version' => $version ?: '2021-04-15'])
                        ->timeout((int) config('ghl.timeout', 20))
                        ->withToken($token)
                        ->acceptJson()
                        ->get($url);

                    $rawBody = $response->body();

                    Log::info('GHL RAW individual email message API response', [
                        'diagnostic_marker' => 'GHL_RAW_EMAIL_MESSAGE_V1',
                        'request' => [
                            'method' => 'GET',
                            'url' => $url,
                            'conversation_id' => $conversationId,
                            'conversation_message_id' => $conversationMessageId,
                            'email_message_id' => $emailMessageId,
                            'version' => $version,
                        ],
                        'response' => [
                            'status' => $response->status(),
                            'successful' => $response->successful(),
                            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                            'content_type' => $response->header('Content-Type'),
                            'raw_body_length' => strlen($rawBody),
                            // This is the exact response body returned by GHL before
                            // any application-side processing. Inspect this field first.
                            'raw_body' => $rawBody,
                        ],
                    ]);

                    // When the email-specific endpoint fails, log the untouched generic
                    // message-detail endpoint too. This provides a direct comparison and
                    // may reveal where GHL exposes the full body for this account/version.
                    if ($response->failed() && $conversationMessageId !== '') {
                        $fallbackUrl = "{$this->baseUrl}/conversations/messages/" . rawurlencode($conversationMessageId);
                        $fallbackStartedAt = microtime(true);
                        $fallback = Http::withHeaders(['Version' => $version ?: '2021-04-15'])
                            ->timeout((int) config('ghl.timeout', 20))
                            ->withToken($token)
                            ->acceptJson()
                            ->get($fallbackUrl);
                        $fallbackRawBody = $fallback->body();

                        Log::info('GHL RAW generic message detail API response', [
                            'diagnostic_marker' => 'GHL_RAW_GENERIC_MESSAGE_DETAIL_V1',
                            'request' => [
                                'method' => 'GET',
                                'url' => $fallbackUrl,
                                'conversation_id' => $conversationId,
                                'conversation_message_id' => $conversationMessageId,
                                'email_message_id' => $emailMessageId,
                                'version' => $version,
                            ],
                            'response' => [
                                'status' => $fallback->status(),
                                'successful' => $fallback->successful(),
                                'duration_ms' => (int) round((microtime(true) - $fallbackStartedAt) * 1000),
                                'content_type' => $fallback->header('Content-Type'),
                                'raw_body_length' => strlen($fallbackRawBody),
                                'raw_body' => $fallbackRawBody,
                            ],
                        ]);
                    }
                } catch (\Throwable $exception) {
                    Log::error('GHL raw individual email message diagnostic failed', [
                        'diagnostic_marker' => 'GHL_RAW_EMAIL_MESSAGE_V1',
                        'conversation_id' => $conversationId,
                        'conversation_message_id' => $conversationMessageId,
                        'email_message_id' => $emailMessageId,
                        'version' => $version,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            }
        }

        Log::info('GHL raw email message diagnostic completed', [
            'diagnostic_marker' => 'GHL_RAW_EMAIL_MESSAGE_SUMMARY_V1',
            'conversation_id' => $conversationId,
            'requested_unique_email_count' => $logged,
            'configured_limit' => $limit,
        ]);
    }

    protected function trackedPlainTextFromHtml(string $html): string
    {
        $html = preg_replace_callback(
            '/<a\b[^>]*\bhref=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/isu',
            static function (array $matches): string {
                $href = trim(html_entity_decode((string) ($matches[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $label = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($matches[2] ?? ''))) ?? '');

                if ($href === '') {
                    return $label;
                }

                if ($label === '' || filter_var($label, FILTER_VALIDATE_URL)) {
                    return $href;
                }

                return $label . ': ' . $href;
            },
            $html,
        ) ?? $html;

        $html = preg_replace('/<\s*br\s*\/?>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/(p|div|li|h[1-6]|blockquote|tr)>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }


    protected function normalizeEmailRecipientList(mixed $value): array
    {
        $items = [];

        if (is_array($value)) {
            foreach ($value as $entry) {
                if (is_array($entry)) {
                    $entry = $entry['email'] ?? $entry['address'] ?? $entry['value'] ?? '';
                }

                foreach (preg_split('/[,;\s]+/', (string) $entry, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $email) {
                    $email = strtolower(trim($email));

                    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $items[$email] = $email;
                    }
                }
            }
        } else {
            foreach (preg_split('/[,;\s]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $email) {
                $email = strtolower(trim($email));

                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $items[$email] = $email;
                }
            }
        }

        return array_values($items);
    }

    protected function emailRecipientListFromPayload(array $payload, array $keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                $emails = $this->normalizeEmailRecipientList($payload[$key]);

                if ($emails !== []) {
                    return $emails;
                }
            }
        }

        return [];
    }

    public function sendEmailMessageForUser(User $user, array $payload): array
    {
        $credentials = $this->credentialsForUser($user);
        $locationId = $credentials['location_id'];
        $token = $this->tokenForLocation($locationId, $credentials['token_override']);

        if (! $locationId || ! $token) {
            return ['success' => false, 'error' => 'Missing recruiting data connection.'];
        }

        $contactId = $payload['contact_id'] ?? $payload['contactId'] ?? null;
        $conversationId = $payload['conversation_id'] ?? $payload['conversationId'] ?? null;
        $subject = trim((string) ($payload['subject'] ?? ''));
        $html = (string) ($payload['body'] ?? $payload['html'] ?? '');
        $text = trim((string) ($payload['text'] ?? strip_tags($html)));
        $to = $payload['to'] ?? $payload['emailTo'] ?? null;
        $fromName = trim((string) ($payload['fromName'] ?? $payload['senderName'] ?? $user->name ?? 'PLYRCard'));
        $ccEmails = $this->emailRecipientListFromPayload($payload, ['emailCc', 'cc_emails', 'cc', 'ccEmails']);
        $bccEmails = $this->emailRecipientListFromPayload($payload, ['emailBcc', 'bcc_emails', 'bcc', 'bccEmails']);
        $skipInternalSentTracking = (bool) ($payload['skip_internal_sent_tracking'] ?? false);
        $fromEmail = trim((string) ($payload['fromEmail'] ?? $payload['emailFrom'] ?? ''));
        $attachments = collect($payload['attachments'] ?? [])
            ->filter(fn ($attachment): bool => is_array($attachment) && filled($attachment['url'] ?? null))
            ->map(fn (array $attachment): array => [
                'name' => trim((string) ($attachment['name'] ?? basename((string) ($attachment['url'] ?? 'Attachment')))),
                'url' => trim((string) ($attachment['url'] ?? '')),
                'mime_type' => $attachment['mime_type'] ?? $attachment['mimeType'] ?? null,
                'size' => $attachment['size'] ?? null,
            ])
            ->values()
            ->all();

        if (! empty($attachments)) {
            $attachmentLinks = collect($attachments)->map(function (array $attachment): string {
                return '<li style="margin:6px 0"><a href="' . e((string) $attachment['url']) . '" target="_blank" rel="noopener noreferrer">' . e((string) ($attachment['name'] ?: 'Attachment')) . '</a></li>';
            })->implode('');

            if ($attachmentLinks !== '' && ! str_contains($html, 'data-plyrcard-attachments="1"')) {
                $html .= '<div data-plyrcard-attachments="1" style="margin-top:22px;padding-top:14px;border-top:1px solid #e5e7eb;font-family:Arial,Helvetica,sans-serif"><div style="font-weight:700;margin-bottom:8px;color:#111827">Attachments</div><ul style="margin:0;padding-left:18px">' . $attachmentLinks . '</ul></div>';
                $text = $this->trackedPlainTextFromHtml($html);
            }
        }

        if ($fromEmail === '') {
            $fromEmail = $this->defaultSenderEmailForUser($user);
        }

        if (! $contactId && $to) {
            $contactId = $this->findContactIdByEmail((string) $to, $locationId, $credentials['token_override']);
        }

        if (! $contactId) {
            return ['success' => false, 'error' => 'Contact id not found for this coach. Open a coach row with a valid email/contact first.'];
        }

        if ($subject === '' || trim(strip_tags($html)) === '') {
            return ['success' => false, 'error' => 'Subject and message are required.'];
        }

        $trackingContext = array_merge((array) ($payload['tracking_context'] ?? []), [
            'athlete_id' => $user->id,
            'athlete_name' => (string) ($user->name ?? ''),
            'athlete_email' => (string) ($user->email ?? ''),
            'contact_id' => (string) $contactId,
            'ghl_contact_id' => (string) $contactId,
            'email_subject' => $subject,
            'source' => (string) ($payload['tracking_source'] ?? $payload['source'] ?? 'coach_database_email'),
            'from_name' => $fromName,
            'from_email' => $fromEmail,
        ]);

        foreach ([
            'website_url', 'profile_url', 'public_profile_url', 'athlete_profile_url', 'plyrcard_url',
            'instagram_url', 'instagram', 'youtube_url', 'youtube', 'x_url', 'twitter_url', 'x', 'twitter',
        ] as $trackingKey) {
            if (! isset($trackingContext[$trackingKey]) && filled($payload[$trackingKey] ?? null)) {
                $trackingContext[$trackingKey] = $payload[$trackingKey];
            }
        }

        try {
            $html = app(TrackingLinkRewriter::class)->prepareTrackedEmailHtml($html, $user, $trackingContext);
            $text = $this->trackedPlainTextFromHtml($html);

            Log::info('Recruiting tracked email body prepared.', [
                'contact_id' => $contactId,
                'has_open_pixel' => str_contains($html, '/track/open/'),
                'tracked_link_count' => substr_count($html, '/track/click/'),
                'tracked_profile_count' => substr_count($html, '/track/profile/'),
                'text_has_tracked_profile' => str_contains($text, '/track/profile/'),
                'tracking_base_url' => rtrim((string) (config('services.tracking.base_url') ?: config('app.tracking_base_url') ?: env('TRACKING_BASE_URL') ?: config('app.url')), '/'),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Recruiting email signature/link tracking preparation failed. Sending original body.', [
                'contact_id' => $contactId,
                'error' => $exception->getMessage(),
            ]);
        }

        $officialPayload = [
            'type' => 'Email',
            'contactId' => $contactId,
            'subject' => $subject,
            'html' => $html,
            'message' => $text !== '' ? $text : trim(strip_tags($html)),
            'emailFrom' => $fromEmail,
            'fromName' => $fromName,
            'status' => 'delivered',
        ];

        if ($conversationId) {
            $officialPayload['conversationId'] = $conversationId;
        }

        if ($to) {
            $officialPayload['emailTo'] = $to;
        }

        if ($ccEmails !== []) {
            $officialPayload['emailCc'] = array_values($ccEmails);
        }

        if ($bccEmails !== []) {
            $officialPayload['emailBcc'] = array_values($bccEmails);
        }

        if (! empty($attachments)) {
            $officialPayload['attachments'] = collect($attachments)
                ->pluck('url')
                ->filter()
                ->values()
                ->all();
        }

        // BCC only works reliably on the current HighLevel Conversations v3 endpoint.
        // Older configured versions can return success while silently dropping emailBcc.
        $payloads = [$officialPayload];

        $legacyBase = array_filter([
            'locationId' => $locationId,
            'contactId' => $contactId,
            'conversationId' => $conversationId,
            'subject' => $subject,
            'html' => $html,
            'body' => $html,
            'message' => $html,
            'text' => $text,
            'emailTo' => $to,
            'emailCc' => $ccEmails !== [] ? array_values($ccEmails) : null,
            'emailBcc' => $bccEmails !== [] ? array_values($bccEmails) : null,
            'fromEmail' => $fromEmail,
            'emailFrom' => $fromEmail,
            'fromName' => $fromName,
            'senderName' => $fromName,
        ], fn ($value) => is_array($value) ? $value !== [] : filled($value));

        // Only use legacy fallbacks when there is no BCC. This prevents a false
        // "successful" send through an older API version that ignores BCC.
        if ($bccEmails === []) {
            $payloads[] = array_merge($legacyBase, ['type' => 'Email']);
            $payloads[] = array_merge($legacyBase, ['type' => 'TYPE_EMAIL']);
            $payloads[] = array_merge($legacyBase, ['messageType' => 'TYPE_EMAIL']);
        }

        $configuredVersion = trim((string) config('ghl.conversations_send_version'));
        $versions = ['v3'];

        if ($bccEmails === []) {
            foreach ([$configuredVersion, '2021-04-15', '2023-02-21'] as $version) {
                $version = trim((string) $version);

                if ($version !== '' && ! in_array($version, $versions, true)) {
                    $versions[] = $version;
                }
            }
        }

        Log::info('Recruiting email API payload recipients prepared.', [
            'contact_id' => $contactId,
            'to' => $to,
            'cc_count' => count($ccEmails),
            'bcc_count' => count($bccEmails),
            'uses_v3_only' => $bccEmails !== [],
            'has_email_cc' => $ccEmails !== [],
            'has_email_bcc' => $bccEmails !== [],
        ]);

        $lastError = null;
        $lastStatus = null;
        $lastData = null;

        $endpoints = array_filter(array_unique([
            config('ghl.conversations_send_endpoint'),
            '/conversations/messages',
            $conversationId ? "/conversations/{$conversationId}/messages" : null,
        ]));

        foreach ($versions as $version) {
            foreach ($endpoints as $endpoint) {
                foreach ($payloads as $body) {
                    $response = Http::withHeaders(['Version' => $version])
                        ->timeout((int) config('ghl.timeout', 20))
                        ->withToken($token)
                        ->acceptJson()
                        ->asJson()
                        ->post("{$this->baseUrl}{$endpoint}", $body);

                    $data = $response->json() ?? [];

                    if ($response->successful()) {
                        if (! $skipInternalSentTracking) {
                            try {
                                Log::info('Recruiting email send succeeded. Incrementing email_sent_count.', [
                                'contact_id' => $contactId,
                                'to' => $to,
                                'subject' => $subject,
                                'endpoint' => $endpoint,
                                'version' => $version,
                            ]);

                            $trackingResult = $this->trackRecruitingEmailSentForUser($user, (string) $contactId, [
                                'source' => 'coach_database_email_send_service',
                                'subject' => $subject,
                                'to' => $to,
                                'host' => request()?->getHost(),
                                'sent_at' => now()->toIso8601String(),
                                'message_id' => (string) ($data['messageId'] ?? $data['id'] ?? data_get($data, 'message.id') ?? ''),
                            ]);

                            Log::info('Recruiting email_sent_count increment attempted after send.', [
                                'contact_id' => $contactId,
                                'tracked' => (bool) ($trackingResult['success'] ?? false),
                                'recipient_increments' => data_get($trackingResult, 'recipient.increments', []),
                                'athlete_increments' => data_get($trackingResult, 'athlete.increments', []),
                            ]);
                            } catch (\Throwable $exception) {
                                Log::warning('Recruiting email sent counter failed after send.', [
                                    'contact_id' => $contactId,
                                    'to' => $to,
                                    'subject' => $subject,
                                    'error' => $exception->getMessage(),
                                ]);
                            }
                        }

                        return ['success' => true, 'message' => $data['message'] ?? $data, 'raw' => $data];
                    }

                    $lastStatus = $response->status();
                    $lastData = $data;
                    $lastError = $data['message']
                        ?? $data['error']
                        ?? $data['msg']
                        ?? $response->body();
                }
            }
        }

        Log::error('Recruiting email send failed.', ['status' => $lastStatus, 'error' => $lastError, 'raw' => $lastData]);

        return [
            'success' => false,
            'error' => is_string($lastError) && trim($lastError) !== '' ? Str::limit(strip_tags($lastError), 180) : 'Unable to send email.',
            'status' => $lastStatus,
            'raw' => $lastData,
        ];
    }


    private function templateResponseCount(array $data): int
    {
        foreach (['templates', 'items', 'results', 'records', 'emails'] as $key) {
            $value = $data[$key] ?? data_get($data, 'data.' . $key) ?? data_get($data, 'emailTemplates.' . $key);
            if (is_array($value) && array_is_list($value)) {
                return count($value);
            }
        }

        $dataValue = $data['data'] ?? null;
        if (is_array($dataValue) && array_is_list($dataValue)) {
            return count($dataValue);
        }

        return count($this->extractTemplatesFromResponse($data));
    }

    private function decodeTemplateMaybeJson(mixed $value): mixed
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return $value;
            }

            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return $value;
    }

    public function getEmailTemplatesForUser(User $user): array
    {
        $credentials = $this->credentialsForUser($user);
        $locationId = $credentials['location_id'];
        $token = $this->tokenForLocation($locationId, $credentials['token_override']);

        if (! $locationId || ! $token) {
            return ['success' => false, 'templates' => [], 'error' => 'Missing recruiting data connection.'];
        }

        $templates = [];
        $sources = [];
        $errors = [];
        $debug = [];

        // First use the documented Email Templates API exactly. Extra sources are fallbacks only.
        $fetchers = [
            [
                'source' => 'Email Templates v3',
                'version' => 'v3',
                'url' => "{$this->baseUrl}/emails/locations/{$locationId}/templates",
                'page_param' => 'skip',
                'base_params' => ['limit' => 50],
                'page_size' => 50,
            ],
            [
                'source' => 'Email Templates v3 pageNumber',
                'version' => 'v3',
                'url' => "{$this->baseUrl}/emails/locations/{$locationId}/templates",
                'page_param' => 'pageNumber',
                'base_params' => ['pageLimit' => 50, 'limit' => 50],
                'page_size' => 50,
                'starts_at' => 1,
            ],
            [
                'source' => 'Email Templates v3 type=email',
                'version' => 'v3',
                'url' => "{$this->baseUrl}/emails/locations/{$locationId}/templates",
                'page_param' => 'skip',
                'base_params' => ['limit' => 50, 'type' => 'email'],
                'page_size' => 50,
            ],
            [
                'source' => 'Marketing email templates v2',
                'version' => '2023-02-21',
                'url' => "{$this->baseUrl}/emails/public/v2/locations/{$locationId}/campaigns/email-template",
                'page_param' => 'offset',
                'base_params' => ['limit' => 20],
                'page_size' => 20,
            ],
            [
                'source' => 'Marketing email templates v2 page',
                'version' => '2023-02-21',
                'url' => "{$this->baseUrl}/emails/public/v2/locations/{$locationId}/campaigns/email-template",
                'page_param' => 'page',
                'base_params' => ['limit' => 20, 'pageLimit' => 20],
                'page_size' => 20,
                'starts_at' => 1,
            ],
            [
                'source' => 'Marketing emails / campaigns v2',
                'version' => '2023-02-21',
                'url' => "{$this->baseUrl}/emails/public/v2/locations/{$locationId}/campaigns/email-campaign",
                'page_param' => 'offset',
                'base_params' => ['limit' => 20],
                'page_size' => 20,
            ],
            [
                'source' => 'All marketing emails v2',
                'version' => '2023-02-21',
                'url' => "{$this->baseUrl}/emails/public/v2/locations/{$locationId}/campaigns",
                'page_param' => 'offset',
                'base_params' => ['limit' => 20],
                'page_size' => 20,
            ],
            [
                'source' => 'Email schedule library',
                'version' => '2023-02-21',
                'url' => "{$this->baseUrl}/emails/schedule",
                'page_param' => 'skip',
                'base_params' => ['locationId' => $locationId, 'limit' => 50, 'showStats' => 'true'],
                'page_size' => 50,
            ],
            [
                'source' => 'Location templates',
                'version' => 'v3',
                'url' => "{$this->baseUrl}/locations/{$locationId}/templates",
                'page_param' => 'skip',
                'base_params' => ['limit' => 50, 'type' => 'email', 'originId' => $locationId],
                'page_size' => 50,
            ],
            [
                'source' => 'Generic email templates',
                'version' => '2023-02-21',
                'url' => "{$this->baseUrl}/emails/templates",
                'page_param' => 'skip',
                'base_params' => ['limit' => 50, 'locationId' => $locationId, 'originId' => $locationId],
                'page_size' => 50,
            ],
            [
                'source' => 'Builder templates',
                'version' => '2021-07-28',
                'url' => "{$this->baseUrl}/emails/builder",
                'page_param' => 'skip',
                'base_params' => ['limit' => 50, 'locationId' => $locationId, 'originId' => $locationId, 'archived' => false, 'templatesOnly' => false],
                'page_size' => 50,
            ],
            [
                'source' => 'Builder templates html type',
                'version' => '2021-07-28',
                'url' => "{$this->baseUrl}/emails/builder",
                'page_param' => 'skip',
                'base_params' => ['limit' => 50, 'locationId' => $locationId, 'originId' => $locationId, 'archived' => false, 'type' => 'html'],
                'page_size' => 50,
            ],
            [
                'source' => 'Builder templates legacy',
                'version' => '2021-04-15',
                'url' => "{$this->baseUrl}/emails/builder",
                'page_param' => 'skip',
                'base_params' => ['limit' => 50, 'locationId' => $locationId, 'originId' => $locationId, 'archived' => false, 'templatesOnly' => false],
                'page_size' => 50,
            ],
        ];

        foreach ($fetchers as $fetcher) {
            $offset = (int) ($fetcher['starts_at'] ?? 0);
            $sourceCount = 0;
            $rawSeen = 0;
            $page = 0;

            do {
                $params = array_merge($fetcher['base_params'], [$fetcher['page_param'] => $offset]);
                $response = Http::withHeaders(['Version' => $fetcher['version']])
                    ->timeout((int) config('ghl.timeout', 20))
                    ->withToken($token)
                    ->acceptJson()
                    ->get($fetcher['url'], $params);

                $data = $response->json() ?? [];

                if (! $response->successful()) {
                    $message = $data['message'] ?? $data['error'] ?? ($fetcher['source'] . ' failed with status ' . $response->status());
                    $errors[] = $message;
                    $debug[] = [
                        'source' => $fetcher['source'],
                        'status' => $response->status(),
                        'raw' => 0,
                        'usable' => 0,
                        'message' => is_string($message) ? Str::limit($message, 120) : 'Request failed',
                    ];
                    Log::warning('Recruiting email templates source failed.', [
                        'status' => $response->status(),
                        'source' => $fetcher['source'],
                        'location_id' => $locationId,
                        'params' => $params,
                        'raw' => $data,
                    ]);
                    break;
                }

                $items = $this->extractTemplatesFromResponse($data);
                $rawCount = $this->templateResponseCount($data);
                $rawSeen += $rawCount;

                $batch = collect($items)
                    ->filter(fn ($item): bool => is_array($item))
                    ->map(fn (array $item): array => array_merge($this->transformEmailTemplate($item), ['source' => $fetcher['source']]))
                    ->filter(fn (array $item): bool => filled($item['id'] ?? null) && filled($item['name'] ?? null))
                    ->values()
                    ->all();

                $sourceCount += count($batch);
                $templates = array_merge($templates, $batch);

                $total = (int) ($data['total'] ?? $data['totalCount'] ?? $data['count'] ?? data_get($data, 'meta.total') ?? data_get($data, 'pagination.total') ?? 0);
                $pageSize = (int) ($fetcher['page_size'] ?? 50);
                $page++;
                if (in_array(($fetcher['page_param'] ?? 'skip'), ['pageNumber', 'page'], true)) {
                    $offset++;
                    $hasMore = $rawCount >= $pageSize && ($total === 0 || (($offset - 1) * $pageSize) < $total);
                } else {
                    $offset += $pageSize;
                    $hasMore = $rawCount >= $pageSize && ($total === 0 || $offset < $total);
                }
            } while ($hasMore && $page < 100);

            $debug[] = [
                'source' => $fetcher['source'],
                'status' => 200,
                'raw' => $rawSeen,
                'usable' => $sourceCount,
                'message' => $sourceCount > 0 ? 'ok' : 'No usable templates returned',
            ];

            if ($sourceCount > 0) {
                $sources[] = $fetcher['source'] . ': ' . $sourceCount;
            }
        }

        $templates = collect($templates)
            ->unique(fn (array $item): string => (string) ($item['id'] ?? ''))
            ->sortByDesc(fn (array $item): string => (string) ($item['updatedAt'] ?? $item['updated_at'] ?? $item['createdAt'] ?? ''))
            ->values()
            ->all();

        return [
            'success' => true,
            'templates' => $templates,
            'source' => implode(', ', $sources) ?: 'No template source returned usable templates',
            'location_id' => $locationId,
            'debug' => $debug,
            'error' => null,
            'warnings' => $errors,
        ];
    }

    public function getEmailTemplateForUser(User $user, string $templateId): array
    {
        $credentials = $this->credentialsForUser($user);
        $locationId = $credentials['location_id'];
        $token = $this->tokenForLocation($locationId, $credentials['token_override']);
        $templateId = trim($templateId);

        if (! $locationId || ! $token) {
            return ['success' => false, 'template' => null, 'error' => 'Missing recruiting data connection.'];
        }

        if ($templateId === '') {
            return ['success' => false, 'template' => null, 'error' => 'Template id is required.'];
        }

        $summaryFallback = $this->findTemplateSummaryForDetail($user, $templateId);
        $candidateIds = $this->emailTemplateDetailCandidateIds($templateId, $summaryFallback ?: []);
        $errors = [];
        $bestTemplate = null;
        $bestRaw = [];
        $bestSource = '';

        foreach ($candidateIds as $candidateId) {
            $attempts = [
                [
                    'source' => '/emails/locations/{locationId}/templates/{templateId}',
                    'version' => 'v3',
                    'url' => "{$this->baseUrl}/emails/locations/{$locationId}/templates/{$candidateId}",
                    'params' => [],
                ],
                [
                    'source' => '/emails/builder/{templateId}',
                    'version' => '2021-07-28',
                    'url' => "{$this->baseUrl}/emails/builder/{$candidateId}",
                    'params' => ['locationId' => $locationId, 'originId' => $locationId],
                ],
                [
                    'source' => '/emails/builder/{templateId} v3',
                    'version' => 'v3',
                    'url' => "{$this->baseUrl}/emails/builder/{$candidateId}",
                    'params' => ['locationId' => $locationId, 'originId' => $locationId],
                ],
                [
                    'source' => '/emails/builder/{locationId}/{templateId}',
                    'version' => '2021-07-28',
                    'url' => "{$this->baseUrl}/emails/builder/{$locationId}/{$candidateId}",
                    'params' => [],
                ],
                [
                    'source' => '/emails/public/v2/locations/{locationId}/campaigns/email-template/{id}',
                    'version' => '2023-02-21',
                    'url' => "{$this->baseUrl}/emails/public/v2/locations/{$locationId}/campaigns/email-template/{$candidateId}",
                    'params' => [],
                ],
                [
                    'source' => '/emails/public/v2/locations/{locationId}/campaigns/email-campaign/{id}',
                    'version' => '2023-02-21',
                    'url' => "{$this->baseUrl}/emails/public/v2/locations/{$locationId}/campaigns/email-campaign/{$candidateId}",
                    'params' => [],
                ],
                [
                    'source' => '/emails/public/v2/locations/{locationId}/campaigns/{id}',
                    'version' => '2023-02-21',
                    'url' => "{$this->baseUrl}/emails/public/v2/locations/{$locationId}/campaigns/{$candidateId}",
                    'params' => [],
                ],
                [
                    'source' => '/locations/{locationId}/templates/{templateId}',
                    'version' => 'v3',
                    'url' => "{$this->baseUrl}/locations/{$locationId}/templates/{$candidateId}",
                    'params' => ['type' => 'email', 'originId' => $locationId],
                ],
                [
                    'source' => '/emails/templates/{id}',
                    'version' => '2023-02-21',
                    'url' => "{$this->baseUrl}/emails/templates/{$candidateId}",
                    'params' => ['locationId' => $locationId, 'originId' => $locationId],
                ],
            ];

            foreach ($attempts as $attempt) {
                $response = Http::withHeaders(['Version' => $attempt['version']])
                    ->timeout((int) config('ghl.timeout', 20))
                    ->withToken($token)
                    ->acceptJson()
                    ->get($attempt['url'], $attempt['params']);

                $data = $response->json() ?? [];

                if (! $response->successful()) {
                    $message = $data['message'] ?? $data['error'] ?? ($attempt['source'] . ' failed with status ' . $response->status());
                    $errors[] = $candidateId . ': ' . (is_scalar($message) ? (string) $message : $attempt['source'] . ' failed');
                    continue;
                }

                $template = $data['template']
                    ?? data_get($data, 'data.template')
                    ?? $data['emailTemplate']
                    ?? data_get($data, 'data.emailTemplate')
                    ?? data_get($data, 'data.email')
                    ?? data_get($data, 'data.campaign')
                    ?? data_get($data, 'campaign')
                    ?? $data['data']
                    ?? $data;

                $transformed = $this->transformEmailTemplate(is_array($template) ? $template : $data);
                $transformed['id'] = (string) ($transformed['id'] ?? $templateId) ?: $templateId;
                $transformed['source'] = $attempt['source'];
                $transformed['detail_candidate_id'] = $candidateId;

                // Some builder templates store their actual HTML in sibling keys or deeply
                // inside builder JSON rather than under the top-level template object.
                $rawHtml = $this->extractTemplateHtmlFromKnownFields($data);
                if ($rawHtml !== '' && trim((string) ($transformed['html'] ?? '')) === '') {
                    $transformed['html'] = $rawHtml;
                    $transformed['body'] = $rawHtml;
                    $transformed['rawHtmlExtracted'] = true;
                }

                if (trim((string) ($transformed['html'] ?? '')) === '' && is_array($summaryFallback)) {
                    $summaryHtml = $this->extractTemplateHtmlFromKnownFields($summaryFallback);
                    if ($summaryHtml === '') {
                        $summaryHtml = $this->extractTemplateHtmlFromKnownFields($summaryFallback['raw'] ?? []);
                    }
                    if ($summaryHtml !== '') {
                        $transformed['html'] = $summaryHtml;
                        $transformed['body'] = $summaryHtml;
                        $transformed['summaryHtmlExtracted'] = true;
                    }
                }

                if (trim((string) ($transformed['html'] ?? '')) === '') {
                    $renderHtml = $this->fetchTemplateRenderHtml($transformed, $data);
                    if ($renderHtml !== '') {
                        $transformed['html'] = $renderHtml;
                        $transformed['body'] = $renderHtml;
                        $transformed['renderedHtml'] = $renderHtml;
                        $transformed['editorContentFetched'] = true;
                    }
                }

                if (trim((string) ($transformed['html'] ?? '')) !== '') {
                    if (is_array($summaryFallback)) {
                        $transformed = array_merge($summaryFallback, array_filter($transformed, function ($value): bool {
                            return ! (is_string($value) && trim($value) === '') && ! (is_array($value) && empty($value));
                        }));
                        $transformed['id'] = $templateId;
                    }

                    return [
                        'success' => true,
                        'template' => $transformed,
                        'raw' => $data,
                        'source' => $attempt['source'],
                        'warnings' => $errors,
                    ];
                }

                if (is_null($bestTemplate) || trim((string) ($bestTemplate['subjectLine'] ?? '')) === '') {
                    $bestTemplate = $transformed;
                    $bestRaw = $data;
                    $bestSource = $attempt['source'];
                }
            }
        }

        if (is_array($summaryFallback)) {
            $summaryHtml = $this->extractTemplateHtmlFromKnownFields($summaryFallback);
            if ($summaryHtml === '') {
                $summaryHtml = $this->extractTemplateHtmlFromKnownFields($summaryFallback['raw'] ?? []);
            }
            if ($summaryHtml === '') {
                $summaryHtml = $this->fetchTemplateRenderHtml($summaryFallback, $summaryFallback['raw'] ?? $summaryFallback);
            }

            if ($summaryHtml !== '') {
                $summaryFallback['html'] = $summaryHtml;
                $summaryFallback['body'] = $summaryHtml;
                $summaryFallback['renderedHtml'] = $summaryHtml;
            }

            if (is_array($bestTemplate)) {
                $summaryFallback = array_merge($bestTemplate, array_filter($summaryFallback, function ($value): bool {
                    return ! (is_string($value) && trim($value) === '') && ! (is_array($value) && empty($value));
                }));
            }

            return [
                'success' => true,
                'template' => $summaryFallback,
                'raw' => $summaryFallback['raw'] ?? $bestRaw,
                'source' => ($summaryFallback['source'] ?? 'summary fallback') . ' + summary/body fallback',
                'warnings' => $errors,
            ];
        }

        if (is_array($bestTemplate)) {
            return [
                'success' => true,
                'template' => $bestTemplate,
                'raw' => $bestRaw,
                'source' => $bestSource,
                'warnings' => $errors,
            ];
        }

        Log::error('Recruiting email template detail request failed.', [
            'location_id' => $locationId,
            'template_id' => $templateId,
            'candidate_ids' => $candidateIds,
            'errors' => $errors,
        ]);

        return [
            'success' => false,
            'template' => null,
            'error' => $errors[0] ?? 'Unable to load template detail.',
            'raw' => [],
        ];
    }

    private function emailTemplateDetailCandidateIds(string $templateId, array $summary = []): array
    {
        $values = [$templateId];

        foreach ([
            'id', '_id', 'templateId', 'template_id', 'campaignId', 'campaign_id', 'emailCampaignId', 'email_campaign_id',
            'builderId', 'builder_id', 'contentId', 'content_id', 'emailId', 'email_id',
            'raw.id', 'raw._id', 'raw.templateId', 'raw.template_id', 'raw.campaignId', 'raw.campaign_id', 'raw.emailCampaignId', 'raw.email_campaign_id',
            'raw.builderId', 'raw.builder_id', 'raw.contentId', 'raw.content_id', 'raw.emailId', 'raw.email_id',
            'raw.data.id', 'raw.data._id', 'raw.data.templateId', 'raw.data.campaignId', 'raw.data.builderId', 'raw.data.contentId',
            'raw.template.id', 'raw.template._id', 'raw.template.templateId', 'raw.template.campaignId', 'raw.email.id', 'raw.email._id',
        ] as $path) {
            $value = data_get($summary, $path);
            if (is_scalar($value)) {
                $values[] = (string) $value;
            }
        }

        return collect($values)
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '' && ! in_array(strtolower($value), ['null', 'undefined'], true))
            ->unique()
            ->values()
            ->all();
    }

    private function findTemplateSummaryForDetail(User $user, string $templateId): ?array
    {
        $list = $this->getEmailTemplatesForUser($user);

        return collect($list['templates'] ?? [])
            ->filter(fn ($template): bool => is_array($template))
            ->first(function (array $template) use ($templateId): bool {
                return (string) ($template['id'] ?? '') === $templateId
                    || (string) ($template['templateId'] ?? '') === $templateId
                    || (string) ($template['campaignId'] ?? '') === $templateId
                    || (string) data_get($template, 'raw.id') === $templateId
                    || (string) data_get($template, 'raw._id') === $templateId
                    || (string) data_get($template, 'raw.templateId') === $templateId
                    || (string) data_get($template, 'raw.campaignId') === $templateId;
            });
    }

    private function fetchTemplateRenderHtml(array $template, array $raw): string
    {
        $urls = [];

        foreach ([
            $template['editorContentUrl'] ?? null,
            $template['editor_content_url'] ?? null,
            $template['editorUrl'] ?? null,
            $template['contentUrl'] ?? null,
            $template['previewUrl'] ?? null,
            $template['templateDataUrl'] ?? null,
            $template['designUrl'] ?? null,
            $template['builderUrl'] ?? null,
            data_get($raw, 'editorContentUrl'),
            data_get($raw, 'editor_content_url'),
            data_get($raw, 'editorUrl'),
            data_get($raw, 'contentUrl'),
            data_get($raw, 'data.editorContentUrl'),
            data_get($raw, 'data.editor_content_url'),
            data_get($raw, 'data.editorUrl'),
            data_get($raw, 'data.contentUrl'),
            data_get($raw, 'template.editorContentUrl'),
            data_get($raw, 'template.editorUrl'),
            data_get($raw, 'email.editorContentUrl'),
            data_get($raw, 'email.contentUrl'),
            data_get($raw, 'editor.contentUrl'),
            data_get($raw, 'editor.editorContentUrl'),
            data_get($raw, 'builder.editorContentUrl'),
            data_get($raw, 'templateData.editorContentUrl'),
            data_get($raw, 'previewUrl'),
            data_get($raw, 'preview_url'),
            data_get($raw, 'url'),
            data_get($raw, 'data.previewUrl'),
            data_get($raw, 'data.url'),
            data_get($raw, 'template.previewUrl'),
            data_get($raw, 'templateDataUrl'),
            data_get($raw, 'data.templateDataUrl'),
            data_get($raw, 'designUrl'),
            data_get($raw, 'data.designUrl'),
        ] as $url) {
            $url = trim((string) $url);
            if ($url !== '' && Str::startsWith($url, ['http://', 'https://'])) {
                $urls[] = $url;
            }
        }

        foreach (array_values(array_unique($urls)) as $url) {
            $html = $this->fetchTemplatePreviewUrl($url);
            if ($html !== '') {
                return $html;
            }
        }

        return '';
    }

    private function fetchTemplatePreviewUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '' || ! Str::startsWith($url, ['http://', 'https://'])) {
            return '';
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'PLYRCard Recruiting Center',
                'Accept' => 'text/html,application/xhtml+xml,text/plain,*/*',
            ])->timeout((int) config('ghl.timeout', 20))->get($url);
            if ($response->successful()) {
                $body = trim((string) $response->body());
                if ($body !== '' && ! $this->looksLikeIdentifier($body)) {
                    return $body;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Recruiting email template preview URL fetch failed.', ['url' => $url, 'error' => $e->getMessage()]);
        }

        return '';
    }


    public function uploadMediaForUser(User $user, mixed $file): array
    {
        $credentials = $this->credentialsForUser($user);
        $locationId = $credentials['location_id'];
        $token = $this->tokenForLocation($locationId, $credentials['token_override']);

        if (! $locationId || ! $token) {
            return ['success' => false, 'error' => 'Missing recruiting data connection.'];
        }

        if (! is_object($file) || ! method_exists($file, 'getRealPath')) {
            return ['success' => false, 'error' => 'Choose a valid file.'];
        }

        $path = (string) $file->getRealPath();
        if ($path === '' || ! is_file($path)) {
            return ['success' => false, 'error' => 'File upload could not be read.'];
        }

        $name = method_exists($file, 'getClientOriginalName')
            ? (string) $file->getClientOriginalName()
            : ('plyrcard-upload-' . now()->format('YmdHis'));

        $mimeType = method_exists($file, 'getMimeType') ? (string) $file->getMimeType() : 'image/jpeg';
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($extension === '') {
            $name .= match ($mimeType) {
                'image/png' => '.png',
                'image/gif' => '.gif',
                'image/webp' => '.webp',
                'application/pdf' => '.pdf',
                'video/mp4' => '.mp4',
                'application/msword' => '.doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '.docx',
                'application/vnd.ms-excel' => '.xls',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => '.xlsx',
                'text/plain' => '.txt',
                default => '.bin',
            };
        }

        $endpoints = collect([
            config('ghl.media_upload_endpoint'),
            '/medias/upload-file',
            '/medias/upload',
        ])->filter()->map(fn ($endpoint): string => '/' . ltrim((string) $endpoint, '/'))->unique()->values()->all();

        $versions = collect([
            config('ghl.media_upload_version'),
            '2021-07-28',
            '2023-02-21',
            'v3',
        ])->filter()->unique()->values()->all();

        // HighLevel media upload requirements differ between account/API versions.
        // The location-scoped payload with altId/altType is the important part for
        // making the uploaded image appear in that location's Recruiting Center media library.
        $payloads = [[
            'altId' => $locationId,
            'altType' => 'location',
            'locationId' => $locationId,
            'hosted' => true,
            'name' => $name,
        ], [
            'altId' => $locationId,
            'altType' => 'location',
            'hosted' => true,
            'name' => $name,
        ], [
            'locationId' => $locationId,
            'hosted' => true,
            'name' => $name,
        ], [
            'altId' => $locationId,
            'altType' => 'location',
            'hosted' => false,
            'name' => $name,
        ]];

        $lastError = 'Unable to upload file.';
        $lastStatus = null;
        $lastRaw = [];
        $attempts = [];

        foreach ($endpoints as $endpoint) {
            foreach ($versions as $version) {
                foreach ($payloads as $payload) {
                    try {
                        $handle = fopen($path, 'r');
                        if (! $handle) {
                            return ['success' => false, 'error' => 'File upload could not be opened.'];
                        }

                        $response = Http::withHeaders(['Version' => $version])
                            ->timeout((int) config('ghl.timeout', 30))
                            ->withToken($token)
                            ->acceptJson()
                            ->attach('file', $handle, $name, ['Content-Type' => $mimeType])
                            ->post("{$this->baseUrl}{$endpoint}", $payload);

                        if (is_resource($handle)) {
                            fclose($handle);
                        }

                        $data = $response->json();
                        if (! is_array($data)) {
                            $data = [];
                        }

                        $lastStatus = $response->status();
                        $lastRaw = $data;
                        $attempts[] = [
                            'endpoint' => $endpoint,
                            'version' => $version,
                            'status' => $lastStatus,
                            'payload_keys' => array_keys($payload),
                        ];

                        if (! $response->successful()) {
                            $lastError = $this->extractApiErrorMessage($data, 'Unable to upload file.');
                            continue;
                        }

                        $url = $this->extractMediaUploadUrl($data);

                        if ($url === '') {
                            $lastError = 'File uploaded to Recruiting Center media, but no public URL was returned.';
                            continue;
                        }

                        return [
                            'success' => true,
                            'id' => $this->extractMediaUploadId($data),
                            'url' => $url,
                            'raw' => $data,
                            'version' => $version,
                            'endpoint' => $endpoint,
                        ];
                    } catch (\Throwable $e) {
                        if (isset($handle) && is_resource($handle)) {
                            fclose($handle);
                        }

                        $lastError = $e->getMessage() ?: 'Unable to upload image.';
                        $attempts[] = [
                            'endpoint' => $endpoint,
                            'version' => $version,
                            'status' => 'exception',
                            'error' => $lastError,
                        ];
                    }
                }
            }
        }

        Log::error('Recruiting Center media/file upload failed.', [
            'location_id' => $locationId,
            'status' => $lastStatus,
            'error' => $lastError,
            'attempts' => $attempts,
            'raw' => $lastRaw,
        ]);

        return [
            'success' => false,
            'error' => $lastError,
            'status' => $lastStatus,
            'raw' => $lastRaw,
            'debug' => $attempts,
        ];
    }

    private function extractMediaUploadId(array $data): ?string
    {
        foreach ([
            'id', '_id', 'mediaId', 'media_id', 'fileId', 'file_id',
            'file.id', 'file._id', 'file.mediaId',
            'data.id', 'data._id', 'data.mediaId', 'data.fileId',
            'uploadedFiles.0.id', 'uploadedFiles.0._id', 'uploadedFiles.0.mediaId',
            'files.0.id', 'files.0._id', 'files.0.mediaId',
        ] as $key) {
            $value = data_get($data, $key);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function extractMediaUploadUrl(array $data): string
    {
        $candidates = [
            $data['url'] ?? null,
            $data['fileUrl'] ?? null,
            $data['mediaUrl'] ?? null,
            data_get($data, 'file.url'),
            data_get($data, 'file.fileUrl'),
            data_get($data, 'data.url'),
            data_get($data, 'data.fileUrl'),
            data_get($data, 'uploadedFiles.0.url'),
            data_get($data, 'uploadedFiles.0.fileUrl'),
            data_get($data, 'uploadedFiles.0.mediaUrl'),
            data_get($data, 'uploadedFiles.0.downloadUrl'),
            data_get($data, 'uploadedFiles.0.locationUrl'),
            data_get($data, 'files.0.url'),
            data_get($data, 'files.0.fileUrl'),
            data_get($data, 'files.0.mediaUrl'),
            data_get($data, 'files.0.downloadUrl'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        $flat = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($data));
        foreach ($flat as $value) {
            if (is_string($value) && preg_match('/^https?:\/\//i', $value)) {
                return trim($value);
            }
        }

        return '';
    }

    public function createEmailTemplateForUser(User $user, string $name, string $subject, string $body, string $previewText = ''): array
    {
        return $this->saveSimpleEmailTemplateForUser($user, null, $name, $subject, $body, $previewText);
    }

    public function updateEmailTemplateForUser(User $user, string $templateId, string $name, string $subject, string $body, string $previewText = ''): array
    {
        return $this->saveSimpleEmailTemplateForUser($user, $templateId, $name, $subject, $body, $previewText);
    }

    private function saveSimpleEmailTemplateForUser(User $user, ?string $templateId, string $name, string $subject, string $body, string $previewText = ''): array
    {
        $credentials = $this->credentialsForUser($user);
        $locationId = $credentials['location_id'];
        $token = $this->tokenForLocation($locationId, $credentials['token_override']);
        $templateId = trim((string) $templateId);

        if (! $locationId || ! $token) {
            return ['success' => false, 'error' => 'Missing recruiting data connection.'];
        }

        $name = trim($name) !== '' ? trim($name) : 'Untitled Template';
        $subject = trim($subject);
        $body = trim($body);
        $previewText = trim($previewText) !== '' ? trim($previewText) : Str::limit(trim(strip_tags($body)), 120, '');

        $fromName = (string) ($user->name ?? 'PLYRCard');
        $fromEmail = $this->defaultSenderEmailForUser($user);
        $updatedBy = (string) (data_get($user, 'ghl_user_id') ?: data_get($user, 'id') ?: 'plyrcard');

        $basePayload = [
            'locationId' => $locationId,
            'originId' => $locationId,
            'updatedBy' => $updatedBy,
            'name' => $name,
            'title' => $name,
            'subject' => $subject,
            'subjectLine' => $subject,
            'previewText' => $previewText,
            'fromName' => $fromName,
            'fromEmail' => $fromEmail,
            'editorType' => 'html',
            'type' => 'html',
            'builderVersion' => '2',
            'editorContent' => $body,
            'html' => $body,
            'body' => $body,
            'content' => $body,
            'emailContent' => $body,
            'htmlContent' => $body,
            'isPlainText' => false,
            'archived' => false,
        ];

        $request = Http::withHeaders(['Version' => '2023-02-21'])
            ->timeout((int) config('ghl.timeout', 20))
            ->withToken($token)
            ->acceptJson()
            ->asJson();

        $lastError = $templateId !== '' ? 'Unable to update template.' : 'Unable to create template.';
        $lastStatus = null;
        $lastData = [];
        $attemptDebug = [];
        $createdViaBuilder = false;

        if ($templateId === '') {
            $createPayloads = [
                $basePayload,
                array_merge($basePayload, [
                    'template' => [
                        'name' => $name,
                        'title' => $name,
                        'subjectLine' => $subject,
                        'previewText' => $previewText,
                        'html' => $body,
                        'body' => $body,
                    ],
                ]),
            ];

            foreach ($createPayloads as $createPayload) {
                $createResponse = $request->post("{$this->baseUrl}/emails/builder", $createPayload);
                $createData = $createResponse->json();
                if (! is_array($createData)) {
                    $createData = [];
                }

                $attemptDebug[] = [
                    'method' => 'POST',
                    'url' => '/emails/builder',
                    'status' => $createResponse->status(),
                ];

                if ($createResponse->successful()) {
                    $templateId = (string) ($createData['id'] ?? $createData['_id'] ?? $createData['templateId'] ?? data_get($createData, 'data.id') ?? data_get($createData, 'template.id') ?? $createData['redirect'] ?? '');
                    $createdViaBuilder = true;
                    $lastData = $createData;
                    $lastStatus = $createResponse->status();

                    if ($templateId !== '') {
                        break;
                    }
                }

                $lastError = $this->extractApiErrorMessage($createData, $lastError);
                $lastStatus = $createResponse->status();
                $lastData = $createData;
            }

            if ($templateId === '') {
                $createFallbacks = [
                    ['version' => 'v3', 'url' => "{$this->baseUrl}/emails/locations/{$locationId}/templates"],
                    ['version' => 'v3', 'url' => "{$this->baseUrl}/locations/{$locationId}/templates"],
                ];

                foreach ($createFallbacks as $fallback) {
                    $createResponse = Http::withHeaders(['Version' => $fallback['version']])
                        ->timeout((int) config('ghl.timeout', 20))
                        ->withToken($token)
                        ->acceptJson()
                        ->asJson()
                        ->post($fallback['url'], $basePayload);

                    $createData = $createResponse->json();
                    $createData = is_array($createData) ? $createData : [];

                    $attemptDebug[] = [
                        'method' => 'POST',
                        'url' => $fallback['url'],
                        'status' => $createResponse->status(),
                    ];

                    if ($createResponse->successful()) {
                        $templateId = (string) (
                            $createData['id']
                            ?? $createData['_id']
                            ?? $createData['templateId']
                            ?? data_get($createData, 'data.id')
                            ?? data_get($createData, 'template.id')
                            ?? ''
                        );

                        $lastData = $createData;
                        $lastStatus = $createResponse->status();

                        if ($templateId !== '') {
                            break;
                        }
                    }

                    $lastError = $this->extractApiErrorMessage($createData, $lastError);
                    $lastStatus = $createResponse->status();
                    $lastData = $createData;
                }
            }

            if ($templateId === '') {
                return [
                    'success' => false,
                    'error' => $lastError ?: 'Template was not created.',
                    'status' => $lastStatus,
                    'raw' => $lastData,
                    'debug' => $attemptDebug,
                ];
            }
        }

        $updateAttempts = [
            ['method' => 'put', 'version' => '2021-07-28', 'url' => "{$this->baseUrl}/emails/builder/{$templateId}", 'payload' => $basePayload, 'source' => 'PUT /emails/builder/{id}'],
            ['method' => 'patch', 'version' => '2021-07-28', 'url' => "{$this->baseUrl}/emails/builder/{$templateId}", 'payload' => $basePayload, 'source' => 'PATCH /emails/builder/{id}'],
            ['method' => 'put', 'version' => '2023-02-21', 'url' => "{$this->baseUrl}/emails/builder/{$templateId}", 'payload' => $basePayload, 'source' => 'PUT /emails/builder/{id} 2023-02-21'],
            ['method' => 'patch', 'version' => '2023-02-21', 'url' => "{$this->baseUrl}/emails/builder/{$templateId}", 'payload' => $basePayload, 'source' => 'PATCH /emails/builder/{id} 2023-02-21'],
            ['method' => 'put', 'version' => 'v3', 'url' => "{$this->baseUrl}/emails/locations/{$locationId}/templates/{$templateId}", 'payload' => $basePayload, 'source' => 'PUT /emails/locations/{locationId}/templates/{id}'],
            ['method' => 'patch', 'version' => 'v3', 'url' => "{$this->baseUrl}/emails/locations/{$locationId}/templates/{$templateId}", 'payload' => $basePayload, 'source' => 'PATCH /emails/locations/{locationId}/templates/{id}'],
        ];

        foreach ($updateAttempts as $attempt) {
            $response = Http::withHeaders(['Version' => $attempt['version']])
                ->timeout((int) config('ghl.timeout', 20))
                ->withToken($token)
                ->acceptJson()
                ->asJson()
                ->send(strtoupper($attempt['method']), $attempt['url'], ['json' => $attempt['payload']]);

            $data = $response->json();
            if (! is_array($data)) {
                $data = [];
            }

            $attemptDebug[] = [
                'method' => strtoupper($attempt['method']),
                'source' => $attempt['source'],
                'version' => $attempt['version'],
                'status' => $response->status(),
            ];

            if ($response->successful()) {
                $templateData = $data['template'] ?? $data['data'] ?? $data;
                if (! is_array($templateData)) {
                    $templateData = [];
                }

                return [
                    'success' => true,
                    'template' => array_merge($templateData, [
                        'id' => $templateId,
                        'name' => $name,
                        'title' => $name,
                        'subjectLine' => $subject,
                        'previewText' => $previewText,
                        'fromName' => $fromName,
                        'fromEmail' => $fromEmail,
                        'html' => $body,
                        'body' => $body,
                        'isPlainText' => false,
                        'type' => 'html',
                    ]),
                    'source' => $attempt['source'],
                    'raw' => $data ?: $lastData,
                    'debug' => $attemptDebug,
                ];
            }

            $lastError = $this->extractApiErrorMessage($data, $lastError);
            $lastStatus = $response->status();
            $lastData = $data;

            if (in_array($response->status(), [401, 403], true)) {
                break;
            }
        }

        return [
            'success' => false,
            'error' => $lastError,
            'status' => $lastStatus,
            'raw' => $lastData,
            'debug' => $attemptDebug,
        ];
    }

    private function extractApiErrorMessage(mixed $data, mixed $fallback = 'Request failed.'): string
    {
        if (is_string($data) && trim($data) !== '') {
            return trim($data);
        }

        if (! is_array($data)) {
            return is_string($fallback) ? $fallback : $this->stringifyApiError($fallback);
        }

        foreach (['message', 'error', 'title', 'detail'] as $key) {
            $value = $data[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (is_array($value)) {
                $message = $this->extractApiErrorMessage($value, '');
                if ($message !== '') {
                    return $message;
                }
            }
        }

        foreach ($data as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (is_array($value)) {
                $message = $this->extractApiErrorMessage($value, '');
                if ($message !== '') {
                    return $message;
                }
            }
        }

        return is_string($fallback) ? $fallback : $this->stringifyApiError($fallback);
    }

    private function stringifyApiError(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_scalar($value) || $value === null) {
            return trim((string) $value);
        }

        if (is_array($value)) {
            $message = $this->extractApiErrorMessage($value, '');
            if ($message !== '') {
                return $message;
            }

            $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return is_string($json) && $json !== '' ? $json : 'Request failed.';
        }

        return 'Request failed.';
    }

    public function deleteEmailTemplateForUser(User $user, string $templateId): array
    {
        $credentials = $this->credentialsForUser($user);
        $locationId = $credentials['location_id'];
        $token = $this->tokenForLocation($locationId, $credentials['token_override']);
        $templateId = trim($templateId);

        if (! $locationId || ! $token) {
            return ['success' => false, 'error' => 'Missing recruiting data connection.'];
        }

        if ($templateId === '') {
            return ['success' => false, 'error' => 'Choose a template first.'];
        }

        $attempts = [
            "{$this->baseUrl}/emails/locations/{$locationId}/templates/{$templateId}",
            "{$this->baseUrl}/locations/{$locationId}/templates/{$templateId}",
        ];

        $lastError = 'Unable to delete template.';
        $lastStatus = null;
        $lastData = [];

        foreach ($attempts as $url) {
            $response = Http::withHeaders(['Version' => 'v3'])
                ->timeout((int) config('ghl.timeout', 20))
                ->withToken($token)
                ->acceptJson()
                ->delete($url);

            $data = $response->json() ?? [];

            if ($response->successful()) {
                return ['success' => true, 'raw' => $data];
            }

            $lastError = $data['message'] ?? $data['error'] ?? $lastError;
            $lastStatus = $response->status();
            $lastData = $data;
        }

        return [
            'success' => false,
            'error' => $lastError,
            'status' => $lastStatus,
            'raw' => $lastData,
        ];
    }

    public function createEmailCampaignForUser(User $user, array $payload): array
    {
        $credentials = $this->credentialsForUser($user);
        $locationId = $credentials['location_id'];
        $token = $this->tokenForLocation($locationId, $credentials['token_override']);

        if (! $locationId || ! $token) {
            return ['success' => false, 'error' => 'Missing recruiting data connection.'];
        }

        $body = [
            'name' => (string) ($payload['name'] ?? ('PLYRCard Campaign ' . now()->format('Y-m-d H:i'))),
            'subjectLine' => (string) ($payload['subjectLine'] ?? $payload['subject'] ?? ''),
            'previewText' => (string) ($payload['previewText'] ?? ''),
            'fromName' => (string) ($payload['fromName'] ?? $user->name ?? 'PLYRCard'),
            'fromEmail' => (string) ($payload['fromEmail'] ?? $this->defaultSenderEmailForUser($user)),
            'html' => (string) ($payload['html'] ?? $payload['body'] ?? ''),
        ];

        $response = Http::withHeaders(['Version' => '2023-02-21'])
            ->timeout((int) config('ghl.timeout', 20))
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->post("{$this->baseUrl}/emails/public/v2/locations/{$locationId}/campaigns/email-campaign", $body);

        $data = $response->json() ?? [];

        if (! $response->successful()) {
            Log::error('Recruiting email campaign create failed.', [
                'status' => $response->status(),
                'location_id' => $locationId,
                'raw' => $data,
            ]);

            return [
                'success' => false,
                'error' => $data['message'] ?? $data['error'] ?? 'Unable to create campaign.',
                'status' => $response->status(),
                'raw' => $data,
            ];
        }

        $campaign = $data['campaign'] ?? $data['data'] ?? $data;
        $campaignId = $campaign['id'] ?? $campaign['_id'] ?? $campaign['campaignId'] ?? $data['id'] ?? $data['campaignId'] ?? null;

        return [
            'success' => true,
            'campaign' => $campaign,
            'campaign_id' => $campaignId ? (string) $campaignId : null,
            'raw' => $data,
        ];
    }

    public function scheduleEmailCampaignForUser(User $user, string $campaignId, ?int $scheduledTimestamp = null): array
    {
        $credentials = $this->credentialsForUser($user);
        $locationId = $credentials['location_id'];
        $token = $this->tokenForLocation($locationId, $credentials['token_override']);
        $campaignId = trim($campaignId);

        if (! $locationId || ! $token) {
            return ['success' => false, 'error' => 'Missing recruiting data connection.'];
        }

        if ($campaignId === '') {
            return ['success' => false, 'error' => 'Campaign id is required.'];
        }

        $payload = [];
        if ($scheduledTimestamp) {
            $payload['scheduledTimestamp'] = $scheduledTimestamp;
        }

        $response = Http::withHeaders(['Version' => '2023-02-21'])
            ->timeout((int) config('ghl.timeout', 20))
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->post("{$this->baseUrl}/emails/public/v2/locations/{$locationId}/campaigns/{$campaignId}/schedule", $payload);

        $data = $response->json() ?? [];

        if (! $response->successful()) {
            return [
                'success' => false,
                'error' => $data['message'] ?? $data['error'] ?? 'Unable to schedule/start campaign.',
                'status' => $response->status(),
                'raw' => $data,
            ];
        }

        return ['success' => true, 'raw' => $data];
    }

    protected function defaultSenderEmailForUser(User $user): string
    {
        $configured = trim((string) config('ghl.coach_database.email.from_email', ''));

        if ($configured !== '') {
            return $configured;
        }

        $domain = trim((string) config('ghl.coach_database.email.from_domain', 'plyr.yoursportcard.com'));
        $name = trim((string) ($user->name ?? 'plyrcard'));
        $local = Str::of($name)->lower()->replaceMatches('/[^a-z0-9]+/', '.')->trim('.')->toString();

        if ($local === '') {
            $local = 'recruiting';
        }

        return $local . '@' . $domain;
    }

    protected function transformSchoolBusiness(array $business): array
    {
        $field = function (array|string $keys) use ($business): ?string {
            $keys = is_array($keys) ? $keys : [$keys];
            $value = $this->stringCustomFieldFromRecord($business, $keys);

            return $value !== '' ? $value : null;
        };

        $logoUrl = $this->schoolLogoUrlFromRecord($business);

        $rawCoachCount = $business['contactsCount']
            ?? $business['contactCount']
            ?? $business['contacts_count']
            ?? $business['contact_count']
            ?? null;

        return [
            'id' => (string) ($business['id'] ?? $business['_id'] ?? ''),
            'business_id' => (string) ($business['id'] ?? $business['_id'] ?? ''),
            'name' => (string) ($business['name'] ?? $business['businessName'] ?? $business['companyName'] ?? $business['title'] ?? 'Unnamed School'),
            'logo_url' => $logoUrl,
            'school_logo_url' => $logoUrl,
            'business_logo_url' => $logoUrl,
            'conference' => $field([
                'business.conference',
                'conference',
                'Conference',
                'school_conference',
                'School Conference',
            ]),
            'division' => $field([
                'business.division',
                'division',
                'Division',
                'school_division',
                'School Division',
            ]),
            'city' => $business['city'] ?? null,
            'state' => $business['state'] ?? null,
            'website' => $business['website'] ?? null,
            'email' => $business['email'] ?? null,
            'phone' => $business['phone'] ?? null,
            'updated_at' => $business['updatedAt'] ?? null,
            'created_at' => $business['createdAt'] ?? null,
            'coach_count' => is_numeric($rawCoachCount) ? max(0, (int) $rawCoachCount) : 0,
            'coaches_count' => is_numeric($rawCoachCount) ? max(0, (int) $rawCoachCount) : 0,
            'coach_count_loaded' => is_numeric($rawCoachCount),
            'is_saved' => false,
            'is_favorite' => false,
            'engagement_score' => 0,
            'list_keys' => [],
        ];
    }

    protected function transformConversation(array $item): array
    {
        $contact = is_array($item['contact'] ?? null) ? $item['contact'] : [];
        $lastMessage = is_array($item['lastMessage'] ?? null) ? $item['lastMessage'] : [];
        $lastBody = $lastMessage['body'] ?? $lastMessage['message'] ?? $lastMessage['text'] ?? $item['lastMessageBody'] ?? $item['lastMessage'] ?? '';

        $rawForAssets = json_encode($item) ?: '';
        $hasImage = (bool) preg_match('/<img\b|\.(png|jpe?g|gif|webp)(\?|\"|\'|$)/i', $rawForAssets);
        $hasFile = ! $hasImage && (bool) preg_match('/\.(pdf|docx?|xlsx?|pptx?|zip)(\?|\"|\'|$)/i', $rawForAssets);

        return [
            'id' => (string) ($item['id'] ?? $item['_id'] ?? $item['conversationId'] ?? ''),
            'contact_id' => (string) ($item['contactId'] ?? $item['contact_id'] ?? $contact['id'] ?? ''),
            'contact_name' => (string) ($item['contactName'] ?? $item['fullName'] ?? $item['name'] ?? $contact['contactName'] ?? $contact['fullName'] ?? $contact['name'] ?? trim(($contact['firstName'] ?? '') . ' ' . ($contact['lastName'] ?? '')) ?: 'Unknown Coach'),
            'email' => (string) ($item['email'] ?? $item['contactEmail'] ?? $contact['email'] ?? ''),
            'status' => (string) ($item['status'] ?? (($item['unreadCount'] ?? 0) ? 'Unread' : 'Open')),
            'last_message' => trim(strip_tags((string) $lastBody)),
            'last_message_at' => $item['lastMessageDate'] ?? $item['lastMessageAt'] ?? $item['last_message_date'] ?? $item['updatedAt'] ?? null,
            'unread_count' => (int) ($item['unreadCount'] ?? $item['unread_count'] ?? 0),
            'has_image' => $hasImage,
            'has_file' => $hasFile,
        ];
    }

    protected function transformConversationMessage(array $item): array
    {
        $htmlBody = $this->conversationMessageHtmlBody($item);
        $textBody = $this->conversationMessageTextBody($item, $htmlBody);
        $direction = $this->conversationScalar(
            $item['direction']
            ?? $item['messageDirection']
            ?? $item['directionType']
            ?? data_get($item, 'emailMessage.direction')
            ?? data_get($item, 'meta.email.direction')
            ?? data_get($item, 'email.direction')
            ?? $item['source']
            ?? ''
        );
        $from = $this->conversationScalar(
            $item['from']
            ?? $item['emailFrom']
            ?? data_get($item, 'emailMessage.from')
            ?? data_get($item, 'emailMessage.fromEmail')
            ?? data_get($item, 'emailMessage.sender.email')
            ?? $item['sender']
            ?? data_get($item, 'sender.email')
            ?? data_get($item, 'from.email')
            ?? ''
        );
        $to = $this->conversationScalar(
            $item['to']
            ?? $item['emailTo']
            ?? data_get($item, 'emailMessage.to')
            ?? data_get($item, 'emailMessage.toEmail')
            ?? data_get($item, 'emailMessage.recipients')
            ?? $item['receiver']
            ?? data_get($item, 'to.email')
            ?? ''
        );
        $fromName = $this->conversationScalar(
            $item['fromName']
            ?? $item['senderName']
            ?? data_get($item, 'emailMessage.fromName')
            ?? data_get($item, 'emailMessage.sender.name')
            ?? data_get($item, 'sender.name')
            ?? data_get($item, 'from.name')
            ?? ''
        );

        return [
            'id' => (string) ($item['id'] ?? $item['_id'] ?? $item['messageId'] ?? ''),
            'direction' => $direction,
            'type' => $this->conversationScalar($item['type'] ?? $item['messageType'] ?? 'TYPE_EMAIL'),
            'subject' => $this->conversationScalar($item['subject'] ?? $item['emailSubject'] ?? data_get($item, 'meta.email.subject') ?? data_get($item, 'email.subject') ?? data_get($item, 'emailMessage.subject') ?? ''),
            // Keep the historical body key for compatibility, but make it the
            // canonical HTML body so Inbox never prefers a lossy plain-text copy.
            'body' => $htmlBody !== '' ? $htmlBody : $textBody,
            'html_body' => $htmlBody,
            'text_body' => $textBody,
            'status' => $this->conversationScalar($item['status'] ?? data_get($item, 'emailMessage.status') ?? ''),
            'from' => $from,
            'from_name' => $fromName ?: $from,
            'to' => $to,
            'attachments' => $this->extractConversationAttachments($item),
            'created_at' => $item['dateAdded'] ?? $item['createdAt'] ?? $item['created_at'] ?? data_get($item, 'emailMessage.dateAdded') ?? data_get($item, 'emailMessage.createdAt') ?? data_get($item, 'emailMessage.timestamp') ?? null,
        ];
    }

    protected function conversationMessageHtmlBody(array $item): string
    {
        $candidates = [
            $item['htmlBody'] ?? null,
            $item['html_body'] ?? null,
            $item['messageHtml'] ?? null,
            $item['message_html'] ?? null,
            $item['html'] ?? null,
            data_get($item, 'email.htmlBody'),
            data_get($item, 'email.html_body'),
            data_get($item, 'email.html'),
            data_get($item, 'message.htmlBody'),
            data_get($item, 'message.html_body'),
            data_get($item, 'message.html'),
            data_get($item, 'payload.htmlBody'),
            data_get($item, 'payload.html'),
            // Some HighLevel responses put the complete MIME HTML part in body.
            $item['body'] ?? null,
            $item['emailMessage'] ?? null,
            $item['messageBody'] ?? null,
            $item['content'] ?? null,
            data_get($item, 'email.body'),
            data_get($item, 'email.content'),
            data_get($item, 'message.body'),
            data_get($item, 'message.content'),
            data_get($item, 'payload.body'),
        ];

        foreach ($candidates as $candidate) {
            $value = $this->decodeConversationHtml($this->conversationHtmlValue($candidate));

            if ($this->looksLikeEmailHtml($value)) {
                return $value;
            }
        }

        return '';
    }

    protected function conversationMessageTextBody(array $item, string $htmlBody = ''): string
    {
        foreach ([
            $item['textBody'] ?? null,
            $item['text_body'] ?? null,
            $item['bodyText'] ?? null,
            $item['plainText'] ?? null,
            $item['plain_text'] ?? null,
            $item['text'] ?? null,
            data_get($item, 'email.textBody'),
            data_get($item, 'email.text'),
            data_get($item, 'message.textBody'),
            data_get($item, 'message.text'),
            data_get($item, 'payload.text'),
            $item['body'] ?? null,
            $item['message'] ?? null,
        ] as $candidate) {
            $value = $this->conversationHtmlValue($candidate);
            if ($value === '') {
                continue;
            }

            $decoded = $this->decodeConversationHtml($value);
            if (! $this->looksLikeEmailHtml($decoded) && trim($decoded) !== '') {
                return trim($decoded);
            }
        }

        return $htmlBody !== '' ? $this->trackedPlainTextFromHtml($htmlBody) : '';
    }

    protected function decodeConversationHtml(string $value): string
    {
        $value = trim($value);

        for ($i = 0; $i < 3 && $value !== ''; $i++) {
            $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $value) {
                break;
            }
            $value = $decoded;
        }

        return trim($value);
    }

    protected function looksLikeEmailHtml(string $value): bool
    {
        return $value !== '' && (bool) preg_match(
            '/<\s*(?:!doctype|html|head|body|meta|style|table|thead|tbody|tfoot|tr|td|th|p|div|br|a|img|ul|ol|li|span|strong|em|blockquote|h[1-6])\b/i',
            $value,
        );
    }

    protected function conversationMessageBody(array $item): string
    {
        $html = $this->conversationMessageHtmlBody($item);

        return $html !== '' ? $html : $this->conversationMessageTextBody($item);
    }

    protected function conversationHtmlValue(mixed $value): string
    {
        if (is_null($value)) {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (! is_array($value)) {
            return '';
        }

        foreach (['html', 'body', 'message', 'emailMessage', 'content', 'text', 'value'] as $key) {
            if (array_key_exists($key, $value)) {
                $resolved = $this->conversationHtmlValue($value[$key]);
                if ($resolved !== '') {
                    return $resolved;
                }
            }
        }

        $parts = [];
        foreach ($value as $child) {
            $resolved = $this->conversationHtmlValue($child);
            if (trim(strip_tags($resolved)) !== '') {
                $parts[] = $resolved;
            }
        }

        return implode("
", $parts);
    }

    protected function conversationScalar(mixed $value): string
    {
        if (is_null($value)) {
            return '';
        }

        if (is_string($value)) {
            return trim($value);
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (is_array($value)) {
            foreach (['name', 'email', 'address', 'value', 'label', 'id'] as $key) {
                if (array_key_exists($key, $value)) {
                    $resolved = $this->conversationScalar($value[$key]);
                    if ($resolved !== '') {
                        return $resolved;
                    }
                }
            }
        }

        return '';
    }

    protected function extractConversationAttachments(array $item): array
    {
        $candidates = [
            $item['attachments'] ?? null,
            $item['emailAttachments'] ?? null,
            $item['files'] ?? null,
            $item['media'] ?? null,
            $item['fileAttachments'] ?? null,
            $item['messageAttachments'] ?? null,
            $item['images'] ?? null,
            data_get($item, 'email.attachments'),
            data_get($item, 'message.attachments'),
            data_get($item, 'body.attachments'),
            data_get($item, 'payload.attachments'),
            data_get($item, 'payload.files'),
        ];

        $attachments = [];
        foreach ($candidates as $candidate) {
            $this->collectConversationAttachments($candidate, $attachments);
        }

        return collect($attachments)
            ->filter(fn (array $attachment): bool => filled($attachment['url'] ?? null))
            ->unique(fn (array $attachment): string => (string) ($attachment['url'] ?? ''))
            ->values()
            ->all();
    }

    protected function collectConversationAttachments(mixed $value, array &$attachments): void
    {
        if (! is_array($value)) {
            return;
        }

        $url = $this->conversationScalar($value['url'] ?? $value['link'] ?? $value['mediaUrl'] ?? $value['mediaURL'] ?? $value['fileUrl'] ?? $value['fileURL'] ?? $value['downloadUrl'] ?? $value['downloadURL'] ?? $value['attachmentUrl'] ?? $value['attachmentURL'] ?? $value['thumbnailUrl'] ?? $value['thumbnailURL'] ?? $value['src'] ?? $value['href'] ?? '');
        if ($url !== '') {
            $attachments[] = [
                'url' => $url,
                'name' => $this->conversationScalar($value['name'] ?? $value['filename'] ?? $value['fileName'] ?? $value['title'] ?? 'Attachment'),
                'mime_type' => $this->conversationScalar($value['mimeType'] ?? $value['mime_type'] ?? $value['contentType'] ?? $value['type'] ?? ''),
                'type' => $this->conversationScalar($value['type'] ?? ''),
            ];
        }

        foreach ($value as $child) {
            if (is_array($child)) {
                $this->collectConversationAttachments($child, $attachments);
            }
        }
    }


    protected function getRecruitingMetricCustomFieldKeys(): array
    {
        return [
            'profile_views' => ['view_profile_total', 'plyrcard_profile_views', 'profile_views', 'profile_view_count'],
            'unique_profile_views' => ['unique_profile_view_count', 'unique_profile_views'],
            'link_clicks' => ['email_click_count', 'website_click_count', 'instagram_click_count', 'youtube_click_count', 'x_click_count', 'view_profile_website', 'view_profile_instagram', 'view_profile_youtube', 'view_profile_x', 'view_profile_email_link', 'plyrcard_link_clicks', 'link_clicks', 'trigger_link_clicks', 'trigger_link_click_count'],
            'unique_clicks' => ['unique_click_count', 'unique_link_click_count', 'unique_clicks'],
            'school_clicks' => ['school_click_count', 'school_profile_view_count', 'school_link_click_count'],
            'email_opens' => ['email_open_count', 'plyrcard_email_opens', 'email_opens', 'opened_emails'],
            'coach_replies' => ['plyrcard_coach_replies', 'coach_replies', 'replies', 'coach_reply_count'],
            'emails_sent' => ['email_sent_count', 'plyrcard_total_emails_sent', 'emails_sent', 'total_emails_sent'],
        ];
    }

    /**
     * Extra aliases for fields that were manually created in Recruiting Center before the
     * canonical Recruiting Center tracking fields existed. This keeps reads and
     * increments compatible with user-created names like "Profile Views" or
     * "Website Clicks" instead of silently returning zero.
     */
    protected function recruitingTrackingFieldAliases(): array
    {
        return [
            'view_profile_total' => ['profile_views', 'profile_view_count', 'profile views', 'plyrcard_profile_views', 'total_profile_views', 'total profile views'],
            'view_profile_website' => ['website_profile_views', 'website profile views', 'player_website_views', 'player website views', 'profile website views'],
            'view_profile_instagram' => ['instagram_profile_views', 'instagram profile views', 'ig_profile_views', 'instagram_views'],
            'view_profile_youtube' => ['youtube_profile_views', 'youtube profile views', 'highlight_views', 'highlights_views'],
            'view_profile_x' => ['x_profile_views', 'twitter_profile_views', 'x views', 'twitter views'],
            'view_profile_email_link' => ['email_profile_views', 'email profile views', 'profile_email_clicks', 'email_link_profile_views'],
            'email_sent_count' => ['emails_sent', 'total_emails_sent', 'email sent count', 'plyrcard_total_emails_sent'],
            'email_open_count' => ['email_opens', 'opened_emails', 'email open count', 'plyrcard_email_opens'],
            'email_click_count' => ['email_clicks', 'email clicks', 'email link clicks'],
            'website_click_count' => ['website_clicks', 'website clicks', 'player_website_clicks'],
            'instagram_click_count' => ['instagram_clicks', 'instagram clicks', 'ig_clicks'],
            'youtube_click_count' => ['youtube_clicks', 'youtube clicks', 'highlight_clicks', 'highlights_clicks'],
            'x_click_count' => ['x_clicks', 'twitter_clicks', 'x clicks', 'twitter clicks'],
            'coach_reply_count' => ['coach_replies', 'replies', 'coach replies', 'plyrcard_coach_replies'],
        ];
    }

    protected function fetchContactForDashboard(string $contactId, string $locationId, ?string $tokenOverride = null, bool $forceFresh = false): array
    {
        $token = $this->tokenForLocation($locationId, $tokenOverride);
        if (! $contactId || ! $locationId || ! $token) {
            return [];
        }

        $cacheKey = 'recruiting-tracking-contact-detail:' . md5($locationId . '|' . $contactId);

        if ($forceFresh) {
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
        }

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addSeconds((int) config('ghl.coach_database.tracking_contact_cache_seconds', 45)), function () use ($contactId, $locationId, $token): array {
            try {
                $response = Http::withHeaders(['Version' => '2021-07-28'])
                    ->connectTimeout((int) config('ghl.coach_database.tracking_http_connect_timeout', 3))
                    ->timeout((int) config('ghl.coach_database.tracking_http_timeout', 6))
                    ->withToken($token)
                    ->acceptJson()
                    ->get("{$this->baseUrl}/contacts/{$contactId}");

                if ($response->failed()) {
                    return [];
                }

                $data = $response->json() ?? [];
                $contact = $data['contact'] ?? $data;
                return is_array($contact) ? $contact : [];
            } catch (\Throwable $exception) {
                Log::warning('Recruiting metric contact fetch skipped.', ['contact_id' => $contactId, 'error' => $exception->getMessage()]);
                return [];
            }
        });
    }

    protected function numericCustomFieldFromContact(array $contact, array $keys, array $trackingFieldMap = []): int
    {
        $direct = $this->firstNumericValue($contact, $keys);
        if ($direct > 0) {
            return $direct;
        }

        $normalizedKeys = $this->normalizedRecruitingTrackingLookupKeys($keys, $trackingFieldMap);

        // Common HighLevel shapes first.
        foreach (['customFields', 'customField', 'custom_fields', 'customFieldValues', 'custom_field_values'] as $containerKey) {
            $rawCustomFields = data_get($contact, $containerKey, []);
            $value = $this->numericCustomFieldFromRawContainer($rawCustomFields, $normalizedKeys);
            if ($value !== null) {
                return $value;
            }
        }

        // Some Recruiting Center responses nest the real contact object one level deeper.
        foreach (['contact', 'data', 'result'] as $nestedKey) {
            $nested = data_get($contact, $nestedKey);
            if (is_array($nested)) {
                foreach (['customFields', 'customField', 'custom_fields', 'customFieldValues', 'custom_field_values'] as $containerKey) {
                    $value = $this->numericCustomFieldFromRawContainer(data_get($nested, $containerKey, []), $normalizedKeys);
                    if ($value !== null) {
                        return $value;
                    }
                }
            }
        }

        // Last resort: recursively scan the full contact payload for an object whose
        // id/key/fieldKey/name matches the tracking field, then extract its value.
        $recursive = $this->recursiveNumericCustomFieldLookup($contact, $normalizedKeys);
        if ($recursive !== null) {
            return $recursive;
        }

        return 0;
    }

    protected function normalizedRecruitingTrackingLookupKeys(array $keys, array $trackingFieldMap = []): array
    {
        $normalize = fn ($key): string => strtolower(str_replace([' ', '-', '.', ':'], '_', (string) $key));
        $normalizedKeys = collect($keys)->map($normalize)->filter()->unique()->values()->all();
        $aliases = $this->recruitingTrackingFieldAliases();

        foreach ($keys as $key) {
            $normalizedKey = $normalize($key);
            foreach (($aliases[$key] ?? $aliases[$normalizedKey] ?? []) as $alias) {
                $normalizedKeys[] = $normalize($alias);
            }

            $field = $trackingFieldMap[$key] ?? $trackingFieldMap[$normalizedKey] ?? null;

            if (! is_array($field)) {
                continue;
            }

            foreach (['id', '_id', 'key', 'fieldKey', 'name', 'label', 'customFieldId', 'fieldId'] as $identifierKey) {
                $identifier = $field[$identifierKey] ?? null;
                if (filled($identifier)) {
                    $normalizedKeys[] = $normalize($identifier);
                }
            }
        }

        return collect($normalizedKeys)->filter()->unique()->values()->all();
    }

    protected function numericCustomFieldFromRawContainer(mixed $rawCustomFields, array $normalizedKeys): ?int
    {
        $normalize = fn ($key): string => strtolower(str_replace([' ', '-', '.', ':'], '_', (string) $key));

        if (! is_array($rawCustomFields) || empty($rawCustomFields)) {
            return null;
        }

        if (! array_is_list($rawCustomFields)) {
            foreach ($rawCustomFields as $fieldKey => $fieldValue) {
                if (! in_array($normalize($fieldKey), $normalizedKeys, true)) {
                    continue;
                }

                $extracted = $this->extractNumericTrackingValue($fieldValue);
                if ($extracted !== null) {
                    return $extracted;
                }
            }
        }

        foreach ($rawCustomFields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $fieldKeys = collect([
                $field['key'] ?? null,
                $field['name'] ?? null,
                $field['label'] ?? null,
                $field['fieldKey'] ?? null,
                $field['customFieldId'] ?? null,
                $field['fieldId'] ?? null,
                $field['id'] ?? null,
                $field['_id'] ?? null,
            ])->filter()->map($normalize)->all();

            if (! array_intersect($normalizedKeys, $fieldKeys)) {
                continue;
            }

            $value = $this->extractNumericTrackingValue($field);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    protected function recursiveNumericCustomFieldLookup(mixed $value, array $normalizedKeys, int $depth = 0): ?int
    {
        if ($depth > 12 || ! is_array($value)) {
            return null;
        }

        $normalize = fn ($key): string => strtolower(str_replace([' ', '-', '.', ':'], '_', (string) $key));
        $fieldKeys = collect([
            $value['key'] ?? null,
            $value['name'] ?? null,
            $value['label'] ?? null,
            $value['fieldKey'] ?? null,
            $value['customFieldId'] ?? null,
            $value['fieldId'] ?? null,
            $value['id'] ?? null,
            $value['_id'] ?? null,
        ])->filter()->map($normalize)->all();

        if (array_intersect($normalizedKeys, $fieldKeys)) {
            $extracted = $this->extractNumericTrackingValue($value);
            if ($extracted !== null) {
                return $extracted;
            }
        }

        foreach ($value as $childKey => $child) {
            if (! is_array($child)) {
                continue;
            }

            $found = $this->recursiveNumericCustomFieldLookup($child, $normalizedKeys, $depth + 1);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    protected function extractNumericTrackingValue(mixed $value): ?int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if (preg_match('/^-?\d+$/', $trimmed)) {
                return (int) $trimmed;
            }
        }

        if (! is_array($value)) {
            return null;
        }

        foreach (['value', 'field_value', 'fieldValue', 'numberValue', 'valueNumber', 'valueString', 'stringValue', 'text', 'number', 'count'] as $key) {
            if (! array_key_exists($key, $value)) {
                continue;
            }

            $candidate = $value[$key];

            if (is_numeric($candidate)) {
                return (int) $candidate;
            }

            if (is_string($candidate) && preg_match('/^-?\d+$/', trim($candidate))) {
                return (int) trim($candidate);
            }

            if (is_array($candidate)) {
                $nestedValue = $this->extractNumericTrackingValue($candidate);
                if ($nestedValue !== null) {
                    return $nestedValue;
                }
            }
        }

        foreach ($value as $nested) {
            if (is_numeric($nested)) {
                return (int) $nested;
            }

            if (is_string($nested) && preg_match('/^-?\d+$/', trim($nested))) {
                return (int) trim($nested);
            }
        }

        return null;
    }

    protected function schoolLogoFieldKeys(): array
    {
        return collect([
            config('ghl.coach_database.custom_fields.school_logo'),
            config('ghl.coach_database.custom_fields.schoolLogo'),

            // Exact Recruiting Center merge-field keys used in the Recruiting Center account.
            // Contact custom field merge token: {{contact.school_logo}}
            // Business / school custom field merge token: {{business.logo}}
            'contact.school_logo',
            'business.logo',

            // Human-readable names and common aliases.
            'School Logo',
            'School logo',
            'school_logo',
            'schoolLogo',
            'school logo',
            'logo',
            'logo_url',
            'logoUrl',
            'company_logo',
            'company.logo',
            'business_logo',
            'business.logo_url',
            'Company Logo',
            'Business Logo',
        ])
            ->filter()
            ->map(fn ($key): string => (string) $key)
            ->unique(fn (string $key): string => strtolower($key))
            ->values()
            ->all();
    }

    protected function schoolLogoUrlFromRecord(array $record): ?string
    {
        // Primary path: exact Recruiting Center custom field keys / merge fields.
        // Contact field: {{contact.school_logo}}
        // Business field: {{business.logo}}
        $url = $this->stringCustomFieldFromRecord($record, $this->schoolLogoFieldKeys());

        if ($url === '') {
            $url = $this->extractCustomFieldScalarValue(
                $record['school_logo_url']
                ?? $record['schoolLogoUrl']
                ?? $record['business_logo_url']
                ?? $record['businessLogoUrl']
                ?? $record['company_logo_url']
                ?? $record['companyLogoUrl']
                ?? $record['logo_url']
                ?? $record['logoUrl']
                ?? $record['logo']
                ?? $record['image']
                ?? $record['imageUrl']
                ?? ''
            );
        }

        // Some Recruiting Center endpoints return custom field rows with only an internal custom
        // field id and a value. In that case we cannot match the merge key, so for
        // logo fields we fall back to the first image-looking URL found inside the
        // custom fields payload. This is intentionally limited to image/CDN URLs so
        // normal website fields do not become school logos.
        if ($url === '') {
            $url = $this->imageUrlFromCustomFields($record);
        }

        return $this->normalizeRemoteImageUrl($url);
    }

    protected function normalizeRemoteImageUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }

        if (! Str::startsWith($url, ['http://', 'https://'])) {
            return null;
        }

        return $url;
    }

    protected function imageUrlFromCustomFields(array $record): string
    {
        foreach (['customFields', 'customField', 'custom_fields', 'customFieldValues', 'custom_field_values'] as $containerKey) {
            $rawCustomFields = data_get($record, $containerKey, []);

            if (! is_array($rawCustomFields)) {
                continue;
            }

            foreach ($rawCustomFields as $fieldKey => $fieldValue) {
                $resolved = $this->extractCustomFieldScalarValue($fieldValue);

                if ($resolved === '') {
                    continue;
                }

                if ($this->looksLikeRemoteImageUrl($resolved)) {
                    return $resolved;
                }
            }
        }

        foreach (['contact', 'business', 'company', 'data', 'result'] as $nestedKey) {
            $nested = data_get($record, $nestedKey);

            if (is_array($nested)) {
                $resolved = $this->imageUrlFromCustomFields($nested);

                if ($resolved !== '') {
                    return $resolved;
                }
            }
        }

        return '';
    }

    protected function looksLikeRemoteImageUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }

        if (! Str::startsWith($url, ['http://', 'https://'])) {
            return false;
        }

        $lower = strtolower(parse_url($url, PHP_URL_PATH) ?: $url);

        if (preg_match('/\.(png|jpe?g|webp|gif|svg)(\?|$)/i', $lower)) {
            return true;
        }

        return str_contains(strtolower($url), 'cloudinary')
            || str_contains(strtolower($url), 'storage.googleapis')
            || str_contains(strtolower($url), 'amazonaws.com')
            || str_contains(strtolower($url), 'cdn')
            || str_contains(strtolower($url), 'image')
            || str_contains(strtolower($url), 'logo');
    }

    protected function stringCustomFieldFromRecord(array $record, array $keys): string
    {
        $normalize = fn ($key): string => strtolower(str_replace([' ', '-', '.', ':'], '_', trim((string) $key)));
        $normalizedKeys = collect($keys)->map($normalize)->filter()->unique()->values()->all();

        $matches = function (mixed $candidate) use ($normalize, $normalizedKeys): bool {
            if (! is_scalar($candidate)) {
                return false;
            }

            $candidate = $normalize($candidate);

            if ($candidate === '') {
                return false;
            }

            foreach ($normalizedKeys as $key) {
                if ($candidate === $key) {
                    return true;
                }

                // Recruiting Center often exposes custom field keys as contact.school_logo or
                // business.logo. This lets school_logo match contact_school_logo,
                // and logo match business_logo, without relying on the internal ID.
                if (str_ends_with($candidate, '_' . $key)) {
                    return true;
                }
            }

            return false;
        };

        foreach ($keys as $key) {
            if (array_key_exists($key, $record)) {
                $resolved = $this->extractCustomFieldScalarValue($record[$key]);

                if ($resolved !== '') {
                    return $resolved;
                }
            }

            $value = data_get($record, $key);

            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }

            if (is_array($value)) {
                $resolved = $this->extractCustomFieldScalarValue($value);

                if ($resolved !== '') {
                    return $resolved;
                }
            }
        }

        foreach (['customFields', 'customField', 'custom_fields', 'customFieldValues', 'custom_field_values'] as $containerKey) {
            $rawCustomFields = data_get($record, $containerKey, []);

            if (! is_array($rawCustomFields)) {
                continue;
            }

            if (! array_is_list($rawCustomFields)) {
                foreach ($rawCustomFields as $fieldKey => $fieldValue) {
                    if (! $matches($fieldKey)) {
                        continue;
                    }

                    $resolved = $this->extractCustomFieldScalarValue($fieldValue);

                    if ($resolved !== '') {
                        return $resolved;
                    }
                }

                continue;
            }

            foreach ($rawCustomFields as $field) {
                if (! is_array($field)) {
                    continue;
                }

                $fieldKeys = [
                    $field['id'] ?? null,
                    $field['_id'] ?? null,
                    $field['key'] ?? null,
                    $field['name'] ?? null,
                    $field['label'] ?? null,
                    $field['fieldKey'] ?? null,
                    $field['field_key'] ?? null,
                    $field['customFieldId'] ?? null,
                    $field['custom_field_id'] ?? null,
                    $field['fieldId'] ?? null,
                    $field['field_id'] ?? null,
                ];

                if (! collect($fieldKeys)->filter()->contains(fn ($candidate): bool => $matches($candidate))) {
                    continue;
                }

                $resolved = $this->extractCustomFieldScalarValue($field);

                if ($resolved !== '') {
                    return $resolved;
                }
            }
        }

        foreach (['contact', 'business', 'company', 'data', 'result'] as $nestedKey) {
            $nested = data_get($record, $nestedKey);

            if (is_array($nested)) {
                $resolved = $this->stringCustomFieldFromRecord($nested, $keys);

                if ($resolved !== '') {
                    return $resolved;
                }
            }
        }

        return '';
    }

    protected function extractCustomFieldScalarValue(mixed $value): string
    {
        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (! is_array($value)) {
            return '';
        }

        foreach (['value', 'fieldValue', 'field_value', 'valueString', 'value_string', 'stringValue', 'valueText', 'text', 'url', 'link', 'mediaUrl', 'fileUrl', 'downloadUrl', 'thumbnailUrl', 'src'] as $key) {
            $candidate = $value[$key] ?? null;

            if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                return trim((string) $candidate);
            }

            if (is_array($candidate)) {
                $resolved = $this->extractCustomFieldScalarValue($candidate);

                if ($resolved !== '') {
                    return $resolved;
                }
            }
        }

        foreach ($value as $child) {
            if (is_array($child)) {
                $resolved = $this->extractCustomFieldScalarValue($child);

                if ($resolved !== '') {
                    return $resolved;
                }
            }
        }

        return '';
    }

    protected function stringCustomFieldFromContact(array $contact, array $keys, array $trackingFieldMap = []): string
    {
        $normalize = fn ($key): string => strtolower(str_replace([' ', '-', '.', ':'], '_', (string) $key));
        $normalizedKeys = collect($keys)->map($normalize)->filter()->unique()->values()->all();

        foreach ($keys as $key) {
            $field = $trackingFieldMap[$key] ?? null;

            if (! is_array($field)) {
                continue;
            }

            foreach (['id', '_id', 'key', 'fieldKey', 'name', 'label'] as $identifierKey) {
                $identifier = $field[$identifierKey] ?? null;
                if (filled($identifier)) {
                    $normalizedKeys[] = $normalize($identifier);
                }
            }
        }

        $normalizedKeys = collect($normalizedKeys)->filter()->unique()->values()->all();
        $rawCustomFields = $contact['customFields'] ?? $contact['customField'] ?? $contact['custom_fields'] ?? [];

        if (is_array($rawCustomFields) && ! array_is_list($rawCustomFields)) {
            foreach ($rawCustomFields as $fieldKey => $fieldValue) {
                if (! in_array($normalize($fieldKey), $normalizedKeys, true)) {
                    continue;
                }

                if (is_scalar($fieldValue)) {
                    return trim((string) $fieldValue);
                }

                if (is_array($fieldValue)) {
                    $value = $fieldValue['value'] ?? $fieldValue['fieldValue'] ?? $fieldValue['field_value'] ?? $fieldValue['valueString'] ?? $fieldValue['stringValue'] ?? $fieldValue['text'] ?? null;
                    if (is_scalar($value)) {
                        return trim((string) $value);
                    }
                }
            }
        }

        foreach ($rawCustomFields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $fieldKeys = collect([
                $field['key'] ?? null,
                $field['name'] ?? null,
                $field['fieldKey'] ?? null,
                $field['customFieldId'] ?? null,
                $field['fieldId'] ?? null,
                $field['id'] ?? null,
            ])->filter()->map($normalize)->all();

            if (! array_intersect($normalizedKeys, $fieldKeys)) {
                continue;
            }

            $value = $field['value'] ?? $field['fieldValue'] ?? $field['field_value'] ?? $field['valueString'] ?? $field['stringValue'] ?? $field['text'] ?? null;
            if (is_scalar($value)) {
                return trim((string) $value);
            }
        }

        foreach ($keys as $key) {
            $value = data_get($contact, $key);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return '';
    }

    protected function normalizeRecruitingActivityTime(mixed $value): ?string
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        try {
            $time = \Illuminate\Support\Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }

        // Ignore epoch/default placeholder dates that make the UI say things like "20 years ago".
        if ($time->lessThan(now()->subYears((int) config('ghl.coach_database.activity_max_age_years', 3)))) {
            return null;
        }

        if ($time->greaterThan(now()->addDay())) {
            return null;
        }

        return $time->toIso8601String();
    }

    protected function contactDisplayNameForActivity(array $contact): string
    {
        $name = $contact['name']
            ?? $contact['contactName']
            ?? trim((string) (($contact['firstName'] ?? $contact['first_name'] ?? '') . ' ' . ($contact['lastName'] ?? $contact['last_name'] ?? '')))
            ?: null;

        return filled($name) ? (string) $name : 'Coach contact';
    }

    protected function trackingActivityTitle(string $source, string $platform, array $metrics): string
    {
        $source = strtolower(trim($source));
        $platform = strtolower(trim($platform));

        if (str_contains($source, 'email_sent') || ((int) ($metrics['email_sent_count'] ?? 0) > 0 && ! str_contains($source, 'open') && ! str_contains($source, 'click'))) {
            return 'Email sent';
        }

        if (str_contains($source, 'email_open') || str_contains($source, 'open')) {
            return 'Email opened';
        }

        if (str_contains($source, 'email') && str_contains($source, 'click')) {
            return 'Email link clicked';
        }

        return match ($platform) {
            'instagram' => 'Instagram link clicked',
            'youtube' => 'YouTube link clicked',
            'x' => 'X link clicked',
            'website' => 'Website link clicked',
            'email' => 'Profile link clicked',
            default => 'Recruiting activity updated',
        };
    }

    protected function getRecruitingTrackingRecentActivityForUser(User $user, string $locationId, ?string $tokenOverride = null, array $trackingFieldMap = []): array
    {
        $contactIds = [];

        $ownerContactId = $this->resolveRecruitingTrackingOwnerContactId($user, $locationId, $tokenOverride);
        if ($ownerContactId) {
            $contactIds[] = $ownerContactId;
        }

        $contactIds = collect($contactIds)
            ->merge($this->recentRecruitingTrackingContactIdsForUser($user, $locationId))
            ->map(fn ($id): string => trim((string) $id))
            ->filter()
            ->unique()
            ->take((int) config('ghl.coach_database.tracking_activity_contact_limit', 12))
            ->values()
            ->all();

        return collect($contactIds)
            ->map(function (string $contactId) use ($locationId, $tokenOverride, $trackingFieldMap): ?array {
                $contact = $this->fetchContactForDashboard($contactId, $locationId, $tokenOverride);
                if (empty($contact)) {
                    return null;
                }

                $metrics = $this->getRecruitingTrackingMetricsFromContact($contact, $trackingFieldMap);
                if (array_sum(array_map('intval', $metrics)) <= 0) {
                    return null;
                }

                $time = $this->normalizeRecruitingActivityTime(
                    $this->stringCustomFieldFromContact($contact, ['last_profile_view_at'], $trackingFieldMap)
                        ?: ($contact['updatedAt'] ?? $contact['lastActivity'] ?? $contact['dateUpdated'] ?? null)
                );

                if (! $time) {
                    return null;
                }

                $platform = $this->stringCustomFieldFromContact($contact, ['last_clicked_platform'], $trackingFieldMap) ?: 'email';
                $source = $this->stringCustomFieldFromContact($contact, ['last_profile_view_source'], $trackingFieldMap) ?: 'tracking';
                $destination = $this->stringCustomFieldFromContact($contact, ['last_clicked_url'], $trackingFieldMap);
                $name = $this->contactDisplayNameForActivity($contact);

                $activityTitle = $this->trackingActivityTitle($source, $platform, $metrics);
                $eventCount = max(1, (int) match ($platform) {
                    'instagram' => $metrics['instagram_click_count'] ?? 0,
                    'youtube' => $metrics['youtube_click_count'] ?? 0,
                    'x' => $metrics['x_click_count'] ?? 0,
                    'website' => $metrics['website_click_count'] ?? 0,
                    'email' => $metrics['email_click_count'] ?? $metrics['email_sent_count'] ?? $metrics['email_open_count'] ?? 0,
                    default => $metrics['email_click_count'] ?? $metrics['view_profile_total'] ?? 0,
                });

                if (str_contains(strtolower($activityTitle), 'email sent')) {
                    $title = 'Email sent to ' . $name;
                    $copy = ($contact['email'] ?? $contact['school'] ?? 'Recruiting contact') . ' • ' . number_format($eventCount) . ' ' . Str::plural('email', $eventCount);
                } elseif (str_contains(strtolower($activityTitle), 'clicked')) {
                    $platformLabel = match ($platform) {
                        'instagram' => 'Instagram',
                        'youtube' => 'YouTube',
                        'x' => 'X',
                        'website' => 'Website',
                        'email' => 'Email link',
                        default => Str::headline($platform),
                    };
                    $title = $name;
                    $copy = 'Clicked ' . $platformLabel . ' ' . number_format($eventCount) . ' ' . Str::plural('time', $eventCount) . ($destination !== '' ? ' • ' . Str::limit($destination, 90) : '');
                } else {
                    $title = $activityTitle . ' to ' . $name;
                    $copy = $destination !== '' ? Str::limit($destination, 120) : ($contact['email'] ?? $contact['school'] ?? 'Recruiting contact activity');
                }

                return [
                    'type' => 'tracking',
                    'title' => $title,
                    'copy' => $copy,
                    'time' => $time,
                    'url' => null,
                    'has_image' => false,
                    'has_file' => false,
                    'metrics' => $metrics,
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $item): int => strtotime((string) ($item['time'] ?? '')) ?: 0)
            ->values()
            ->all();
    }

    protected function getAthleteRecruitingMetricSnapshot(User $user, string $locationId, ?string $tokenOverride = null): array
    {
        $token = $this->tokenForLocation($locationId, $tokenOverride);
        $trackingFieldMap = $locationId && $token
            ? $this->ensureRecruitingTrackingFieldsForLocation($locationId, $token, false)
            : [];

        $contactId = $user->ghl_contact_id ?: $this->findContactIdByEmail($user->email, $locationId, $tokenOverride);
        if (! $contactId) {
            return [];
        }

        $contact = $this->fetchContactForDashboard($contactId, $locationId, $tokenOverride);
        if (empty($contact)) {
            return [];
        }

        $keys = $this->getRecruitingMetricCustomFieldKeys();
        return [
            'profile_views' => $this->numericCustomFieldFromContact($contact, $keys['profile_views'], $trackingFieldMap),
            'link_clicks' => $this->numericCustomFieldFromContact($contact, $keys['link_clicks'], $trackingFieldMap),
            'email_opens' => $this->numericCustomFieldFromContact($contact, $keys['email_opens'], $trackingFieldMap),
            'coach_replies' => $this->numericCustomFieldFromContact($contact, $keys['coach_replies'], $trackingFieldMap),
            'emails_sent' => $this->numericCustomFieldFromContact($contact, $keys['emails_sent'], $trackingFieldMap),
            'view_profile_total' => $this->numericCustomFieldFromContact($contact, ['view_profile_total'], $trackingFieldMap),
            'view_profile_website' => $this->numericCustomFieldFromContact($contact, ['view_profile_website'], $trackingFieldMap),
            'view_profile_instagram' => $this->numericCustomFieldFromContact($contact, ['view_profile_instagram'], $trackingFieldMap),
            'view_profile_youtube' => $this->numericCustomFieldFromContact($contact, ['view_profile_youtube'], $trackingFieldMap),
            'view_profile_x' => $this->numericCustomFieldFromContact($contact, ['view_profile_x'], $trackingFieldMap),
            'view_profile_email_link' => $this->numericCustomFieldFromContact($contact, ['view_profile_email_link'], $trackingFieldMap),
            'view_profile_qr' => $this->numericCustomFieldFromContact($contact, ['view_profile_qr'], $trackingFieldMap),
            'profile_view_unique_contact_count' => $this->numericCustomFieldFromContact($contact, ['profile_view_unique_contact_count', 'unique_profile_view_count'], $trackingFieldMap),
            'profile_view_unique_school_count' => $this->numericCustomFieldFromContact($contact, ['profile_view_unique_school_count'], $trackingFieldMap),
            'profile_view_school_click_count' => $this->numericCustomFieldFromContact($contact, ['profile_view_school_click_count', 'school_profile_view_count'], $trackingFieldMap),
            'unique_profile_view_count' => $this->numericCustomFieldFromContact($contact, ['unique_profile_view_count', 'profile_view_unique_contact_count'], $trackingFieldMap),
            'unique_link_click_count' => $this->numericCustomFieldFromContact($contact, ['unique_link_click_count'], $trackingFieldMap),
            'unique_click_count' => $this->numericCustomFieldFromContact($contact, ['unique_click_count'], $trackingFieldMap),
            'school_profile_view_count' => $this->numericCustomFieldFromContact($contact, ['school_profile_view_count', 'profile_view_school_click_count'], $trackingFieldMap),
            'school_link_click_count' => $this->numericCustomFieldFromContact($contact, ['school_link_click_count'], $trackingFieldMap),
            'school_click_count' => $this->numericCustomFieldFromContact($contact, ['school_click_count'], $trackingFieldMap),
            'email_sent_count' => $this->numericCustomFieldFromContact($contact, ['email_sent_count'], $trackingFieldMap),
            'email_open_count' => $this->numericCustomFieldFromContact($contact, ['email_open_count'], $trackingFieldMap),
            'email_click_count' => $this->numericCustomFieldFromContact($contact, ['email_click_count'], $trackingFieldMap),
            'email_delivered_count' => $this->numericCustomFieldFromContact($contact, ['email_delivered_count'], $trackingFieldMap),
            'email_failed_count' => $this->numericCustomFieldFromContact($contact, ['email_failed_count'], $trackingFieldMap),
            'contact_id' => $contactId,
        ];
    }

    public function incrementRecruitingMetricForUser(User $user, string $metric, int $amount = 1): array
    {
        $credentials = $this->credentialsForUser($user);
        $locationId = $credentials['location_id'];
        $tokenOverride = $credentials['token_override'];
        $token = $this->tokenForLocation($locationId, $tokenOverride);
        $metric = strtolower(trim($metric));
        $metric = match ($metric) {
            'click', 'clicks', 'link', 'link_click', 'trigger_link_click', 'trigger_link_clicks' => 'link_clicks',
            'unique_click', 'unique_clicks', 'unique_link_click', 'unique_link_clicks' => 'unique_clicks',
            'school_click', 'school_clicks', 'overall_school_clicks' => 'school_clicks',
            'view', 'views', 'profile_view', 'profile_views' => 'profile_views',
            'unique_profile_view', 'unique_profile_views' => 'unique_profile_views',
            'open', 'opens', 'email_open', 'email_opens' => 'email_opens',
            'reply', 'replies', 'coach_reply', 'coach_replies' => 'coach_replies',
            'sent', 'email_sent', 'emails_sent' => 'emails_sent',
            default => $metric,
        };

        $keys = $this->getRecruitingMetricCustomFieldKeys();
        if (! isset($keys[$metric]) || ! $locationId || ! $token) {
            return ['success' => false, 'error' => 'Unsupported recruiting metric or missing connection.'];
        }

        $contactId = $user->ghl_contact_id ?: $this->findContactIdByEmail($user->email, $locationId, $tokenOverride);
        if (! $contactId) {
            return ['success' => false, 'error' => 'Contact not found.'];
        }

        $trackingFieldMap = $this->ensureRecruitingTrackingFieldsForLocation($locationId, $token, true);
        $contact = $this->fetchContactForDashboard($contactId, $locationId, $tokenOverride, true);
        $current = $this->numericCustomFieldFromContact($contact, $keys[$metric], $trackingFieldMap);
        $next = max(0, $current + max(1, $amount));
        $primaryKey = $this->normalizeRecruitingTrackingKey((string) ($keys[$metric][0] ?? '')) ?: (string) ($keys[$metric][0] ?? '');

        $customField = $this->recruitingTrackingFieldPayload($locationId, $primaryKey, $next);

        try {
            $response = Http::withHeaders(['Version' => config('ghl.version', '2021-07-28')])
                ->timeout((int) config('ghl.timeout', 20))
                ->withToken($token)
                ->acceptJson()
                ->asJson()
                ->put("{$this->baseUrl}/contacts/{$contactId}", ['customFields' => [$customField]]);

            if ($response->failed()) {
                Log::error('Recruiting metric increment failed.', ['metric' => $metric, 'status' => $response->status(), 'body' => $response->body()]);
                return ['success' => false, 'error' => 'Metric update failed.'];
            }

            return ['success' => true, 'metric' => $metric, 'value' => $next, 'contact_id' => $contactId];
        } catch (\Throwable $exception) {
            Log::error('Recruiting metric increment exception.', ['metric' => $metric, 'error' => $exception->getMessage()]);
            return ['success' => false, 'error' => $exception->getMessage()];
        }
    }

    protected function getRecruitingTrackingMetricsFromContact(array $contact, array $trackingFieldMap = []): array
    {
        $website = $this->numericCustomFieldFromContact($contact, ['view_profile_website'], $trackingFieldMap);
        $instagram = $this->numericCustomFieldFromContact($contact, ['view_profile_instagram'], $trackingFieldMap);
        $youtube = $this->numericCustomFieldFromContact($contact, ['view_profile_youtube'], $trackingFieldMap);
        $x = $this->numericCustomFieldFromContact($contact, ['view_profile_x'], $trackingFieldMap);
        $emailProfile = $this->numericCustomFieldFromContact($contact, ['view_profile_email_link'], $trackingFieldMap);
        $total = $this->numericCustomFieldFromContact($contact, ['view_profile_total'], $trackingFieldMap);

        if ($total <= 0) {
            $total = $website + $instagram + $youtube + $x + $emailProfile;
        }

        $emailClicks = $this->numericCustomFieldFromContact($contact, ['email_click_count'], $trackingFieldMap);
        $websiteClicks = $this->numericCustomFieldFromContact($contact, ['website_click_count'], $trackingFieldMap);
        $instagramClicks = $this->numericCustomFieldFromContact($contact, ['instagram_click_count'], $trackingFieldMap);
        $youtubeClicks = $this->numericCustomFieldFromContact($contact, ['youtube_click_count'], $trackingFieldMap);
        $xClicks = $this->numericCustomFieldFromContact($contact, ['x_click_count'], $trackingFieldMap);
        $uniqueProfileViews = $this->numericCustomFieldFromContact($contact, ['unique_profile_view_count'], $trackingFieldMap);
        $uniqueLinkClicks = $this->numericCustomFieldFromContact($contact, ['unique_link_click_count'], $trackingFieldMap);
        $uniqueClicks = $this->numericCustomFieldFromContact($contact, ['unique_click_count'], $trackingFieldMap);
        $schoolProfileViews = $this->numericCustomFieldFromContact($contact, ['school_profile_view_count'], $trackingFieldMap);
        $schoolLinkClicks = $this->numericCustomFieldFromContact($contact, ['school_link_click_count'], $trackingFieldMap);
        $schoolClicks = $this->numericCustomFieldFromContact($contact, ['school_click_count'], $trackingFieldMap);
        $profileViewUniqueContacts = $this->numericCustomFieldFromContact($contact, ['profile_view_unique_contact_count'], $trackingFieldMap);
        $profileViewUniqueSchools = $this->numericCustomFieldFromContact($contact, ['profile_view_unique_school_count'], $trackingFieldMap);
        $profileViewSchoolClicks = $this->numericCustomFieldFromContact($contact, ['profile_view_school_click_count'], $trackingFieldMap);
        $emailSent = $this->numericCustomFieldFromContact($contact, ['email_sent_count'], $trackingFieldMap);
        $emailOpen = $this->numericCustomFieldFromContact($contact, ['email_open_count'], $trackingFieldMap);
        $linkClicks = $emailClicks + $websiteClicks + $instagramClicks + $youtubeClicks + $xClicks;
        $uniqueClicks = max($uniqueClicks, $uniqueProfileViews + $uniqueLinkClicks);
        $profileViewUniqueContacts = max($profileViewUniqueContacts, $uniqueProfileViews, $total > 0 ? 1 : 0);
        $profileViewSchoolClicks = max($profileViewSchoolClicks, $schoolProfileViews, $total);
        $schoolClicks = max($schoolClicks, $schoolProfileViews + $schoolLinkClicks, $profileViewSchoolClicks + $schoolLinkClicks);
        $ghlContactClicks = $total + $linkClicks;

        return [
            'view_profile_total' => $total,
            'view_profile_website' => $website,
            'view_profile_instagram' => $instagram,
            'view_profile_youtube' => $youtube,
            'view_profile_x' => $x,
            'view_profile_email_link' => $emailProfile,
            'email_sent_count' => $emailSent,
            'email_open_count' => $emailOpen,
            'email_click_count' => $emailClicks,
            'website_click_count' => $websiteClicks,
            'instagram_click_count' => $instagramClicks,
            'youtube_click_count' => $youtubeClicks,
            'x_click_count' => $xClicks,
            'unique_profile_view_count' => $uniqueProfileViews,
            'unique_link_click_count' => $uniqueLinkClicks,
            'unique_click_count' => $uniqueClicks,
            'profile_view_unique_contact_count' => $profileViewUniqueContacts,
            'profile_view_unique_school_count' => $profileViewUniqueSchools,
            'profile_view_school_click_count' => $profileViewSchoolClicks,
            'school_profile_view_count' => $schoolProfileViews,
            'school_link_click_count' => $schoolLinkClicks,
            'school_click_count' => $schoolClicks,
            'profile_views' => $total,
            'email_opens' => $emailOpen,
            'link_clicks' => $linkClicks,
            'ghl_contact_clicks' => $ghlContactClicks,
            'contact_clicks' => $ghlContactClicks,
            'unique_contact_clicks' => $ghlContactClicks > 0 ? 1 : 0,
            'unique_profile_view_contacts' => max($profileViewUniqueContacts, $total > 0 ? 1 : 0),
            'emails_sent' => $emailSent,
        ];
    }

    protected function mergeRecruitingMetricTotals(array ...$sets): array
    {
        $keys = [
            'view_profile_total', 'view_profile_website', 'view_profile_instagram',
            'view_profile_youtube', 'view_profile_x', 'view_profile_email_link',
            'email_sent_count', 'email_open_count', 'email_click_count',
            'website_click_count', 'instagram_click_count', 'youtube_click_count', 'x_click_count',
            'unique_profile_view_count', 'unique_link_click_count', 'unique_click_count',
            'profile_view_unique_contact_count', 'profile_view_unique_school_count', 'profile_view_school_click_count',
            'school_profile_view_count', 'school_link_click_count', 'school_click_count',
            'profile_views', 'email_opens', 'link_clicks', 'emails_sent', 'ghl_contact_clicks', 'contact_clicks', 'unique_contact_clicks', 'unique_profile_view_contacts',
        ];

        $merged = array_fill_keys($keys, 0);

        foreach ($sets as $set) {
            foreach ($keys as $key) {
                $merged[$key] += (int) ($set[$key] ?? 0);
            }
        }

        return $merged;
    }

    protected function recruitingTrackingRecentContactsCacheKey(User $user, string $locationId): string
    {
        return 'recruiting-tracking-recent-contacts:' . $user->id . ':' . md5($locationId);
    }

    protected function rememberRecruitingTrackingContactId(?User $user, string $locationId, string $contactId): void
    {
        if (! $user || trim($contactId) === '' || trim($locationId) === '') {
            return;
        }

        $cacheKey = $this->recruitingTrackingRecentContactsCacheKey($user, $locationId);
        $ids = \Illuminate\Support\Facades\Cache::get($cacheKey, []);
        $ids = collect(is_array($ids) ? $ids : [])
            ->prepend(trim($contactId))
            ->filter()
            ->unique()
            ->take((int) config('ghl.coach_database.tracking_recent_contact_limit', 40))
            ->values()
            ->all();

        \Illuminate\Support\Facades\Cache::put($cacheKey, $ids, now()->addDays(30));
    }

    protected function recentRecruitingTrackingContactIdsForUser(User $user, string $locationId): array
    {
        $cacheKey = $this->recruitingTrackingRecentContactsCacheKey($user, $locationId);

        return collect(\Illuminate\Support\Facades\Cache::get($cacheKey, []))
            ->map(fn ($id): string => trim((string) $id))
            ->filter()
            ->unique()
            ->take((int) config('ghl.coach_database.tracking_recent_contact_limit', 40))
            ->values()
            ->all();
    }

    protected function getRecruitingTrackingMetricSumForUser(User $user, string $locationId, ?string $tokenOverride = null): array
    {
        $token = $this->tokenForLocation($locationId, $tokenOverride);
        $trackingFieldMap = $locationId && $token
            ? $this->ensureRecruitingTrackingFieldsForLocation($locationId, $token, false)
            : [];

        if (! $locationId || ! $token) {
            return ['success' => false, 'totals' => [], 'count' => 0, 'error' => 'Missing recruiting data connection.'];
        }

        // IMPORTANT: do not fetch every contact detail on dashboard load.
        // Full contact detail calls are slow and can exceed PHP's 30-second limit.
        // Instead, read only the sender aggregate contact plus contacts that were
        // actually touched by tracking routes / email sends and remembered in cache.
        $contactIds = [];

        $ownerContactId = $this->resolveRecruitingTrackingOwnerContactId($user, $locationId, $tokenOverride);
        if ($ownerContactId) {
            $contactIds[] = $ownerContactId;
        }

        $contactIds = collect($contactIds)
            ->merge($this->recentRecruitingTrackingContactIdsForUser($user, $locationId))
            ->map(fn ($id): string => trim((string) $id))
            ->filter()
            ->unique()
            ->take((int) config('ghl.coach_database.tracking_dashboard_detail_limit', 25))
            ->values()
            ->all();

        $totals = [];
        $trackedContacts = 0;
        $schoolClickGroups = [];

        foreach ($contactIds as $contactId) {
            $contact = $this->fetchContactForDashboard($contactId, $locationId, $tokenOverride);
            if (empty($contact)) {
                continue;
            }

            $metrics = $this->getRecruitingTrackingMetricsFromContact($contact, $trackingFieldMap);
            if (array_sum(array_map('intval', $metrics)) <= 0) {
                continue;
            }

            $coachRow = $this->transformCoachContact($contact, $trackingFieldMap);
            $schoolName = trim((string) ($coachRow['school'] ?? $coachRow['company_name'] ?? data_get($contact, 'companyName') ?? data_get($contact, 'business.name') ?? ''));
            $businessId = trim((string) ($coachRow['business_id'] ?? data_get($contact, 'businessId') ?? data_get($contact, 'business.id') ?? ''));
            $schoolKey = $businessId !== '' ? 'business:' . $businessId : 'school:' . strtolower($schoolName);
            $schoolTotalClicks = (int) ($metrics['school_click_count'] ?? 0);
            if ($schoolTotalClicks <= 0) {
                $schoolTotalClicks = (int) ($metrics['view_profile_total'] ?? 0) + (int) ($metrics['link_clicks'] ?? 0);
            }

            if ($schoolName !== '' && $schoolTotalClicks > 0) {
                $schoolClickGroups[$schoolKey] ??= [
                    'school' => $schoolName,
                    'business_id' => $businessId ?: null,
                    'logo_url' => $coachRow['school_logo_url'] ?? $coachRow['business_logo_url'] ?? $coachRow['logo_url'] ?? null,
                    'clicks' => 0,
                    'profile_views' => 0,
                    'link_clicks' => 0,
                    'unique_clicks' => 0,
                    'coach_contact_ids' => [],
                    'coach_count' => 0,
                ];

                $schoolClickGroups[$schoolKey]['clicks'] += $schoolTotalClicks;
                $schoolClickGroups[$schoolKey]['profile_views'] += (int) ($metrics['view_profile_total'] ?? 0);
                $schoolClickGroups[$schoolKey]['link_clicks'] += (int) ($metrics['link_clicks'] ?? 0);
                $schoolClickGroups[$schoolKey]['unique_clicks'] += (int) ($metrics['unique_click_count'] ?? 0);
                $schoolClickGroups[$schoolKey]['coach_contact_ids'][] = $contactId;
                $schoolClickGroups[$schoolKey]['coach_contact_ids'] = array_values(array_unique($schoolClickGroups[$schoolKey]['coach_contact_ids']));
                $schoolClickGroups[$schoolKey]['coach_count'] = count($schoolClickGroups[$schoolKey]['coach_contact_ids']);
            }

            $totals[] = $metrics;
            $trackedContacts++;
        }

        $merged = ! empty($totals)
            ? $this->mergeRecruitingMetricTotals(...$totals)
            : $this->mergeRecruitingMetricTotals([]);

        $uniqueProfileViewContacts = collect($totals)
            ->filter(fn (array $metrics): bool => (int) ($metrics['view_profile_total'] ?? $metrics['profile_views'] ?? 0) > 0)
            ->count();
        $uniqueLinkClickContacts = collect($totals)
            ->filter(fn (array $metrics): bool => (int) ($metrics['link_clicks'] ?? 0) > 0)
            ->count();

        $schoolClickGroups = collect($schoolClickGroups)
            ->sortByDesc(fn (array $school): int => (int) ($school['clicks'] ?? 0))
            ->values()
            ->all();

        $schoolClicksTotal = collect($schoolClickGroups)->sum(fn (array $school): int => (int) ($school['clicks'] ?? 0));
        $profileViewSchoolClicks = collect($schoolClickGroups)->sum(fn (array $school): int => (int) ($school['profile_views'] ?? 0));
        $profileViewSchools = collect($schoolClickGroups)->filter(fn (array $school): bool => (int) ($school['profile_views'] ?? 0) > 0)->count();
        $merged['profile_view_unique_contact_count'] = max((int) ($merged['profile_view_unique_contact_count'] ?? 0), $uniqueProfileViewContacts);
        $merged['profile_view_unique_school_count'] = max((int) ($merged['profile_view_unique_school_count'] ?? 0), $profileViewSchools);
        $merged['profile_view_school_click_count'] = max((int) ($merged['profile_view_school_click_count'] ?? 0), $profileViewSchoolClicks, (int) ($merged['school_profile_view_count'] ?? 0));
        $merged['unique_profile_view_contacts'] = max((int) ($merged['unique_profile_view_contacts'] ?? 0), $uniqueProfileViewContacts);
        $merged['unique_profile_views'] = max((int) ($merged['unique_profile_views'] ?? 0), $uniqueProfileViewContacts);
        $merged['unique_link_click_contacts'] = max((int) ($merged['unique_link_click_contacts'] ?? 0), $uniqueLinkClickContacts);
        $merged['unique_clicks'] = max((int) ($merged['unique_clicks'] ?? 0), $uniqueLinkClickContacts);
        $merged['contact_link_clicks'] = max((int) ($merged['contact_link_clicks'] ?? 0), (int) ($merged['link_clicks'] ?? 0));
        $merged['school_clicks_total'] = max((int) ($merged['school_clicks_total'] ?? 0), $schoolClicksTotal);
        $merged['school_link_clicks'] = max((int) ($merged['school_link_clicks'] ?? 0), $schoolClicksTotal);
        $merged['schools_with_clicks'] = max((int) ($merged['schools_with_clicks'] ?? 0), count($schoolClickGroups));

        return [
            'success' => true,
            'totals' => $merged,
            'schools' => $schoolClickGroups,
            'count' => $trackedContacts,
            'debug' => [[
                'stage' => 'tracking_metrics_from_recent_contacts',
                'contact_ids_checked' => count($contactIds),
                'tracked_contacts' => $trackedContacts,
            ]],
        ];
    }

    public function getRecruitingDashboardActivityForUser(User $user): array
    {
        $credentials = $this->credentialsForUser($user);
        $locationId = $credentials['location_id'];
        $token = $this->tokenForLocation($locationId, $credentials['token_override']);

        if (! $locationId || ! $token) {
            return ['success' => false, 'stats' => [], 'recent_activity' => [], 'conversations' => [], 'error' => 'Missing recruiting data connection.'];
        }

        $trackingFieldMap = $this->ensureRecruitingTrackingFieldsForLocation($locationId, $token, false);
        $athleteMetrics = $this->getAthleteRecruitingMetricSnapshot($user, $locationId, $credentials['token_override']);
        $contactMetricSummary = $this->getRecruitingTrackingMetricSumForUser($user, $locationId, $credentials['token_override']);
        $trackingRecentActivity = $this->getRecruitingTrackingRecentActivityForUser($user, $locationId, $credentials['token_override'], $trackingFieldMap);
        $contactMetrics = is_array($contactMetricSummary['totals'] ?? null) ? $contactMetricSummary['totals'] : [];

        $schoolClickGroups = is_array($contactMetricSummary['schools'] ?? null) ? array_values($contactMetricSummary['schools']) : [];

        $trackedEmailSent = max((int) ($athleteMetrics['email_sent_count'] ?? 0), (int) ($contactMetrics['email_sent_count'] ?? 0));
        $trackedEmailOpens = max((int) ($athleteMetrics['email_open_count'] ?? 0), (int) ($contactMetrics['email_open_count'] ?? 0));
        $trackedEmailClicks = max((int) ($athleteMetrics['email_click_count'] ?? 0), (int) ($contactMetrics['email_click_count'] ?? 0));
        $trackedWebsiteClicks = max((int) ($athleteMetrics['website_click_count'] ?? 0), (int) ($contactMetrics['website_click_count'] ?? 0));
        $trackedInstagramClicks = max((int) ($athleteMetrics['instagram_click_count'] ?? 0), (int) ($contactMetrics['instagram_click_count'] ?? 0));
        $trackedYoutubeClicks = max((int) ($athleteMetrics['youtube_click_count'] ?? 0), (int) ($contactMetrics['youtube_click_count'] ?? 0));
        $trackedXClicks = max((int) ($athleteMetrics['x_click_count'] ?? 0), (int) ($contactMetrics['x_click_count'] ?? 0));
        $trackedProfileTotal = max((int) ($athleteMetrics['view_profile_total'] ?? 0), (int) ($contactMetrics['view_profile_total'] ?? 0));
        $trackedWebsiteViews = max((int) ($athleteMetrics['view_profile_website'] ?? 0), (int) ($contactMetrics['view_profile_website'] ?? 0));
        $trackedInstagramViews = max((int) ($athleteMetrics['view_profile_instagram'] ?? 0), (int) ($contactMetrics['view_profile_instagram'] ?? 0));
        $trackedYoutubeViews = max((int) ($athleteMetrics['view_profile_youtube'] ?? 0), (int) ($contactMetrics['view_profile_youtube'] ?? 0));
        $trackedXViews = max((int) ($athleteMetrics['view_profile_x'] ?? 0), (int) ($contactMetrics['view_profile_x'] ?? 0));
        $trackedEmailProfileLinks = max((int) ($athleteMetrics['view_profile_email_link'] ?? 0), (int) ($contactMetrics['view_profile_email_link'] ?? 0));
        $trackedUniqueProfileViews = max((int) ($athleteMetrics['unique_profile_view_count'] ?? 0), (int) ($contactMetrics['unique_profile_view_count'] ?? 0));
        $trackedUniqueLinkClicks = max((int) ($athleteMetrics['unique_link_click_count'] ?? 0), (int) ($contactMetrics['unique_link_click_count'] ?? 0));
        $trackedUniqueClicks = max((int) ($athleteMetrics['unique_click_count'] ?? 0), (int) ($contactMetrics['unique_click_count'] ?? 0), $trackedUniqueProfileViews + $trackedUniqueLinkClicks);
        $trackedSchoolProfileViews = max((int) ($athleteMetrics['school_profile_view_count'] ?? 0), (int) ($contactMetrics['school_profile_view_count'] ?? 0));
        $trackedSchoolLinkClicks = max((int) ($athleteMetrics['school_link_click_count'] ?? 0), (int) ($contactMetrics['school_link_click_count'] ?? 0));
        $trackedSchoolClicks = max((int) ($athleteMetrics['school_click_count'] ?? 0), (int) ($contactMetrics['school_click_count'] ?? 0), $trackedSchoolProfileViews + $trackedSchoolLinkClicks);
        $trackedProfileSchoolClicks = max(
            (int) ($athleteMetrics['profile_view_school_click_count'] ?? 0),
            (int) ($contactMetrics['profile_view_school_click_count'] ?? 0),
            collect($schoolClickGroups)->sum(fn (array $school): int => (int) ($school['profile_views'] ?? 0)),
            $trackedSchoolProfileViews,
            $trackedProfileTotal
        );
        $trackedUniqueProfileContacts = max(
            (int) ($athleteMetrics['profile_view_unique_contact_count'] ?? 0),
            (int) ($contactMetrics['profile_view_unique_contact_count'] ?? 0),
            $trackedUniqueProfileViews
        );
        $trackedUniqueProfileSchools = max(
            (int) ($athleteMetrics['profile_view_unique_school_count'] ?? 0),
            (int) ($contactMetrics['profile_view_unique_school_count'] ?? 0),
            collect($schoolClickGroups)->filter(fn (array $school): bool => (int) ($school['profile_views'] ?? 0) > 0)->count()
        );

        if ($trackedProfileTotal <= 0) {
            $trackedProfileTotal = $trackedWebsiteViews + $trackedInstagramViews + $trackedYoutubeViews + $trackedXViews + $trackedEmailProfileLinks;
        }
        $trackedUniqueProfileContacts = max($trackedUniqueProfileContacts, $trackedProfileTotal > 0 ? 1 : 0);
        $trackedProfileSchoolClicks = max($trackedProfileSchoolClicks, $trackedProfileTotal);

        $conversations = [];
        $conversationResult = $this->getConversationsForUser($user, ['limit' => 40, 'status' => 'all']);
        if ($conversationResult['success'] ?? false) {
            $conversations = $conversationResult['conversations'] ?? [];
        }

        $personalEmailsSent = collect($conversations)->filter(function (array $conversation): bool {
            $last = strtolower((string) ($conversation['last_message'] ?? ''));
            $status = strtolower((string) ($conversation['status'] ?? ''));
            $direction = strtolower((string) ($conversation['direction'] ?? $conversation['last_message_direction'] ?? data_get($conversation, 'lastMessage.direction') ?? ''));
            return str_contains($status, 'sent')
                || str_contains($direction, 'out')
                || str_contains($last, 'sent')
                || str_contains($last, 'emailed');
        })->count();

        $conversationReplies = collect($conversations)->filter(function (array $conversation): bool {
            $status = strtolower((string) ($conversation['status'] ?? ''));
            $direction = strtolower((string) ($conversation['direction'] ?? $conversation['last_message_direction'] ?? data_get($conversation, 'lastMessage.direction') ?? ''));
            return (int) ($conversation['unread_count'] ?? 0) > 0
                || str_contains($status, 'unread')
                || str_contains($status, 'replied')
                || str_contains($direction, 'inbound')
                || str_contains($direction, 'incoming');
        })->count();

        $campaignSummary = $this->getEmailCampaignActivityForLocation($locationId, $token);
        $campaignEmailsSent = (int) ($campaignSummary['emails_sent'] ?? 0) + $personalEmailsSent;
        $emailsSent = max($campaignEmailsSent, (int) ($athleteMetrics['emails_sent'] ?? 0), $trackedEmailSent);
        $trackedEmailSent = max($trackedEmailSent, $emailsSent);
        $coachReplies = max((int) ($campaignSummary['coach_replies'] ?? 0) + $conversationReplies, (int) ($athleteMetrics['coach_replies'] ?? 0));
        $linkClicks = max((int) ($campaignSummary['trigger_link_clicks'] ?? 0), (int) ($athleteMetrics['link_clicks'] ?? 0), (int) ($contactMetrics['link_clicks'] ?? 0), $trackedEmailClicks + $trackedWebsiteClicks + $trackedInstagramClicks + $trackedYoutubeClicks + $trackedXClicks);
        $ghlContactClicks = max((int) ($athleteMetrics['ghl_contact_clicks'] ?? 0), (int) ($contactMetrics['ghl_contact_clicks'] ?? 0), $trackedProfileTotal + $linkClicks);
        $emailOpens = max((int) ($campaignSummary['email_opens'] ?? 0), (int) ($athleteMetrics['email_opens'] ?? 0), $trackedEmailOpens);
        $emailOpenRate = (int) ($campaignSummary['email_open_rate'] ?? 0);
        if ($emailOpenRate <= 0 && $emailsSent > 0 && $emailOpens > 0) {
            $emailOpenRate = (int) round(($emailOpens / max(1, $emailsSent)) * 100);
        }

        $recent = collect($trackingRecentActivity)
            ->merge(collect($conversations)
                ->map(function (array $conversation): ?array {
                    $time = $this->normalizeRecruitingActivityTime(
                        $conversation['last_message_at']
                        ?? $conversation['lastMessageAt']
                        ?? $conversation['lastMessageDate']
                        ?? $conversation['updatedAt']
                        ?? null
                    );

                    if (! $time) {
                        return null;
                    }

                    $rawLast = (string) ($conversation['last_message'] ?? 'Recent email conversation activity');
                    $copy = trim(strip_tags($rawLast));
                    $hasImage = (bool) ($conversation['has_image'] ?? false) || preg_match('/<img\b|\.(png|jpe?g|gif|webp)(\?|$)/i', $rawLast);
                    $hasFile = (bool) ($conversation['has_file'] ?? false) || (! $hasImage && preg_match('/\.(pdf|docx?|xlsx?|pptx?|zip)(\?|$)/i', $rawLast));

                    return [
                        'type' => 'conversation',
                        'title' => $conversation['contact_name'] ?? $conversation['name'] ?? 'Coach conversation',
                        'copy' => Str::limit(preg_replace('/\s+/', ' ', $copy), 160),
                        'time' => $time,
                        'url' => null,
                        'has_image' => $hasImage,
                        'has_file' => $hasFile,
                        'metrics' => ['coach_replies' => 1],
                    ];
                })
                ->filter())
            ->merge(collect($campaignSummary['recent_activity'] ?? [])->map(function (array $item): ?array {
                $time = $this->normalizeRecruitingActivityTime($item['time'] ?? null);
                if (! $time) {
                    return null;
                }
                $item['time'] = $time;
                return $item;
            })->filter())
            ->sortByDesc(fn (array $item): int => strtotime((string) ($item['time'] ?? '')) ?: 0)
            ->take(8)
            ->values()
            ->all();

        return [
            'success' => true,
            'stats' => [
                'emails_sent' => $emailsSent,
                'personal_emails_sent' => $personalEmailsSent,
                'campaigns_sent' => (int) ($campaignSummary['campaigns_sent'] ?? 0),
                'email_open_rate' => $emailOpenRate,
                'email_opens' => $emailOpens,
                'email_open_count' => $trackedEmailOpens,
                'email_sent_count' => $trackedEmailSent,
                'email_click_count' => $trackedEmailClicks,
                'website_click_count' => $trackedWebsiteClicks,
                'instagram_click_count' => $trackedInstagramClicks,
                'youtube_click_count' => $trackedYoutubeClicks,
                'x_click_count' => $trackedXClicks,
                'profile_view_unique_contact_count' => $trackedUniqueProfileContacts,
                'profile_view_unique_school_count' => $trackedUniqueProfileSchools,
                'profile_view_school_click_count' => $trackedProfileSchoolClicks,
                'unique_profile_view_count' => $trackedUniqueProfileViews,
                'unique_link_click_count' => $trackedUniqueLinkClicks,
                'unique_click_count' => $trackedUniqueClicks,
                'unique_contact_clicks' => $trackedUniqueClicks,
                'unique_profile_view_contacts' => max($trackedUniqueProfileContacts, $trackedUniqueProfileViews, (int) ($contactMetrics['unique_profile_view_contacts'] ?? 0)),
                'unique_profile_views' => max($trackedUniqueProfileContacts, $trackedUniqueProfileViews, (int) ($contactMetrics['unique_profile_views'] ?? 0)),
                'unique_link_click_contacts' => max($trackedUniqueLinkClicks, (int) ($contactMetrics['unique_link_click_contacts'] ?? 0)),
                'unique_clicks' => max($trackedUniqueClicks, $trackedUniqueProfileViews + $trackedUniqueLinkClicks, (int) ($contactMetrics['unique_clicks'] ?? 0)),
                'contact_link_clicks' => max($ghlContactClicks, (int) ($contactMetrics['contact_link_clicks'] ?? 0), (int) ($contactMetrics['ghl_contact_clicks'] ?? 0)),
                'ghl_contact_clicks' => max($ghlContactClicks, (int) ($contactMetrics['ghl_contact_clicks'] ?? 0)),
                'school_profile_view_count' => $trackedSchoolProfileViews,
                'school_link_click_count' => $trackedSchoolLinkClicks,
                'school_click_count' => $trackedSchoolClicks,
                'overall_school_clicks' => max($trackedSchoolClicks, (int) ($contactMetrics['overall_school_clicks'] ?? 0), (int) ($contactMetrics['school_clicks_total'] ?? 0)),
                'school_clicks_total' => max($trackedSchoolClicks, (int) ($contactMetrics['overall_school_clicks'] ?? 0), (int) ($contactMetrics['school_clicks_total'] ?? 0)),
                'school_link_clicks' => max($trackedSchoolLinkClicks, (int) ($contactMetrics['school_link_clicks'] ?? 0)),
                'schools_with_clicks' => max(count($schoolClickGroups), (int) ($contactMetrics['schools_with_clicks'] ?? 0)),
                'school_profile_views' => $trackedSchoolProfileViews,
                'school_clicks' => $schoolClickGroups,
                'link_clicks' => $linkClicks,
                'trigger_link_clicks' => $linkClicks,
                'coach_replies' => $coachReplies,
                'profile_views' => $trackedProfileTotal,
                'view_profile_total' => $trackedProfileTotal,
                'view_profile_website' => $trackedWebsiteViews,
                'view_profile_instagram' => $trackedInstagramViews,
                'view_profile_youtube' => $trackedYoutubeViews,
                'view_profile_x' => $trackedXViews,
                'view_profile_email_link' => $trackedEmailProfileLinks,
            ],
            'sparks' => $this->dashboardSparksFromRecent($recent, [
                'profile_views' => (int) ($athleteMetrics['profile_views'] ?? 0),
                'link_clicks' => $linkClicks,
                'email_open_rate' => $emailOpenRate,
                'coach_replies' => $coachReplies,
            ]),
            'recent_activity' => $recent,
            'conversations' => $conversations,
            'debug' => array_merge($campaignSummary['debug'] ?? [], [
                'custom_metric_contact_id' => $athleteMetrics['contact_id'] ?? null,
                'tracked_contacts_count' => $contactMetricSummary['count'] ?? 0,
                'tracking_metric_source' => 'contact_custom_fields',
            ]),
        ];
    }

    protected function getEmailCampaignActivityForLocation(string $locationId, string $token): array
    {
        $attempts = [
            ['version' => '2021-07-28', 'url' => "{$this->baseUrl}/emails/public/v2/locations/{$locationId}/campaigns/email-campaign", 'params' => ['limit' => 100]],
            ['version' => '2021-07-28', 'url' => "{$this->baseUrl}/emails/public/v2/locations/{$locationId}/campaigns", 'params' => ['limit' => 100]],
            ['version' => 'v3', 'url' => "{$this->baseUrl}/emails/locations/{$locationId}/campaigns", 'params' => ['limit' => 100]],
            ['version' => '2023-02-21', 'url' => "{$this->baseUrl}/campaigns/", 'params' => ['locationId' => $locationId, 'limit' => 100]],
        ];

        $campaigns = collect();
        $debug = [];

        foreach ($attempts as $attempt) {
            try {
                $response = Http::withHeaders(['Version' => $attempt['version']])
                    ->timeout((int) config('ghl.timeout', 20))
                    ->withToken($token)
                    ->acceptJson()
                    ->get($attempt['url'], $attempt['params']);

                $data = $response->json() ?? [];
                $items = $this->normalizeResponseList($data['campaigns'] ?? $data['data'] ?? $data['items'] ?? $data['results'] ?? $data, ['id', '_id', 'campaignId', 'name', 'title', 'status']);
                $debug[] = ['source' => $attempt['url'], 'status' => $response->status(), 'count' => count($items)];

                if ($response->successful() && ! empty($items)) {
                    $campaigns = $campaigns->merge($items);
                }
            } catch (\Throwable $e) {
                $debug[] = ['source' => $attempt['url'], 'error' => $e->getMessage()];
            }
        }

        $campaigns = $campaigns
            ->filter(fn ($item): bool => is_array($item))
            ->unique(fn (array $item): string => (string) ($item['id'] ?? $item['_id'] ?? $item['campaignId'] ?? md5(json_encode($item))))
            ->values();

        $emailsSent = 0;
        $opens = 0;
        $clicks = 0;
        $replies = 0;
        $sentCampaigns = 0;
        $recent = [];

        foreach ($campaigns as $campaign) {
            $status = strtolower((string) ($campaign['status'] ?? $campaign['campaignStatus'] ?? data_get($campaign, 'data.status') ?? ''));
            $sent = $this->firstNumericValue($campaign, ['sent', 'sentCount', 'emailsSent', 'totalSent', 'stats.sent', 'statistics.sent', 'analytics.sent', 'data.sent', 'data.stats.sent']);
            $open = $this->firstNumericValue($campaign, ['opens', 'openCount', 'emailOpens', 'uniqueOpens', 'stats.opens', 'statistics.opens', 'analytics.opens', 'data.stats.opens']);
            $click = $this->firstNumericValue($campaign, ['clicks', 'clickCount', 'linkClicks', 'uniqueClicks', 'triggerLinkClicks', 'profileClicks', 'websiteClicks', 'socialClicks', 'instagramClicks', 'xClicks', 'twitterClicks', 'youtubeClicks', 'highlightClicks', 'stats.clicks', 'statistics.clicks', 'analytics.clicks', 'data.stats.clicks']);
            $reply = $this->firstNumericValue($campaign, ['replies', 'replyCount', 'stats.replies', 'statistics.replies', 'analytics.replies', 'data.stats.replies']);

            $emailsSent += $sent;
            $opens += $open;
            $clicks += $click;
            $replies += $reply;

            if ($sent > 0 || in_array($status, ['sent', 'completed', 'published', 'delivered'], true)) {
                $sentCampaigns++;
            }

            $time = $this->normalizeRecruitingActivityTime($campaign['sentAt'] ?? $campaign['lastSentAt'] ?? $campaign['updatedAt'] ?? $campaign['createdAt'] ?? data_get($campaign, 'data.updatedAt'));
            if ($time) {
                $recent[] = [
                    'type' => 'campaign',
                    'title' => $campaign['name'] ?? $campaign['title'] ?? $campaign['campaignName'] ?? 'Email campaign',
                    'copy' => ($sent > 0 ? number_format($sent) . ' emails sent' : 'Campaign activity updated'),
                    'time' => $time,
                    'url' => null,
                    'has_image' => false,
                    'has_file' => false,
                    'metrics' => ['emails_sent' => $sent, 'email_opens' => $open, 'link_clicks' => $click, 'coach_replies' => $reply],
                ];
            }
        }

        return [
            'campaigns_sent' => $sentCampaigns,
            'emails_sent' => $emailsSent > 0 ? $emailsSent : $sentCampaigns,
            'email_opens' => $opens,
            'email_open_rate' => $emailsSent > 0 ? (int) round(($opens / max(1, $emailsSent)) * 100) : 0,
            'trigger_link_clicks' => $clicks,
            'coach_replies' => $replies,
            'recent_activity' => $recent,
            'debug' => $debug,
        ];
    }


    protected function dashboardSparksFromRecent(array $recent, array $totals): array
    {
        $buckets = array_fill(0, 7, ['profile_views' => 0, 'link_clicks' => 0, 'email_open_rate' => 0, 'coach_replies' => 0]);
        foreach ($recent as $item) {
            $time = strtotime((string) ($item['time'] ?? '')) ?: time();
            $daysAgo = max(0, min(6, (int) floor((time() - $time) / 86400)));
            $index = 6 - $daysAgo;
            $type = strtolower((string) ($item['type'] ?? ''));
            $copy = strtolower((string) ($item['copy'] ?? ''));
            $metrics = is_array($item['metrics'] ?? null) ? $item['metrics'] : [];
            $buckets[$index]['coach_replies'] += (int) ($metrics['coach_replies'] ?? 0);
            $buckets[$index]['link_clicks'] += (int) ($metrics['link_clicks'] ?? 0);
            $buckets[$index]['email_open_rate'] += (int) ($metrics['email_opens'] ?? 0);
            $buckets[$index]['profile_views'] += (int) ($metrics['profile_views'] ?? 0);
            if (str_contains($type, 'conversation') || str_contains($copy, 'reply')) {
                $buckets[$index]['coach_replies']++;
            }
            if (str_contains($copy, 'click')) {
                $buckets[$index]['link_clicks']++;
            }
            if (str_contains($copy, 'open')) {
                $buckets[$index]['email_open_rate']++;
            }
            if (str_contains($copy, 'view')) {
                $buckets[$index]['profile_views']++;
            }
        }

        $fallback = function (int $total): array {
            if ($total <= 0) {
                return [0, 1, 0, 2, 1, 3, 1];
            }
            $base = max(1, (int) floor($total / 7));
            return [$base, $base + 1, $base, $base + 2, max(0, $base - 1), $base + 1, max(1, $total - ($base * 5))];
        };

        $series = [];
        foreach (['profile_views', 'link_clicks', 'email_open_rate', 'coach_replies'] as $key) {
            $values = array_map(fn ($bucket) => (int) ($bucket[$key] ?? 0), $buckets);
            $series[$key] = array_sum($values) > 0 ? $values : $fallback((int) ($totals[$key] ?? 0));
        }

        return $series;
    }

    protected function firstNumericValue(array $source, array $paths): int
    {
        foreach ($paths as $path) {
            $value = data_get($source, $path);
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return 0;
    }


/**
     * Contact custom fields used by Recruiting Center tracking.
     * These are created at the Recruiting Center location level and then written onto contacts.
     */
    public function recruitingTrackingCustomFieldDefinitions(): array
    {
        return [
            'view_profile_total' => ['name' => 'view_profile_total', 'dataType' => 'NUMERICAL'],
            'view_profile_website' => ['name' => 'view_profile_website', 'dataType' => 'NUMERICAL'],
            'view_profile_instagram' => ['name' => 'view_profile_instagram', 'dataType' => 'NUMERICAL'],
            'view_profile_youtube' => ['name' => 'view_profile_youtube', 'dataType' => 'NUMERICAL'],
            'view_profile_x' => ['name' => 'view_profile_x', 'dataType' => 'NUMERICAL'],
            'view_profile_email_link' => ['name' => 'view_profile_email_link', 'dataType' => 'NUMERICAL'],
            'view_profile_qr' => ['name' => 'view_profile_qr', 'dataType' => 'NUMERICAL'],
            'profile_view_unique_contact_count' => ['name' => 'profile_view_unique_contact_count', 'dataType' => 'NUMERICAL'],
            'profile_view_unique_school_count' => ['name' => 'profile_view_unique_school_count', 'dataType' => 'NUMERICAL'],
            'profile_view_school_click_count' => ['name' => 'profile_view_school_click_count', 'dataType' => 'NUMERICAL'],
            'email_sent_count' => ['name' => 'email_sent_count', 'dataType' => 'NUMERICAL'],
            'email_open_count' => ['name' => 'email_open_count', 'dataType' => 'NUMERICAL'],
            'email_click_count' => ['name' => 'email_click_count', 'dataType' => 'NUMERICAL'],
            'website_click_count' => ['name' => 'website_click_count', 'dataType' => 'NUMERICAL'],
            'instagram_click_count' => ['name' => 'instagram_click_count', 'dataType' => 'NUMERICAL'],
            'youtube_click_count' => ['name' => 'youtube_click_count', 'dataType' => 'NUMERICAL'],
            'x_click_count' => ['name' => 'x_click_count', 'dataType' => 'NUMERICAL'],
            'unique_profile_view_count' => ['name' => 'unique_profile_view_count', 'dataType' => 'NUMERICAL'],
            'unique_link_click_count' => ['name' => 'unique_link_click_count', 'dataType' => 'NUMERICAL'],
            'unique_click_count' => ['name' => 'unique_click_count', 'dataType' => 'NUMERICAL'],
            'school_profile_view_count' => ['name' => 'school_profile_view_count', 'dataType' => 'NUMERICAL'],
            'school_link_click_count' => ['name' => 'school_link_click_count', 'dataType' => 'NUMERICAL'],
            'school_click_count' => ['name' => 'school_click_count', 'dataType' => 'NUMERICAL'],
            'email_delivered_count' => ['name' => 'email_delivered_count', 'dataType' => 'NUMERICAL'],
            'email_failed_count' => ['name' => 'email_failed_count', 'dataType' => 'NUMERICAL'],
            'last_email_status' => ['name' => 'last_email_status', 'dataType' => 'TEXT'],
            'last_email_message_id' => ['name' => 'last_email_message_id', 'dataType' => 'TEXT'],
            'last_email_sent_at' => ['name' => 'last_email_sent_at', 'dataType' => 'TEXT'],
            'last_profile_view_source' => ['name' => 'last_profile_view_source', 'dataType' => 'TEXT'],
            'last_profile_view_at' => ['name' => 'last_profile_view_at', 'dataType' => 'TEXT'],
            'last_clicked_url' => ['name' => 'last_clicked_url', 'dataType' => 'TEXT'],
            'last_clicked_platform' => ['name' => 'last_clicked_platform', 'dataType' => 'TEXT'],
            'last_tracking_host' => ['name' => 'last_tracking_host', 'dataType' => 'TEXT'],
            'last_profile_view_contact_id' => ['name' => 'last_profile_view_contact_id', 'dataType' => 'TEXT'],
            'last_profile_view_coach_name' => ['name' => 'last_profile_view_coach_name', 'dataType' => 'TEXT'],
            'last_profile_view_coach_email' => ['name' => 'last_profile_view_coach_email', 'dataType' => 'TEXT'],
            'last_profile_view_school' => ['name' => 'last_profile_view_school', 'dataType' => 'TEXT'],
            'last_profile_view_business_id' => ['name' => 'last_profile_view_business_id', 'dataType' => 'TEXT'],
            'last_profile_view_school_logo_url' => ['name' => 'last_profile_view_school_logo_url', 'dataType' => 'TEXT'],
            'last_profile_view_platform' => ['name' => 'last_profile_view_platform', 'dataType' => 'TEXT'],
            'last_profile_view_url' => ['name' => 'last_profile_view_url', 'dataType' => 'TEXT'],
            'last_profile_view_referrer' => ['name' => 'last_profile_view_referrer', 'dataType' => 'TEXT'],
            'last_profile_view_utm_source' => ['name' => 'last_profile_view_utm_source', 'dataType' => 'TEXT'],
            'last_profile_view_utm_medium' => ['name' => 'last_profile_view_utm_medium', 'dataType' => 'TEXT'],
            'last_profile_view_utm_campaign' => ['name' => 'last_profile_view_utm_campaign', 'dataType' => 'TEXT'],
            'last_profile_view_fingerprint' => ['name' => 'last_profile_view_fingerprint', 'dataType' => 'TEXT'],
            'profile_view_event_json' => ['name' => 'profile_view_event_json', 'dataType' => 'TEXT'],
        ];
    }

    /**
     * Resolve the Recruiting Center custom-field map once per request/cache window so contact transforms
     * can read custom counters by their real field IDs without one API call per contact.
     */
    protected function recruitingTrackingFieldMapForLocation(?string $locationId, ?string $tokenOverride = null, bool $force = false): array
    {
        $locationId = $locationId ?: config('ghl.location_id');
        $token = $this->tokenForLocation($locationId, $tokenOverride);

        if (! $locationId || ! $token) {
            return [];
        }

        try {
            return $this->ensureRecruitingTrackingFieldsForLocation($locationId, $token, $force);
        } catch (\Throwable $exception) {
            Log::warning('Unable to resolve Recruiting Center tracking field map.', [
                'location_id' => $locationId,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Add attribution query parameters to player-profile links before sending them to coaches.
     * The website tracking endpoint can then classify the hit by coach/contact, school, source,
     * and platform without guessing from a raw referrer.
     */
    public function appendRecruitingTrackingQueryParameters(string $url, ?User $user = null, array $coach = [], string $platform = 'website', string $source = 'coach_database'): string
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '#') || str_starts_with(strtolower($url), 'mailto:') || str_starts_with(strtolower($url), 'tel:')) {
            return $url;
        }

        $platform = $this->normalizeRecruitingTrackingPlatform($platform);
        $athleteId = $user?->id;
        $contactId = trim((string) ($coach['id'] ?? $coach['contact_id'] ?? $coach['ghl_contact_id'] ?? ''));
        $school = trim((string) ($coach['school'] ?? $coach['company_name'] ?? $coach['school_name'] ?? ''));
        $businessId = trim((string) ($coach['business_id'] ?? $coach['ghl_business_id'] ?? $coach['company_id'] ?? ''));
        $email = trim((string) ($coach['email'] ?? $coach['coach_email'] ?? ''));

        $fingerprint = substr(hash('sha256', implode('|', array_filter([
            (string) $athleteId,
            $contactId,
            $email,
            $businessId,
            strtolower($school),
            $platform,
        ], fn ($value): bool => $value !== ''))), 0, 24);

        $trackingPayload = array_filter([
            'u' => $athleteId,
            'c' => $contactId,
            'b' => $businessId,
            'school' => $school,
            'p' => $platform,
            'e' => 'profile_view',
            's' => $source,
            'd' => $url,
            'ts' => now()->timestamp,
        ], fn ($value): bool => ! is_null($value) && $value !== '');
        $trackingContext = rtrim(strtr(base64_encode(json_encode($trackingPayload, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
        $trackingSignature = substr(hash_hmac('sha256', $trackingContext, (string) config('app.key', 'plyrcard')), 0, 24);

        $query = array_filter([
            'utm_source' => 'plyrcard_recruiting',
            'utm_medium' => $platform,
            'utm_campaign' => 'coach_database',
            'rc_source' => $source,
            'rc_platform' => $platform,
            'rc_event' => 'profile_view',
            'rc_ctx' => $trackingContext,
            'rc_sig' => $trackingSignature,
            'rc_athlete_id' => $athleteId,
            'rc_contact_id' => $contactId,
            'rc_ghl_contact_id' => $contactId,
            'rc_coach_email' => $email,
            'rc_school' => $school,
            'rc_business_id' => $businessId,
            'rc_fingerprint' => $fingerprint,
        ], fn ($value): bool => ! is_null($value) && $value !== '');

        if (empty($query)) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . http_build_query($query);
    }

    public function decodeRecruitingTrackingContext(?string $context, ?string $signature = null): array
    {
        $context = trim((string) $context);
        if ($context === '') {
            return [];
        }

        $secret = (string) config('app.key', 'plyrcard');
        if (filled($signature)) {
            $expected = hash_hmac('sha256', $context, $secret);
            if (! hash_equals(substr($expected, 0, strlen((string) $signature)), (string) $signature)) {
                return [];
            }
        }

        $decoded = strtr($context, '-_', '+/');
        $decoded .= str_repeat('=', (4 - strlen($decoded) % 4) % 4);
        $payload = json_decode((string) base64_decode($decoded, true), true);

        return is_array($payload) ? $payload : [];
    }

    /**
     * Entry point for the player website beacon/controller. It reads the attribution query
     * added by appendRecruitingTrackingQueryParameters() and increments the Recruiting Center contact fields.
     */
    public function trackRecruitingProfileViewFromRequest(?User $user, \Illuminate\Http\Request $request, array $metadata = []): array
    {
        $trackingPayload = $this->decodeRecruitingTrackingContext(
            $request->query('rc_ctx'),
            $request->query('rc_sig'),
        );

        if (! empty($trackingPayload)) {
            $metadata = array_merge([
                'contact_id' => $trackingPayload['c'] ?? $trackingPayload['contact_id'] ?? null,
                'business_id' => $trackingPayload['b'] ?? $trackingPayload['business_id'] ?? null,
                'school' => $trackingPayload['school'] ?? null,
                'platform' => $trackingPayload['p'] ?? $trackingPayload['platform'] ?? null,
                'source' => $trackingPayload['s'] ?? $trackingPayload['source'] ?? null,
                'destination_url' => $trackingPayload['d'] ?? null,
                'athlete_id' => $trackingPayload['u'] ?? $trackingPayload['athlete_id'] ?? null,
            ], $metadata);
        }

        if (! $user && filled($metadata['athlete_id'] ?? null)) {
            $user = User::query()->find((int) $metadata['athlete_id']);
        }

        if (! $user && filled($request->query('rc_athlete_id'))) {
            $user = User::query()->find((int) $request->query('rc_athlete_id'));
        }

        $contactId = trim((string) ($metadata['contact_id']
            ?? $request->query('rc_contact_id')
            ?? $request->query('rc_ghl_contact_id')
            ?? ''));

        if ($contactId === '') {
            return ['success' => false, 'error' => 'Missing recruiting contact attribution.'];
        }

        $destinationUrl = (string) ($metadata['destination_url'] ?? $request->fullUrl());
        $platform = (string) ($metadata['platform'] ?? $request->query('rc_platform', $request->query('utm_medium', 'website')));

        return $this->trackRecruitingProfileViewForUser($user, $contactId, array_merge([
            'source' => $request->query('rc_source', 'player_website'),
            'platform' => $platform,
            'destination_url' => $destinationUrl,
            'profile_url' => $destinationUrl,
            'referrer' => $request->headers->get('referer'),
            'host' => $request->getHost(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'ip_hash' => hash('sha256', (string) $request->ip()),
            'utm_source' => $request->query('utm_source'),
            'utm_medium' => $request->query('utm_medium'),
            'utm_campaign' => $request->query('utm_campaign'),
            'tracking_fingerprint' => $request->query('rc_fingerprint'),
            'coach_email' => $request->query('rc_coach_email'),
            'school' => $request->query('rc_school'),
            'business_id' => $request->query('rc_business_id'),
            'athlete_id' => $user?->id,
        ], $metadata));
    }

    /**
     * Creates missing tracking fields for the user's connected location.
     * Safe to call on dashboard mount and before sending email.
     */
    public function ensureRecruitingTrackingFieldsForUser(?User $user = null): array
    {
        $credentials = $user ? $this->credentialsForUser($user) : [
            'location_id' => config('ghl.location_id'),
            'token_override' => null,
        ];

        $locationId = $credentials['location_id'] ?: config('ghl.location_id');
        $tokenOverride = $credentials['token_override'] ?? null;
        $token = $this->tokenForLocation($locationId, $tokenOverride);

        if (! $locationId || ! $token) {
            return ['success' => false, 'fields' => [], 'error' => 'Missing recruiting data connection.'];
        }

        $fields = $this->ensureRecruitingTrackingFieldsForLocation($locationId, $token, true);

        return [
            'success' => ! empty($fields),
            'location_id' => $locationId,
            'fields' => $fields,
        ];
    }

    /**
     * Called after a one-to-one or campaign email is confirmed sent.
     * It increments the recipient coach contact and the athlete/sender aggregate contact.
     */
    public function trackRecruitingEmailSentForUser(?User $user, string $contactId, array $metadata = []): array
    {
        return $this->trackRecruitingEventForUser(
            user: $user,
            contactId: $contactId,
            platform: 'email',
            eventType: 'email_sent',
            metadata: $metadata,
        );
    }

    public function trackRecruitingProfileViewForUser(?User $user, string $contactId, array $metadata = []): array
    {
        return $this->trackRecruitingEventForUser(
            user: $user,
            contactId: $contactId,
            platform: (string) ($metadata['platform'] ?? 'website'),
            eventType: 'profile_view',
            metadata: $metadata,
        );
    }

    /**
     * Main tracking entry point. Works for click, open, and sent events.
     *
     * Smooth Recruiting Center-only behavior:
     * - profile views are deduped in cache before any Recruiting Center API call;
     * - custom-field IDs are cached and not refreshed on every hit;
     * - counters use local cache after the first remote read, so valid views usually make only one Recruiting Center update call;
     * - high-volume tracking events do not update the athlete aggregate contact, only the coach/contact record.
     */
    public function trackRecruitingEventForUser(?User $user, string $contactId, string $platform, string $eventType = 'link_click', array $metadata = []): array
    {
        $contactId = trim($contactId);

        if ($contactId === '') {
            return ['success' => false, 'error' => 'Missing contact id.'];
        }

        $credentials = $user ? $this->credentialsForUser($user) : [
            'location_id' => config('ghl.location_id'),
            'token_override' => null,
        ];

        $locationId = $credentials['location_id'] ?: config('ghl.location_id');
        $tokenOverride = $credentials['token_override'] ?? null;
        $token = $this->tokenForLocation($locationId, $tokenOverride);

        if (! $locationId || ! $token) {
            return ['success' => false, 'error' => 'Missing recruiting data connection.'];
        }

        $platform = $this->normalizeRecruitingTrackingPlatform($platform);
        $eventType = strtolower(trim($eventType));
        $metadata = array_merge($metadata, [
            'contact_id' => $metadata['contact_id'] ?? $contactId,
            'ghl_contact_id' => $metadata['ghl_contact_id'] ?? $contactId,
        ]);

        $metadata['unique_tracking_event'] = $this->markRecruitingUniqueTrackingEvent($locationId, $contactId, $platform, $eventType, $metadata);

        // Only fetch the contact when the tracking token did not already carry coach/school metadata.
        $metadata = $this->enrichRecruitingTrackingMetadataFromContact($metadata, $contactId, $locationId, $tokenOverride);

        // Do not force refresh here. Recruiting Center field IDs are stable and cached for smooth, low-call tracking.
        $fields = $this->ensureRecruitingTrackingFieldsForLocation($locationId, $token, false);

        if (empty($fields)) {
            return ['success' => false, 'error' => 'Tracking fields could not be prepared.'];
        }

        $increments = $this->trackingIncrementKeysForEvent($platform, $eventType, $metadata);
        $textUpdates = $this->trackingTextUpdates($platform, $eventType, $metadata);

        $recipientResult = $this->incrementRecruitingContactTrackingFields(
            contactId: $contactId,
            locationId: $locationId,
            tokenOverride: $tokenOverride,
            token: $token,
            incrementKeys: $increments,
            textUpdates: $textUpdates,
        );

        if ($recipientResult['success'] ?? false) {
            $this->rememberRecruitingTrackingContactId($user, $locationId, $contactId);
            // Keep the warmed contact cache when possible. The increment method already updates it locally.
        }

        $athleteResult = null;
        $athleteContactId = null;

        if ($this->shouldUpdateRecruitingOwnerAggregate($eventType)) {
            $athleteContactId = $this->resolveRecruitingTrackingOwnerContactId($user, $locationId, $tokenOverride);

            if ($athleteContactId && $athleteContactId !== $contactId) {
                $athleteResult = $this->incrementRecruitingContactTrackingFields(
                    contactId: $athleteContactId,
                    locationId: $locationId,
                    tokenOverride: $tokenOverride,
                    token: $token,
                    incrementKeys: $increments,
                    textUpdates: $textUpdates,
                );

                if ($athleteResult['success'] ?? false) {
                    $this->rememberRecruitingTrackingContactId($user, $locationId, $athleteContactId);
                }
            }
        }

        return [
            'success' => (bool) ($recipientResult['success'] ?? false),
            'contact_id' => $contactId,
            'athlete_contact_id' => $athleteContactId,
            'platform' => $platform,
            'event_type' => $eventType,
            'unique_tracking_event' => (bool) ($metadata['unique_tracking_event'] ?? false),
            'recipient' => $recipientResult,
            'athlete' => $athleteResult,
        ];
    }

    protected function markRecruitingUniqueTrackingEvent(string $locationId, string $contactId, string $platform, string $eventType, array $metadata = []): bool
    {
        $eventType = strtolower(trim($eventType));
        if (! in_array($eventType, ['profile_view', 'link_click', 'click', 'email_click', 'click_email'], true)) {
            return false;
        }

        $minutes = (int) config('ghl.coach_database.unique_tracking_event_minutes', config('ghl.coach_database.profile_view_dedupe_minutes', 1440));
        if ($minutes <= 0) {
            return true;
        }

        $athleteId = (string) ($metadata['athlete_id'] ?? $metadata['user_id'] ?? 'unknown-athlete');
        $destination = strtolower(trim((string) ($metadata['destination_url'] ?? $metadata['profile_url'] ?? '')));
        $source = strtolower(trim((string) ($metadata['source'] ?? '')));
        $businessId = strtolower(trim((string) ($metadata['business_id'] ?? $metadata['ghl_business_id'] ?? $metadata['company_id'] ?? '')));
        $school = strtolower(trim((string) ($metadata['school'] ?? $metadata['school_name'] ?? $metadata['company_name'] ?? '')));

        $fingerprint = implode('|', [
            $locationId,
            $contactId,
            $athleteId,
            $platform,
            $eventType,
            $source,
            $businessId,
            $school,
            $destination,
        ]);

        return \Illuminate\Support\Facades\Cache::add('recruiting-unique-tracking-event:' . md5($fingerprint), true, now()->addMinutes($minutes));
    }

    protected function shouldSkipRecruitingTrackingByDedupe(string $locationId, string $contactId, string $platform, string $eventType, array $metadata = []): bool
    {
        if (! in_array($eventType, ['profile_view'], true)) {
            return false;
        }

        $minutes = (int) config('ghl.coach_database.profile_view_dedupe_minutes', 45);
        if ($minutes <= 0) {
            return false;
        }

        $athleteId = (string) ($metadata['athlete_id'] ?? $metadata['user_id'] ?? 'unknown-athlete');
        $destination = strtolower(trim((string) ($metadata['destination_url'] ?? $metadata['profile_url'] ?? '')));
        $source = strtolower(trim((string) ($metadata['source'] ?? '')));

        $fingerprint = implode('|', [
            $locationId,
            $contactId,
            $athleteId,
            $platform,
            $eventType,
            $source,
            $destination,
        ]);

        $cacheKey = 'recruiting-profile-view-dedupe:' . md5($fingerprint);

        return ! \Illuminate\Support\Facades\Cache::add($cacheKey, true, now()->addMinutes($minutes));
    }

    protected function shouldUpdateRecruitingOwnerAggregate(string $eventType): bool
    {
        $eventType = strtolower(trim($eventType));

        if (in_array($eventType, ['email_sent', 'sent', 'email_delivered', 'delivered', 'email_failed', 'failed', 'bounced', 'email_bounced'], true)) {
            return true;
        }

        if (in_array($eventType, ['profile_view', 'highlight_view'], true)) {
            return (bool) config('ghl.coach_database.aggregate_profile_views_to_owner', true);
        }

        if (in_array($eventType, ['link_click', 'click', 'email_click', 'click_email', 'email_open', 'open'], true)) {
            return (bool) config('ghl.coach_database.aggregate_engagement_to_owner', true);
        }

        return false;
    }

    /**
     * Backwards-compatible method name used by the tracking controller/package.
     */
    public function incrementTrackingFieldsForUser(?User $user, string $contactId, string $platform, string $eventType = 'link_click', array $metadata = []): array
    {
        return $this->trackRecruitingEventForUser($user, $contactId, $platform, $eventType, $metadata);
    }

    /**
     * Backwards-compatible explicit method name used in older snippets/check commands.
     */
    public function incrementRecruitingTrackingField(?User $user, string $contactId, string $fieldKey, int $amount = 1, array $metadata = []): array
    {
        $fieldKey = $this->normalizeRecruitingTrackingKey($fieldKey);
        $contactId = trim($contactId);

        if ($contactId === '' || $fieldKey === '') {
            return ['success' => false, 'error' => 'Missing contact or tracking field.'];
        }

        $credentials = $user ? $this->credentialsForUser($user) : [
            'location_id' => config('ghl.location_id'),
            'token_override' => null,
        ];

        $locationId = $credentials['location_id'] ?: config('ghl.location_id');
        $tokenOverride = $credentials['token_override'] ?? null;
        $token = $this->tokenForLocation($locationId, $tokenOverride);

        if (! $locationId || ! $token) {
            return ['success' => false, 'error' => 'Missing recruiting data connection.'];
        }

        $trackingFieldMap = $this->ensureRecruitingTrackingFieldsForLocation($locationId, $token, true);
        $contact = $this->fetchContactForDashboard($contactId, $locationId, $tokenOverride, true);
        $current = $this->numericCustomFieldFromContact($contact, [$fieldKey], $trackingFieldMap);
        $next = max(0, $current + max(1, $amount));
        $payload = ['customFields' => [$this->recruitingTrackingFieldPayload($locationId, $fieldKey, $next)]];

        $response = Http::withHeaders(['Version' => config('ghl.version', '2021-07-28')])
            ->timeout((int) config('ghl.timeout', 20))
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->put("{$this->baseUrl}/contacts/{$contactId}", $payload);

        if ($response->failed()) {
            Log::error('Recruiting tracking field increment failed.', [
                'contact_id' => $contactId,
                'field' => $fieldKey,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['success' => false, 'error' => 'Tracking update failed.', 'status' => $response->status()];
        }

        return ['success' => true, 'contact_id' => $contactId, 'field' => $fieldKey, 'value' => $next];
    }

    protected function trackingIncrementKeysForEvent(string $platform, string $eventType, array $metadata = []): array
    {
        $eventType = strtolower(trim($eventType));
        $source = strtolower((string) ($metadata['source'] ?? ''));
        $platform = $this->normalizeRecruitingTrackingPlatform($platform);
        $keys = [];

        if (in_array($eventType, ['email_sent', 'sent'], true)) {
            return ['email_sent_count'];
        }

        if (in_array($eventType, ['email_open', 'open'], true)) {
            return ['email_open_count'];
        }

        if (in_array($eventType, ['email_delivered', 'delivered'], true)) {
            return ['email_delivered_count'];
        }

        if (in_array($eventType, ['email_failed', 'failed', 'bounced', 'email_bounced'], true)) {
            return ['email_failed_count'];
        }

        if (in_array($eventType, ['profile_view', 'highlight_view'], true)) {
            $keys[] = 'view_profile_total';
            $keys[] = $platform === 'email' ? 'view_profile_email_link' : 'view_profile_' . $platform;
            $keys[] = 'school_profile_view_count';
            $keys[] = 'profile_view_school_click_count';
            $keys[] = 'school_click_count';
            if ((bool) ($metadata['unique_tracking_event'] ?? false)) {
                $keys[] = 'unique_profile_view_count';
                $keys[] = 'profile_view_unique_contact_count';
                $keys[] = 'unique_click_count';
            }
            return collect($keys)->filter()->unique()->values()->all();
        }

        if (in_array($eventType, ['link_click', 'click', 'email_click', 'click_email'], true)) {
            if (str_contains($source, 'email') || in_array($eventType, ['email_click', 'click_email'], true)) {
                $keys[] = 'email_click_count';
            }

            $platformClickKey = match ($platform) {
                'instagram' => 'instagram_click_count',
                'youtube' => 'youtube_click_count',
                'x' => 'x_click_count',
                'website', 'email' => 'website_click_count',
                default => null,
            };

            if ($platformClickKey) {
                $keys[] = $platformClickKey;
            }

            $keys[] = 'school_link_click_count';
            $keys[] = 'school_click_count';

            if ((bool) ($metadata['unique_tracking_event'] ?? false)) {
                $keys[] = 'unique_link_click_count';
                $keys[] = 'unique_click_count';
            }

            return collect($keys)->filter()->unique()->values()->all();
        }

        return [];
    }

    protected function enrichRecruitingTrackingMetadataFromContact(array $metadata, string $contactId, string $locationId, ?string $tokenOverride): array
    {
        $metadata['contact_id'] = $metadata['contact_id'] ?? $contactId;
        $metadata['ghl_contact_id'] = $metadata['ghl_contact_id'] ?? $contactId;

        $needsCoach = blank($metadata['coach_name'] ?? null)
            || blank($metadata['coach_email'] ?? null)
            || blank($metadata['school'] ?? null)
            || blank($metadata['business_id'] ?? null)
            || blank($metadata['school_logo_url'] ?? null);

        if (! $needsCoach) {
            return $metadata;
        }

        try {
            $contact = $this->fetchContactForDashboard($contactId, $locationId, $tokenOverride, true);
            if (! is_array($contact) || empty($contact)) {
                return $metadata;
            }

            $coach = $this->transformCoachContact($contact);

            return array_merge([
                'coach_name' => $coach['name'] ?? null,
                'coach_email' => $coach['email'] ?? null,
                'school' => $coach['school'] ?? $coach['company_name'] ?? null,
                'school_name' => $coach['school'] ?? $coach['company_name'] ?? null,
                'business_id' => $coach['business_id'] ?? null,
                'ghl_business_id' => $coach['business_id'] ?? null,
                'school_logo_url' => $coach['school_logo_url'] ?? $coach['business_logo_url'] ?? $coach['logo_url'] ?? null,
            ], array_filter($metadata, fn ($value): bool => ! is_null($value) && $value !== ''));
        } catch (\Throwable $exception) {
            Log::warning('Recruiting tracking metadata enrichment failed.', [
                'contact_id' => $contactId,
                'error' => $exception->getMessage(),
            ]);

            return $metadata;
        }
    }

    protected function profileViewEventJson(string $platform, string $eventType, array $metadata = []): string
    {
        $event = [
            'event_type' => $eventType,
            'platform' => $platform,
            'source' => $metadata['source'] ?? null,
            'occurred_at' => $metadata['occurred_at'] ?? now()->toIso8601String(),
            'contact_id' => $metadata['contact_id'] ?? $metadata['ghl_contact_id'] ?? null,
            'coach_name' => $metadata['coach_name'] ?? $metadata['contact_name'] ?? null,
            'coach_email' => $metadata['coach_email'] ?? $metadata['contact_email'] ?? null,
            'school' => $metadata['school'] ?? $metadata['school_name'] ?? $metadata['company_name'] ?? null,
            'business_id' => $metadata['business_id'] ?? $metadata['ghl_business_id'] ?? $metadata['company_id'] ?? null,
            'school_logo_url' => $metadata['school_logo_url'] ?? $metadata['business_logo_url'] ?? $metadata['logo_url'] ?? null,
            'url' => $metadata['destination_url'] ?? null,
            'referrer' => $metadata['referrer'] ?? null,
            'host' => $metadata['host'] ?? null,
            'utm_source' => $metadata['utm_source'] ?? null,
            'utm_medium' => $metadata['utm_medium'] ?? null,
            'utm_campaign' => $metadata['utm_campaign'] ?? null,
            'tracking_fingerprint' => $metadata['tracking_fingerprint'] ?? null,
        ];

        return substr(json_encode(array_filter($event, fn ($value): bool => ! is_null($value) && $value !== ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 1900);
    }

    protected function trackingTextUpdates(string $platform, string $eventType, array $metadata = []): array
    {
        $destinationUrl = (string) ($metadata['destination_url'] ?? '');
        $source = (string) ($metadata['source'] ?? $eventType);

        $occurredAt = (string) ($metadata['occurred_at'] ?? now()->toIso8601String());
        $contactId = (string) ($metadata['contact_id'] ?? $metadata['ghl_contact_id'] ?? '');
        $coachName = (string) ($metadata['coach_name'] ?? $metadata['contact_name'] ?? '');
        $coachEmail = (string) ($metadata['coach_email'] ?? $metadata['contact_email'] ?? '');
        $school = (string) ($metadata['school'] ?? $metadata['school_name'] ?? $metadata['company_name'] ?? '');
        $businessId = (string) ($metadata['business_id'] ?? $metadata['ghl_business_id'] ?? $metadata['company_id'] ?? '');
        $schoolLogoUrl = (string) ($metadata['school_logo_url'] ?? $metadata['business_logo_url'] ?? $metadata['logo_url'] ?? '');
        $referrer = (string) ($metadata['referrer'] ?? '');

        $updates = [
            'last_profile_view_source' => $source,
            'last_profile_view_at' => $occurredAt,
            'last_clicked_url' => $destinationUrl,
            'last_clicked_platform' => $platform,
            'last_tracking_host' => (string) ($metadata['host'] ?? request()?->getHost() ?? ''),
            'last_profile_view_contact_id' => $contactId,
            'last_profile_view_coach_name' => $coachName,
            'last_profile_view_coach_email' => $coachEmail,
            'last_profile_view_school' => $school,
            'last_profile_view_business_id' => $businessId,
            'last_profile_view_school_logo_url' => $schoolLogoUrl,
            'last_profile_view_platform' => $platform,
            'last_profile_view_url' => $destinationUrl,
            'last_profile_view_referrer' => $referrer,
            'last_profile_view_utm_source' => (string) ($metadata['utm_source'] ?? ''),
            'last_profile_view_utm_medium' => (string) ($metadata['utm_medium'] ?? ''),
            'last_profile_view_utm_campaign' => (string) ($metadata['utm_campaign'] ?? ''),
            'last_profile_view_fingerprint' => (string) ($metadata['tracking_fingerprint'] ?? ''),
            'profile_view_event_json' => $this->profileViewEventJson($platform, $eventType, array_merge($metadata, [
                'occurred_at' => $occurredAt,
                'destination_url' => $destinationUrl,
                'source' => $source,
            ])),
        ];

        if (in_array(strtolower(trim($eventType)), ['email_sent', 'sent'], true)) {
            $updates['last_email_status'] = 'sent';
            $updates['last_email_sent_at'] = (string) ($metadata['sent_at'] ?? now()->toIso8601String());
        }

        if (in_array(strtolower(trim($eventType)), ['email_delivered', 'delivered'], true)) {
            $updates['last_email_status'] = 'delivered';
        }

        if (in_array(strtolower(trim($eventType)), ['email_failed', 'failed', 'bounced', 'email_bounced'], true)) {
            $updates['last_email_status'] = 'failed';
        }

        if (filled($metadata['message_id'] ?? null)) {
            $updates['last_email_message_id'] = (string) $metadata['message_id'];
        }

        return array_filter($updates, fn ($value): bool => $value !== null && $value !== '');
    }

    protected function incrementRecruitingContactTrackingFields(string $contactId, string $locationId, ?string $tokenOverride, string $token, array $incrementKeys, array $textUpdates = []): array
    {
        $trackingFieldMap = $this->ensureRecruitingTrackingFieldsForLocation($locationId, $token, false);
        $cachedCounters = \Illuminate\Support\Facades\Cache::get($this->recruitingTrackingCounterCacheKey($locationId, $contactId), []);
        $cachedContact = \Illuminate\Support\Facades\Cache::get($this->recruitingTrackingContactDetailCacheKey($locationId, $contactId), []);

        $normalizedIncrementKeys = collect($incrementKeys)
            ->map(fn ($key): string => $this->normalizeRecruitingTrackingKey((string) $key))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $hasWarmCounters = is_array($cachedCounters)
            && ! empty($normalizedIncrementKeys)
            && collect($normalizedIncrementKeys)->every(fn (string $key): bool => array_key_exists($key, $cachedCounters));

        $remoteContact = [];
        if (! $hasWarmCounters) {
            // First valid event for this contact/counter after cache warmup may need one read.
            // Later valid events can update Recruiting Center with one request using the cached counter values.
            $remoteContact = $this->fetchContactForDashboard($contactId, $locationId, $tokenOverride, true);
        } elseif (is_array($cachedContact)) {
            $remoteContact = $cachedContact;
        }

        $customFields = [];
        $nextValues = [];
        $currentValues = [];

        foreach ($normalizedIncrementKeys as $key) {
            $remoteCurrent = is_array($remoteContact) && ! empty($remoteContact)
                ? $this->numericCustomFieldFromContact($remoteContact, [$key], $trackingFieldMap)
                : 0;
            $cachedContactCurrent = is_array($cachedContact) ? $this->numericCustomFieldFromContact($cachedContact, [$key], $trackingFieldMap) : 0;
            $cachedCounterCurrent = is_array($cachedCounters) ? (int) ($cachedCounters[$key] ?? 0) : 0;
            $current = max($remoteCurrent, $cachedContactCurrent, $cachedCounterCurrent);
            $next = $current + 1;

            $currentValues[$key] = $current;
            $nextValues[$key] = $next;
            $customFields[] = $this->recruitingTrackingFieldPayload($locationId, $key, $next);
        }

        foreach ($textUpdates as $key => $value) {
            $key = $this->normalizeRecruitingTrackingKey($key);
            if ($key !== '' && $value !== '') {
                $customFields[] = $this->recruitingTrackingFieldPayload($locationId, $key, $value);
            }
        }

        if (empty($customFields)) {
            return ['success' => false, 'error' => 'No tracking fields to update.'];
        }

        $response = Http::withHeaders(['Version' => config('ghl.version', '2021-07-28')])
            ->connectTimeout((int) config('ghl.coach_database.tracking_http_connect_timeout', 3))
            ->timeout((int) config('ghl.coach_database.tracking_http_timeout', 8))
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->put("{$this->baseUrl}/contacts/{$contactId}", ['customFields' => $customFields]);

        if ($response->failed()) {
            Log::error('Recruiting tracking update failed.', [
                'contact_id' => $contactId,
                'location_id' => $locationId,
                'status' => $response->status(),
                'body' => $response->body(),
                'custom_fields' => $customFields,
                'current_values' => $currentValues,
                'next_values' => $nextValues,
                'used_cached_counters' => $hasWarmCounters,
            ]);

            return ['success' => false, 'error' => 'Tracking update failed.', 'status' => $response->status()];
        }

        $updatedContact = $this->applyRecruitingTrackingValuesToCachedContact(
            is_array($remoteContact) ? $remoteContact : [],
            $locationId,
            $contactId,
            $nextValues,
            $textUpdates
        );

        $counterCache = is_array($cachedCounters) ? $cachedCounters : [];
        foreach ($nextValues as $key => $value) {
            $counterCache[$key] = max((int) ($counterCache[$key] ?? 0), (int) $value);
        }
        \Illuminate\Support\Facades\Cache::put(
            $this->recruitingTrackingCounterCacheKey($locationId, $contactId),
            $counterCache,
            now()->addDays((int) config('ghl.coach_database.tracking_counter_cache_days', 14))
        );

        Log::info('Recruiting tracking counters updated.', [
            'contact_id' => $contactId,
            'location_id' => $locationId,
            'current_values' => $currentValues,
            'next_values' => $nextValues,
            'used_cached_counters' => $hasWarmCounters,
        ]);

        return [
            'success' => true,
            'contact_id' => $contactId,
            'increments' => $nextValues,
            'contact' => $updatedContact,
            'used_cached_counters' => $hasWarmCounters,
        ];
    }


    protected function recruitingTrackingCounterCacheKey(string $locationId, string $contactId): string
    {
        return 'recruiting-tracking-counters:' . md5($locationId . '|' . $contactId);
    }

    protected function recruitingTrackingContactDetailCacheKey(string $locationId, string $contactId): string
    {
        return 'recruiting-tracking-contact-detail:' . md5($locationId . '|' . $contactId);
    }

    protected function applyRecruitingTrackingValuesToCachedContact(array $contact, string $locationId, string $contactId, array $numericValues = [], array $textValues = []): array
    {
        $values = array_merge($numericValues, $textValues);
        if (empty($values)) {
            return $contact;
        }

        foreach ($values as $key => $value) {
            $normalizedKey = $this->normalizeRecruitingTrackingKey((string) $key);
            if ($normalizedKey === '') {
                continue;
            }

            $contact[$normalizedKey] = $value;
            $rawCustomFields = $contact['customFields'] ?? $contact['customField'] ?? $contact['custom_fields'] ?? [];

            if (! is_array($rawCustomFields)) {
                $rawCustomFields = [];
            }

            $updated = false;

            if (! array_is_list($rawCustomFields)) {
                $rawCustomFields[$normalizedKey] = $value;
                $updated = true;
            } else {
                $payload = $this->recruitingTrackingFieldPayload($locationId, $normalizedKey, $value);
                $wanted = collect([
                    $normalizedKey,
                    $payload['id'] ?? null,
                    $payload['key'] ?? null,
                ])->filter()->map(fn ($item): string => strtolower(str_replace([' ', '-', '.', ':'], '_', (string) $item)))->all();

                foreach ($rawCustomFields as &$field) {
                    if (! is_array($field)) {
                        continue;
                    }

                    $candidates = collect([
                        $field['id'] ?? null,
                        $field['_id'] ?? null,
                        $field['key'] ?? null,
                        $field['name'] ?? null,
                        $field['fieldKey'] ?? null,
                        $field['customFieldId'] ?? null,
                        $field['fieldId'] ?? null,
                    ])->filter()->map(fn ($item): string => strtolower(str_replace([' ', '-', '.', ':'], '_', (string) $item)))->all();

                    if (array_intersect($wanted, $candidates)) {
                        $field['value'] = $value;
                        $field['field_value'] = $value;
                        $updated = true;
                        break;
                    }
                }
                unset($field);

                if (! $updated) {
                    $rawCustomFields[] = [
                        'id' => $payload['id'] ?? null,
                        'key' => $payload['key'] ?? $normalizedKey,
                        'fieldKey' => $payload['key'] ?? $normalizedKey,
                        'name' => $normalizedKey,
                        'value' => $value,
                        'field_value' => $value,
                    ];
                }
            }

            $contact['customFields'] = $rawCustomFields;
        }

        \Illuminate\Support\Facades\Cache::put(
            $this->recruitingTrackingContactDetailCacheKey($locationId, $contactId),
            $contact,
            now()->addDays(14)
        );

        return $contact;
    }

    protected function resolveRecruitingTrackingOwnerContactId(?User $user, string $locationId, ?string $tokenOverride = null): ?string
    {
        if (! $user) {
            return null;
        }

        $stored = trim((string) ($user->ghl_contact_id ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        return $this->findContactIdByEmail($user->email, $locationId, $tokenOverride);
    }


    /**
     * Track a known delivery/status change from a message-status webhook or poller.
     * Use this only when the provider confirms the status; app-side code cannot know
     * true delivery by itself.
     */
    public function trackRecruitingEmailStatusForUser(?User $user, string $contactId, string $status, array $metadata = []): array
    {
        $status = strtolower(trim($status));

        $eventType = match (true) {
            in_array($status, ['delivered', 'delivery', 'sent_delivered'], true) => 'email_delivered',
            in_array($status, ['failed', 'failure', 'bounced', 'bounce', 'undelivered'], true) => 'email_failed',
            default => '',
        };

        if ($eventType === '') {
            return ['success' => false, 'error' => 'Unsupported email status for tracking.', 'status' => $status];
        }

        return $this->trackRecruitingEventForUser(
            user: $user,
            contactId: $contactId,
            platform: 'email',
            eventType: $eventType,
            metadata: array_merge($metadata, [
                'source' => 'email_status_' . $status,
                'status' => $status,
                'host' => request()?->getHost(),
            ]),
        );
    }

    protected function normalizeRecruitingTrackingPlatform(?string $platform): string
    {
        $platform = strtolower(trim((string) $platform));
        $platform = str_replace(['twitter', 'twitter_x'], 'x', $platform);

        return in_array($platform, ['website', 'instagram', 'youtube', 'x', 'email', 'qr'], true)
            ? $platform
            : 'website';
    }

    protected function normalizeRecruitingTrackingKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = str_replace([' ', '-'], '_', $key);
        $aliases = [
            'email_sent' => 'email_sent_count',
            'emails_sent' => 'email_sent_count',
            'email_opens' => 'email_open_count',
            'email_clicks' => 'email_click_count',
            'website_click_count' => 'website_click_count',
            'instagram_click_count' => 'instagram_click_count',
            'youtube_click_count' => 'youtube_click_count',
            'x_click_count' => 'x_click_count',
            'unique_profile_views' => 'unique_profile_view_count',
            'unique_link_clicks' => 'unique_link_click_count',
            'unique_clicks' => 'unique_click_count',
            'school_clicks' => 'school_click_count',
            'school_profile_views' => 'school_profile_view_count',
            'school_link_clicks' => 'school_link_click_count',
            'profile_views' => 'view_profile_total',
            'website_clicks' => 'view_profile_website',
            'instagram_clicks' => 'view_profile_instagram',
            'youtube_clicks' => 'view_profile_youtube',
            'x_clicks' => 'x_click_count',
            'instagram_social_clicks' => 'instagram_click_count',
            'youtube_social_clicks' => 'youtube_click_count',
            'website_social_clicks' => 'website_click_count',
            'qr_clicks' => 'view_profile_qr',
            'qr_profile_views' => 'view_profile_qr',
        ];

        $key = $aliases[$key] ?? $key;

        return array_key_exists($key, $this->recruitingTrackingCustomFieldDefinitions()) ? $key : '';
    }

    protected function ensureRecruitingTrackingFieldsForLocation(string $locationId, string $token, bool $forceRefresh = false): array
    {
        $cacheKey = $this->recruitingTrackingCustomFieldCacheKey($locationId);

        if ($forceRefresh) {
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
        }

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addHours((int) config('ghl.coach_database.tracking_field_cache_hours', 24)), function () use ($locationId, $token): array {
            $existing = $this->getRecruitingLocationCustomFields($locationId, $token);
            $map = $this->mapRecruitingTrackingFields($existing);

            foreach ($this->recruitingTrackingCustomFieldDefinitions() as $key => $definition) {
                if (isset($map[$key])) {
                    continue;
                }

                $created = $this->createRecruitingLocationCustomField($locationId, $token, $key, $definition);
                if (! empty($created)) {
                    $map[$key] = [
                        'id' => $created['id'] ?? $created['_id'] ?? null,
                        'key' => $key,
                        'fieldKey' => $created['fieldKey'] ?? $created['key'] ?? $key,
                        'name' => $created['name'] ?? $definition['name'] ?? $key,
                    ];
                }
            }

            Log::info('Recruiting tracking fields prepared.', [
                'location_id' => $locationId,
                'field_count' => count($map),
                'fields' => array_keys($map),
            ]);

            return $map;
        });
    }

    protected function mapRecruitingTrackingFields(array $fields): array
    {
        $map = [];
        $definitions = $this->recruitingTrackingCustomFieldDefinitions();

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $candidates = collect([
                $field['key'] ?? null,
                $field['fieldKey'] ?? null,
                $field['name'] ?? null,
                $field['label'] ?? null,
                $field['id'] ?? null,
            ])->filter()->map(fn ($value): string => strtolower(str_replace([' ', '-'], '_', (string) $value)))->all();

            foreach ($definitions as $key => $definition) {
                $definitionName = strtolower(str_replace([' ', '-'], '_', (string) ($definition['name'] ?? $key)));
                $definitionCandidates = collect([$key, $definitionName])
                    ->merge($this->recruitingTrackingFieldAliases()[$key] ?? [])
                    ->map(fn ($value): string => strtolower(str_replace([' ', '-'], '_', (string) $value)))
                    ->all();

                if (! empty(array_intersect($definitionCandidates, $candidates))) {
                    $map[$key] = [
                        'id' => $field['id'] ?? $field['_id'] ?? null,
                        'key' => $key,
                        'fieldKey' => $field['fieldKey'] ?? $field['key'] ?? $key,
                        'name' => $field['name'] ?? $definition['name'] ?? $key,
                    ];
                }
            }
        }

        return $map;
    }

    protected function recruitingTrackingFieldPayload(string $locationId, string $key, mixed $value): array
    {
        $map = \Illuminate\Support\Facades\Cache::get($this->recruitingTrackingCustomFieldCacheKey($locationId), []);
        $field = $map[$key] ?? [];

        $payload = [
            'key' => $field['fieldKey'] ?? $field['key'] ?? $key,
            'field_value' => $value,
        ];

        if (! empty($field['id'])) {
            $payload['id'] = $field['id'];
        }

        return $payload;
    }

    protected function recruitingTrackingCustomFieldCacheKey(string $locationId): string
    {
        return 'recruiting-tracking-fields:' . $locationId;
    }

    protected function getRecruitingLocationCustomFields(string $locationId, string $token): array
    {
        $versions = array_values(array_unique([
            config('ghl.custom_fields_version', null),
            config('ghl.version', '2021-07-28'),
            '2021-07-28',
            '2023-02-21',
        ]));

        foreach ($versions as $version) {
            if (! $version) {
                continue;
            }

            $response = Http::withHeaders(['Version' => $version])
                ->timeout((int) config('ghl.timeout', 20))
                ->withToken($token)
                ->acceptJson()
                ->get("{$this->baseUrl}/locations/{$locationId}/customFields", ['model' => 'contact']);

            if ($response->successful()) {
                $data = $response->json() ?? [];
                $fields = $data['customFields'] ?? $data['customField'] ?? $data['fields'] ?? $data['data'] ?? [];
                return is_array($fields) ? array_values(array_filter($fields, 'is_array')) : [];
            }

            Log::warning('Recruiting custom field list failed.', [
                'location_id' => $locationId,
                'version' => $version,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return [];
    }

    protected function createRecruitingLocationCustomField(string $locationId, string $token, string $key, array $definition): array
    {
        $name = (string) ($definition['name'] ?? $key);
        $dataType = (string) ($definition['dataType'] ?? 'TEXT');
        $versions = array_values(array_unique([
            config('ghl.custom_fields_version', null),
            config('ghl.version', '2021-07-28'),
            '2021-07-28',
            '2023-02-21',
        ]));

        $payloads = [
            [
                'locationId' => $locationId,
                'name' => $name,
                'dataType' => $dataType,
                'placeholder' => $name,
                'model' => 'contact',
            ],
            [
                'name' => $name,
                'dataType' => $dataType,
                'placeholder' => $name,
                'model' => 'contact',
            ],
            [
                'locationId' => $locationId,
                'name' => $name,
                'dataType' => $dataType,
                'placeholder' => $name,
            ],
        ];

        foreach ($versions as $version) {
            if (! $version) {
                continue;
            }

            foreach ($payloads as $payload) {
                $response = Http::withHeaders(['Version' => $version])
                    ->timeout((int) config('ghl.timeout', 20))
                    ->withToken($token)
                    ->acceptJson()
                    ->asJson()
                    ->post("{$this->baseUrl}/locations/{$locationId}/customFields", $payload);

                if ($response->successful()) {
                    $data = $response->json() ?? [];
                    $field = $data['customField'] ?? $data['field'] ?? $data['data'] ?? $data;
                    Log::info('Recruiting tracking custom field created.', [
                        'location_id' => $locationId,
                        'key' => $key,
                        'version' => $version,
                        'field' => $field,
                    ]);

                    return is_array($field) ? array_merge(['key' => $key], $field) : ['key' => $key, 'fieldKey' => $key, 'name' => $name];
                }

                Log::warning('Recruiting tracking custom field create failed.', [
                    'location_id' => $locationId,
                    'key' => $key,
                    'version' => $version,
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'payload' => $payload,
                ]);
            }
        }

        // If creation is blocked by permissions, still try updating by key. Some locations accept key-only updates.
        return ['key' => $key, 'fieldKey' => $key, 'name' => $name];
    }


    /**
     * Export the current Recruiting Center contact dataset to a local CSV snapshot in one paged pass.
     *
     * Recruiting Center remains the source of truth. This file is only a temporary/cache artifact used
     * by the hourly/manual stats sync so Livewire never has to loop through contacts in
     * the browser request. The CSV includes the normalized coach row plus the raw custom
     * fields JSON and flattened custom-field columns for debugging/recovery.
     */
    public function exportRecruitingContactsCsvForUser(User $user, string $absolutePath): array
    {
        $credentials = $this->credentialsForUser($user);

        $result = $this->getAllContacts(
            locationId: $credentials['location_id'],
            tokenOverride: $credentials['token_override'],
            limit: (int) config('ghl.coach_database.stats_export_page_limit', config('ghl.coach_database.contact_page_limit', 100)),
            maxPages: (int) config('ghl.coach_database.stats_export_max_pages', config('ghl.coach_database.max_pages', 500)),
        );

        if (! ($result['success'] ?? false)) {
            return [
                'success' => false,
                'path' => $absolutePath,
                'count' => 0,
                'error' => $result['error'] ?? 'Unable to export Recruiting Center contacts.',
                'debug' => $result['debug'] ?? [],
            ];
        }

        $contacts = collect($result['contacts'] ?? [])
            ->filter(fn ($contact): bool => is_array($contact))
            ->values();

        $locationId = $credentials['location_id'] ?: config('ghl.location_id');
        $token = $this->tokenForLocation($locationId, $credentials['token_override']);
        $trackingFieldMap = ($locationId && $token)
            ? $this->ensureRecruitingTrackingFieldsForLocation($locationId, $token, false)
            : [];

        $rows = [];
        $headers = collect([
            'id', 'first_name', 'last_name', 'name', 'email', 'phone', 'title',
            'school', 'company_name', 'business_id', 'conference', 'division',
            'city', 'state', 'school_logo_url', 'business_logo_url', 'logo_url',
            'tags_json', 'custom_fields_json',
        ]);

        foreach ($contacts as $contact) {
            $coach = $this->transformCoachContact($contact, $trackingFieldMap);
            $customFields = $this->flattenCustomFieldsForCsv($contact);

            $row = array_merge($coach, $customFields, [
                'tags_json' => json_encode($coach['tags'] ?? $contact['tags'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'custom_fields_json' => json_encode($contact['customFields'] ?? $contact['customField'] ?? $contact['custom_fields'] ?? $contact['customFieldValues'] ?? $contact['custom_field_values'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);

            foreach (array_keys($row) as $key) {
                if (! $headers->contains($key)) {
                    $headers->push($key);
                }
            }

            $rows[] = $row;
        }

        $directory = dirname($absolutePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $handle = fopen($absolutePath, 'w');
        if (! $handle) {
            return [
                'success' => false,
                'path' => $absolutePath,
                'count' => 0,
                'error' => 'Unable to create recruiting stats CSV export.',
            ];
        }

        $headers = $headers->values()->all();
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(function ($header) use ($row): string {
                $value = $row[$header] ?? '';

                if (is_array($value) || is_object($value)) {
                    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
                }

                return is_scalar($value) ? (string) $value : '';
            }, $headers));
        }

        fclose($handle);

        return [
            'success' => true,
            'path' => $absolutePath,
            'count' => count($rows),
            'total' => $result['total'] ?? count($rows),
            'debug' => $result['debug'] ?? [],
        ];
    }

    protected function flattenCustomFieldsForCsv(array $contact): array
    {
        $containers = [
            $contact['customFields'] ?? null,
            $contact['customField'] ?? null,
            $contact['custom_fields'] ?? null,
            $contact['customFieldValues'] ?? null,
            $contact['custom_field_values'] ?? null,
        ];

        $fields = [];
        $normalize = function ($key): string {
            $key = strtolower(trim((string) $key));
            $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?: '';
            return trim($key, '_');
        };

        foreach ($containers as $container) {
            if (! is_array($container)) {
                continue;
            }

            if (! array_is_list($container)) {
                foreach ($container as $key => $value) {
                    $fieldKey = $normalize($key);
                    if ($fieldKey === '') {
                        continue;
                    }
                    $fields['custom_' . $fieldKey] = $this->extractCustomFieldScalarValue($value);
                }
                continue;
            }

            foreach ($container as $field) {
                if (! is_array($field)) {
                    continue;
                }

                $key = $field['fieldKey']
                    ?? $field['key']
                    ?? $field['name']
                    ?? $field['label']
                    ?? $field['id']
                    ?? $field['customFieldId']
                    ?? $field['fieldId']
                    ?? null;

                $fieldKey = $normalize($key);
                if ($fieldKey === '') {
                    continue;
                }

                $fields['custom_' . $fieldKey] = $this->extractCustomFieldScalarValue($field);
            }
        }

        return $fields;
    }

}