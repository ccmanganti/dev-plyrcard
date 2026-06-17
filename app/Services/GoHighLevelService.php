<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
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
            'contacts_by_tag' => $this->getContactsByTagForUser($user, (string) $command['tag']),
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

        // Do not re-filter by local tags here. HighLevel's contacts/search can return
        // contacts matched by the tag filter without including the full tags array in
        // every row. Trust the API-side tag filter and inject the searched tag into the
        // normalized coach row so Favorites/Saved/Lists can rebuild immediately.
        $contacts = collect($result['contacts'] ?? [])
            ->filter(fn ($contact): bool => is_array($contact))
            ->map(fn (array $contact): array => $this->transformCoachContact($this->ensureContactCarriesTag($contact, $tag)))
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

        $contacts = collect($result['contacts'] ?? [])
            ->filter(fn ($contact): bool => is_array($contact))
            ->map(fn (array $contact): array => $this->transformCoachContact($contact))
            ->filter(fn (array $coach): bool => filled($coach['school'] ?? null))
            ->unique('id')
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

        $contacts = collect($result['contacts'] ?? [])
            ->filter(fn ($contact): bool => is_array($contact))
            ->map(fn (array $contact): array => $this->transformCoachContact($contact))
            ->filter(fn (array $coach): bool => filled($coach['school'] ?? null))
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
        $limit = min(max($limit, 1), 100);

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

        $response = Http::withHeaders([
                'Version' => config('ghl.contacts_search_version', 'v3'),
            ])
            ->timeout((int) config('ghl.timeout', 20))
            ->withToken($token)
            ->acceptJson()
            ->get("{$this->baseUrl}/contacts/", $query);

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
                Log::error('GHL get contacts request failed.', [
                    'location_id' => $locationId,
                    'status' => $response->status(),
                    'query' => $query,
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'contacts' => $contacts->values()->all(),
                    'count' => $contacts->count(),
                    'error' => 'GHL get contacts request failed.',
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
                Log::error('GHL businesses request failed.', [
                    'location_id' => $locationId,
                    'status' => $response->status(),
                    'query' => $query,
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'businesses' => $businesses->values()->all(),
                    'count' => $businesses->count(),
                    'error' => 'GHL businesses request failed.',
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
                'error' => 'Missing Business ID, GHL Location ID, or API token.',
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

            $response = Http::withHeaders([
                    'Version' => config('ghl.version', '2023-02-21'),
                ])
                ->timeout((int) config('ghl.timeout', 20))
                ->withToken($token)
                ->acceptJson()
                ->get("{$this->baseUrl}/contacts/business/{$businessId}", $query);

            $data = $response->json() ?? [];

            if ($response->failed()) {
                Log::error('GHL contacts by business request failed.', [
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
                    'error' => 'GHL contacts by business request failed.',
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

        $response = Http::withHeaders([
                'Version' => 'v3',
                'Content-Type' => 'application/json',
            ])
            ->timeout((int) config('ghl.timeout', 20))
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->post("{$this->baseUrl}/contacts/search", $payload);

        $data = $response->json() ?? [];

        if ($response->failed()) {
            Log::error('GHL contacts search failed.', [
                'location_id' => $locationId,
                'status' => $response->status(),
                'payload' => $payload,
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'contacts' => [],
                'total' => 0,
                'error' => 'GHL contacts search failed.',
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
                    'error' => $result['error'] ?? 'GHL contacts search failed.',
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
            Log::warning('GHL calendar list skipped. Missing token.', [
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
                $id = $calendar['id'] ?? $calendar['_id'] ?? $calendar['calendarId'] ?? null;

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

        $response = Http::withHeaders(['Version' => '2021-07-28'])
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

        $response = Http::withHeaders(['Version' => '2021-07-28'])
            ->timeout((int) config('ghl.timeout', 20))
            ->withToken($token)
            ->acceptJson()
            ->post("{$this->baseUrl}/contacts/{$contactId}/notes", ['body' => $body]);

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
            ->withHeaders(['Version' => config('ghl.version', '2023-02-21')])
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
        $candidates = [
            $data['templates']['templates'] ?? null,
            $data['templates']['data'] ?? null,
            $data['templates']['items'] ?? null,
            $data['emailTemplates']['templates'] ?? null,
            $data['emailTemplates']['data'] ?? null,
            $data['data']['templates'] ?? null,
            $data['data']['emailTemplates'] ?? null,
            $data['data']['data'] ?? null,
            $data['data']['items'] ?? null,
            $data['data']['results'] ?? null,
            $data['items']['templates'] ?? null,
            $data['results']['templates'] ?? null,
            $data['templates'] ?? null,
            $data['emailTemplates'] ?? null,
            $data['template'] ?? null,
            $data['data'] ?? null,
            $data['items'] ?? null,
            $data['results'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $items = $this->normalizeResponseList($candidate, ['id', '_id', 'templateId', 'name', 'title', 'subject', 'body', 'html']);
            if (! empty($items)) {
                return $items;
            }
        }

        return [];
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

    private function transformEmailTemplate(array $item): array
    {
        $body = $item['body']
            ?? $item['html']
            ?? $item['htmlBody']
            ?? $item['content']
            ?? $item['template']
            ?? $item['message']
            ?? '';

        if (is_array($body)) {
            $body = $body['html'] ?? $body['body'] ?? $body['content'] ?? '';
        }

        return [
            'id' => (string) ($item['id'] ?? $item['_id'] ?? $item['templateId'] ?? $item['template_id'] ?? ''),
            'name' => (string) ($item['name'] ?? $item['title'] ?? $item['templateName'] ?? 'Untitled Template'),
            'subject' => (string) ($item['subject'] ?? $item['emailSubject'] ?? $item['title'] ?? ''),
            'body' => (string) $body,
            'preview' => trim(str($body)->stripTags()->limit(160)->toString()),
            'updated_at' => $item['updatedAt'] ?? $item['dateUpdated'] ?? $item['modifiedAt'] ?? null,
            'created_at' => $item['createdAt'] ?? $item['dateAdded'] ?? null,
            'raw' => $item,
        ];
    }

    private function transformCoachContact(array $contact): array
    {
        $customFields = collect($contact['customFields'] ?? []);

        $defaultFieldIds = [
            'school_name' => 'mVRCvtpkuGo8eCgj2EkW',
            'school_conference' => '0fPOQNgzOiFmemKNwQ4k',
            'coach_title' => 'r0iC4KEiNp0JFygWViui',
            'coach_external_id' => 'D5Ca9PLSFG3dZdrsaIlV',
        ];

        $fieldValue = function (array $field): mixed {
            return $field['value']
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

        $school = $contact['companyName']
            ?? $contact['company_name']
            ?? $contact['businessName']
            ?? $contact['business_name']
            ?? $getCustomField('school_name');

        $name = $contact['contactName']
            ?? trim(($contact['firstName'] ?? '') . ' ' . ($contact['lastName'] ?? ''));

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
            'company_name' => $school,
            'school_or_company' => $school,
            'title' => $getCustomField('coach_title'),
            'sport' => $getCustomField('coach_sport'),
            'conference' => $getCustomField('school_conference'),
            'division' => $getCustomField('school_division'),
            'external_id' => $getCustomField('coach_external_id'),
            'state' => $getCustomField('school_state'),
            'city' => $getCustomField('school_city'),
            'tags' => $contact['tags'] ?? [],
            'is_saved_school' => $this->contactHasTag($contact, config('ghl.coach_database.tags.saved_school', 'saved school')),
            'is_favorite_school' => $this->contactHasTag($contact, config('ghl.coach_database.tags.favorite_school', 'favorite school')),
            'is_saved_coach' => $this->contactHasTag($contact, config('ghl.coach_database.tags.saved_coach', 'saved coach')),
            'is_favorite_coach' => $this->contactHasTag($contact, config('ghl.coach_database.tags.favorite_coach', 'favorite coach')),
            'viewed_profile' => $this->contactHasTag($contact, config('ghl.coach_database.tags.viewed_profile', 'viewed profile')),
            'viewed_highlights' => $this->contactHasTag($contact, config('ghl.coach_database.tags.viewed_highlights', 'viewed highlights')),
            'engaged' => $this->contactHasTag($contact, config('ghl.coach_database.tags.engaged', 'engaged')),
            'replied' => $this->contactHasTag($contact, config('ghl.coach_database.tags.replied', 'replied')),
            'trigger_link_clicked' => $this->contactHasTag($contact, config('ghl.coach_database.tags.trigger_link_clicked', 'trigger link clicked')),
            'valid_email' => $contact['validEmail'] ?? null,
            'dnd' => $contact['dnd'] ?? false,
            'date_added' => $contact['dateAdded'] ?? null,
            'date_updated' => $contact['dateUpdated'] ?? null,
        ];
    }

    private function transformCoachContactFromBusiness(array $contact, array $business): array
    {
        $transformed = $this->transformCoachContact($contact);

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

        $transformed['business_id'] = $businessId;
        $transformed['school'] = $businessName;
        $transformed['company_name'] = $businessName;
        $transformed['school_or_company'] = $businessName;

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
            Log::warning('GHL custom field sync skipped. Missing token.', array_merge($context, [
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
            Log::error('GHL custom field sync failed.', array_merge($context, [
                'contact_id' => $contactId,
                'status' => $response->status(),
                'body' => $response->body(),
                'custom_fields' => $customFields,
            ]));

            return false;
        }

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

        $response = Http::withHeaders(['Version' => '2021-07-28'])
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

        $response = Http::withHeaders(['Version' => config('ghl.businesses_version', 'v3')])
            ->timeout((int) config('ghl.timeout', 20))
            ->withToken($token)
            ->acceptJson()
            ->get("{$this->baseUrl}/businesses/", [
                'locationId' => $locationId,
                'limit' => $limit,
                'skip' => $skip,
            ]);

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

            $response = Http::withHeaders(['Version' => config('ghl.version', '2023-02-21')])
                ->timeout((int) config('ghl.timeout', 20))
                ->withToken($token)
                ->acceptJson()
                ->get("{$this->baseUrl}/contacts/business/{$businessId}", $query);

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
            $total = $data['total'] ?? $data['meta']['total'] ?? $data['contacts']['total'] ?? $total;
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
                    $coach['school_id'] = $school['id'] ?? $businessId;
                    $coach['school'] = $school['name'] ?? ($coach['school'] ?? null);
                    $coach['conference'] = $school['conference'] ?? ($coach['conference'] ?? null);
                    $coach['division'] = $school['division'] ?? ($coach['division'] ?? null);
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
            'count' => count($coaches),
            'total' => $total,
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

        $params = array_filter([
            'locationId' => $locationId,
            'limit' => $query['limit'] ?? 50,
            'contactId' => $query['contactId'] ?? null,
            'query' => $query['search'] ?? null,
            'status' => $query['status'] ?? 'all',
            'sortBy' => $query['sortBy'] ?? 'last_message_date',
            'sort' => $query['sort'] ?? 'desc',
        ], fn ($value) => filled($value));

        $response = Http::withHeaders(['Version' => config('ghl.conversations_search_version', '2023-02-21')])
            ->timeout((int) config('ghl.timeout', 20))
            ->withToken($token)
            ->acceptJson()
            ->get("{$this->baseUrl}/conversations/search", $params);

        $data = $response->json() ?? [];

        if ($response->failed()) {
            Log::error('Recruiting conversations request failed.', ['status' => $response->status(), 'params' => $params, 'body' => $response->body()]);
            return ['success' => false, 'conversations' => [], 'error' => 'Unable to load conversations.', 'status' => $response->status(), 'raw' => $data];
        }

        $items = $this->extractConversationsFromResponse($data);

        return [
            'success' => true,
            'conversations' => collect($items)->filter(fn ($item) => is_array($item))->map(fn (array $item): array => $this->transformConversation($item))->filter(fn (array $item): bool => filled($item['id'] ?? null))->values()->all(),
            'total' => $data['total'] ?? $data['meta']['total'] ?? null,
            'error' => null,
        ];
    }

    public function getConversationMessagesForUser(User $user, string $conversationId, ?string $lastMessageId = null, int $limit = 50): array
    {
        $credentials = $this->credentialsForUser($user);
        $locationId = $credentials['location_id'];
        $token = $this->tokenForLocation($locationId, $credentials['token_override']);

        if (! $token || ! $conversationId) {
            return ['success' => false, 'messages' => [], 'error' => 'Missing conversation connection.'];
        }

        $params = ['limit' => min(max($limit, 1), 100), 'type' => 'TYPE_EMAIL'];
        if ($lastMessageId) {
            $params['lastMessageId'] = $lastMessageId;
        }

        $response = Http::withHeaders(['Version' => config('ghl.conversations_messages_version', '2021-04-15')])
            ->timeout((int) config('ghl.timeout', 20))
            ->withToken($token)
            ->acceptJson()
            ->get("{$this->baseUrl}/conversations/{$conversationId}/messages", $params);

        $data = $response->json() ?? [];

        if ($response->failed()) {
            Log::error('Recruiting conversation messages request failed.', ['conversation_id' => $conversationId, 'status' => $response->status(), 'body' => $response->body()]);
            return ['success' => false, 'messages' => [], 'error' => 'Unable to load messages.', 'status' => $response->status(), 'raw' => $data];
        }

        $items = $this->extractConversationMessagesFromResponse($data);
        $messages = collect($items)->filter(fn ($item) => is_array($item))->map(fn (array $item): array => $this->transformConversationMessage($item))->filter(fn (array $item): bool => filled($item['id'] ?? null))->values()->all();

        return [
            'success' => true,
            'messages' => $messages,
            'last_message_id' => collect($messages)->last()['id'] ?? $lastMessageId,
            'has_more' => count($messages) >= $params['limit'],
            'error' => null,
        ];
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

        if (! $contactId && $to) {
            $contactId = $this->findContactIdByEmail((string) $to);
        }

        if (! $contactId) {
            return ['success' => false, 'error' => 'Contact id not found for this coach. Open a coach row with a valid email/contact first.'];
        }

        if ($subject === '' || trim(strip_tags($html)) === '') {
            return ['success' => false, 'error' => 'Subject and message are required.'];
        }

        $payloads = [];

        $base = array_filter([
            'locationId' => $locationId,
            'contactId' => $contactId,
            'conversationId' => $conversationId,
            'subject' => $subject,
            'html' => $html,
            'message' => $text,
            'emailTo' => $to,
        ], fn ($value) => filled($value));

        $payloads[] = array_merge($base, ['type' => 'Email']);
        $payloads[] = array_merge($base, ['type' => 'TYPE_EMAIL']);
        $payloads[] = array_merge($base, ['messageType' => 'TYPE_EMAIL']);
        $payloads[] = array_filter([
            'locationId' => $locationId,
            'contactId' => $contactId,
            'conversationId' => $conversationId,
            'type' => 'Email',
            'subject' => $subject,
            'body' => $html,
            'message' => $text,
            'emailTo' => $to,
        ], fn ($value) => filled($value));

        $versions = array_values(array_unique(array_filter([
            config('ghl.conversations_send_version'),
            '2021-04-15',
            'v3',
            '2023-02-21',
        ])));

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

    public function getEmailTemplatesForUser(User $user): array
    {
        $credentials = $this->credentialsForUser($user);
        $locationId = $credentials['location_id'];
        $token = $this->tokenForLocation($locationId, $credentials['token_override']);

        if (! $locationId || ! $token) {
            return ['success' => false, 'templates' => [], 'error' => 'Missing recruiting data connection.'];
        }

        $attempts = [
            ['endpoint' => config('ghl.email_templates_v2_endpoint', '/emails/templates'), 'version' => config('ghl.email_templates_v2_version', '2021-07-28'), 'query' => ['locationId' => $locationId, 'limit' => 100]],
            ['endpoint' => config('ghl.email_templates_v3_endpoint', '/emails/templates'), 'version' => config('ghl.email_templates_v3_version', 'v3'), 'query' => ['locationId' => $locationId, 'limit' => 100]],
            ['endpoint' => config('ghl.email_templates_builder_v2_endpoint', '/emails/builder/templates'), 'version' => config('ghl.email_templates_builder_v2_version', '2021-07-28'), 'query' => ['locationId' => $locationId, 'limit' => 100]],
            ['endpoint' => config('ghl.email_templates_builder_endpoint', '/emails/builder'), 'version' => config('ghl.email_templates_builder_version', '2021-04-15'), 'query' => ['locationId' => $locationId, 'limit' => 100]],
            ['endpoint' => config('ghl.email_templates_endpoint', '/emails/templates'), 'version' => config('ghl.email_templates_version', '2021-04-15'), 'query' => ['locationId' => $locationId, 'limit' => 100]],
            ['endpoint' => config('ghl.email_templates_alt_endpoint', '/templates'), 'version' => config('ghl.email_templates_alt_version', '2021-07-28'), 'query' => ['locationId' => $locationId, 'limit' => 100, 'type' => 'email']],
        ];

        $lastStatus = null;
        $lastData = [];

        foreach ($attempts as $attempt) {
            $response = Http::withHeaders(['Version' => $attempt['version']])
                ->timeout((int) config('ghl.timeout', 20))
                ->withToken($token)
                ->acceptJson()
                ->get("{$this->baseUrl}{$attempt['endpoint']}", $attempt['query']);

            $data = $response->json() ?? [];
            $lastStatus = $response->status();
            $lastData = $data;

            if ($response->successful()) {
                $items = $this->extractTemplatesFromResponse($data);
                $templates = collect($items)->filter(fn ($item) => is_array($item))->map(fn (array $item): array => $this->transformEmailTemplate($item))->filter(fn (array $item): bool => filled($item['id'] ?? null))->values()->all();

                return [
                    'success' => true,
                    'templates' => $templates,
                    'source' => $attempt['endpoint'],
                    'error' => null,
                ];
            }
        }

        Log::error('Recruiting templates request failed.', ['status' => $lastStatus, 'raw' => $lastData]);
        return ['success' => false, 'templates' => [], 'error' => 'Unable to load templates.', 'status' => $lastStatus, 'raw' => $lastData];
    }

    public function createEmailTemplateForUser(User $user, string $name, string $subject, string $body): array
    {
        $credentials = $this->credentialsForUser($user);
        $locationId = $credentials['location_id'];
        $token = $this->tokenForLocation($locationId, $credentials['token_override']);

        if (! $locationId || ! $token) {
            return ['success' => false, 'error' => 'Missing recruiting data connection.'];
        }

        $attempts = [
            [
                'endpoint' => config('ghl.email_templates_v2_endpoint', '/emails/templates'),
                'version' => config('ghl.email_templates_v2_version', '2021-07-28'),
                'payload' => ['locationId' => $locationId, 'name' => $name, 'subject' => $subject, 'body' => $body, 'html' => $body],
            ],
            [
                'endpoint' => config('ghl.email_templates_v3_endpoint', '/emails/templates'),
                'version' => config('ghl.email_templates_v3_version', 'v3'),
                'payload' => ['locationId' => $locationId, 'name' => $name, 'subject' => $subject, 'body' => $body, 'html' => $body],
            ],
            [
                'endpoint' => config('ghl.email_templates_builder_v2_endpoint', '/emails/builder/templates'),
                'version' => config('ghl.email_templates_builder_v2_version', '2021-07-28'),
                'payload' => ['locationId' => $locationId, 'name' => $name, 'subject' => $subject, 'body' => $body, 'html' => $body],
            ],
            [
                'endpoint' => config('ghl.email_templates_builder_endpoint', '/emails/builder'),
                'version' => config('ghl.email_templates_builder_version', '2021-04-15'),
                'payload' => ['locationId' => $locationId, 'name' => $name, 'subject' => $subject, 'body' => $body, 'html' => $body],
            ],
        ];

        $lastStatus = null;
        $lastData = [];

        foreach ($attempts as $attempt) {
            $response = Http::withHeaders(['Version' => $attempt['version']])
                ->timeout((int) config('ghl.timeout', 20))
                ->withToken($token)
                ->acceptJson()
                ->asJson()
                ->post("{$this->baseUrl}{$attempt['endpoint']}", $attempt['payload']);

            $data = $response->json() ?? [];
            $lastStatus = $response->status();
            $lastData = $data;

            if ($response->successful()) {
                return ['success' => true, 'template' => $data['template'] ?? $data, 'source' => $attempt['endpoint'], 'raw' => $data];
            }
        }

        return ['success' => false, 'error' => 'Unable to create template.', 'status' => $lastStatus, 'raw' => $lastData];
    }

    protected function transformSchoolBusiness(array $business): array
    {
        $customFields = collect($business['customFields'] ?? []);
        $field = function (string $key) use ($customFields): ?string {
            $match = $customFields->first(function ($item) use ($key): bool {
                return is_array($item) && strtolower((string) ($item['key'] ?? $item['fieldKey'] ?? '')) === strtolower($key);
            });

            if (! is_array($match)) {
                return null;
            }

            return $match['valueString'] ?? $match['value'] ?? $match['valueText'] ?? null;
        };

        return [
            'id' => (string) ($business['id'] ?? ''),
            'business_id' => (string) ($business['id'] ?? ''),
            'name' => (string) ($business['name'] ?? 'Unnamed School'),
            'conference' => $field('conference'),
            'division' => $field('division'),
            'city' => $business['city'] ?? null,
            'state' => $business['state'] ?? null,
            'website' => $business['website'] ?? null,
            'email' => $business['email'] ?? null,
            'phone' => $business['phone'] ?? null,
            'updated_at' => $business['updatedAt'] ?? null,
            'created_at' => $business['createdAt'] ?? null,
            'coach_count' => 0,
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

        return [
            'id' => (string) ($item['id'] ?? $item['_id'] ?? $item['conversationId'] ?? ''),
            'contact_id' => (string) ($item['contactId'] ?? $item['contact_id'] ?? $contact['id'] ?? ''),
            'contact_name' => (string) ($item['contactName'] ?? $item['fullName'] ?? $item['name'] ?? $contact['contactName'] ?? $contact['fullName'] ?? $contact['name'] ?? trim(($contact['firstName'] ?? '') . ' ' . ($contact['lastName'] ?? '')) ?: 'Unknown Coach'),
            'email' => (string) ($item['email'] ?? $item['contactEmail'] ?? $contact['email'] ?? ''),
            'status' => (string) ($item['status'] ?? (($item['unreadCount'] ?? 0) ? 'Unread' : 'Open')),
            'last_message' => trim(strip_tags((string) $lastBody)),
            'last_message_at' => $item['lastMessageDate'] ?? $item['lastMessageAt'] ?? $item['last_message_date'] ?? $item['updatedAt'] ?? null,
            'unread_count' => (int) ($item['unreadCount'] ?? $item['unread_count'] ?? 0),
        ];
    }

    protected function transformConversationMessage(array $item): array
    {
        $body = $item['body'] ?? $item['message'] ?? $item['html'] ?? $item['text'] ?? $item['emailMessage'] ?? '';
        $direction = $item['direction'] ?? $item['messageDirection'] ?? $item['directionType'] ?? $item['source'] ?? '';

        return [
            'id' => (string) ($item['id'] ?? $item['_id'] ?? $item['messageId'] ?? ''),
            'direction' => (string) $direction,
            'type' => (string) ($item['type'] ?? $item['messageType'] ?? 'TYPE_EMAIL'),
            'subject' => (string) ($item['subject'] ?? $item['emailSubject'] ?? ''),
            'body' => (string) $body,
            'status' => (string) ($item['status'] ?? ''),
            'from' => (string) ($item['from'] ?? $item['emailFrom'] ?? $item['sender'] ?? ''),
            'to' => (string) ($item['to'] ?? $item['emailTo'] ?? $item['receiver'] ?? ''),
            'created_at' => $item['dateAdded'] ?? $item['createdAt'] ?? $item['created_at'] ?? null,
        ];
    }
}