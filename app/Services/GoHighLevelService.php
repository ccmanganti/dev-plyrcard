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
        $fromName = trim((string) ($payload['fromName'] ?? $payload['senderName'] ?? $user->name ?? 'PLYRCard'));
        $fromEmail = trim((string) ($payload['fromEmail'] ?? $payload['emailFrom'] ?? ''));

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

        $payloads = [];

        $base = array_filter([
            'locationId' => $locationId,
            'contactId' => $contactId,
            'conversationId' => $conversationId,
            'subject' => $subject,
            'html' => $html,
            'message' => $text,
            'emailTo' => $to,
            'fromEmail' => $fromEmail,
            'emailFrom' => $fromEmail,
            'fromName' => $fromName,
            'senderName' => $fromName,
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
            'fromEmail' => $fromEmail,
            'emailFrom' => $fromEmail,
            'fromName' => $fromName,
            'senderName' => $fromName,
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
            return ['success' => false, 'error' => 'Choose a valid image.'];
        }

        $path = (string) $file->getRealPath();
        if ($path === '' || ! is_file($path)) {
            return ['success' => false, 'error' => 'Image upload could not be read.'];
        }

        $name = method_exists($file, 'getClientOriginalName')
            ? (string) $file->getClientOriginalName()
            : ('plyrcard-template-image-' . now()->format('YmdHis') . '.jpg');

        $mimeType = method_exists($file, 'getMimeType') ? (string) $file->getMimeType() : 'image/jpeg';
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($extension === '') {
            $name .= match ($mimeType) {
                'image/png' => '.png',
                'image/gif' => '.gif',
                'image/webp' => '.webp',
                default => '.jpg',
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
        // making the uploaded image appear in that location's GHL media library.
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

        $lastError = 'Unable to upload image.';
        $lastStatus = null;
        $lastRaw = [];
        $attempts = [];

        foreach ($endpoints as $endpoint) {
            foreach ($versions as $version) {
                foreach ($payloads as $payload) {
                    try {
                        $handle = fopen($path, 'r');
                        if (! $handle) {
                            return ['success' => false, 'error' => 'Image upload could not be opened.'];
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
                            $lastError = $this->extractApiErrorMessage($data, 'Unable to upload image.');
                            continue;
                        }

                        $url = $this->extractMediaUploadUrl($data);

                        if ($url === '') {
                            $lastError = 'Image uploaded to GHL, but no URL was returned.';
                            continue;
                        }

                        return [
                            'success' => true,
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

        Log::error('GHL media upload failed.', [
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
            data_get($data, 'files.0.url'),
            data_get($data, 'files.0.fileUrl'),
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
                return [
                    'success' => false,
                    'error' => 'Template was created, but no template id was returned.',
                    'status' => $lastStatus,
                    'raw' => $lastData,
                    'debug' => $attemptDebug,
                ];
            }
        }

        $updateAttempts = [
            ['method' => 'patch', 'version' => '2023-02-21', 'url' => "{$this->baseUrl}/emails/builder/{$templateId}", 'payload' => $basePayload, 'source' => '/emails/builder/{id}'],
            ['method' => 'put', 'version' => '2023-02-21', 'url' => "{$this->baseUrl}/emails/builder/{$templateId}", 'payload' => $basePayload, 'source' => 'PUT /emails/builder/{id}'],
            ['method' => 'patch', 'version' => 'v3', 'url' => "{$this->baseUrl}/emails/locations/{$locationId}/templates/{$templateId}", 'payload' => $basePayload, 'source' => 'PATCH /emails/locations/{locationId}/templates/{id}'],
            ['method' => 'put', 'version' => 'v3', 'url' => "{$this->baseUrl}/emails/locations/{$locationId}/templates/{$templateId}", 'payload' => $basePayload, 'source' => 'PUT /emails/locations/{locationId}/templates/{id}'],
            ['method' => 'patch', 'version' => 'v3', 'url' => "{$this->baseUrl}/locations/{$locationId}/templates/{$templateId}", 'payload' => array_merge($basePayload, ['templateType' => 'email']), 'source' => 'PATCH /locations/{locationId}/templates/{id}'],
            ['method' => 'put', 'version' => 'v3', 'url' => "{$this->baseUrl}/locations/{$locationId}/templates/{$templateId}", 'payload' => array_merge($basePayload, ['templateType' => 'email']), 'source' => 'PUT /locations/{locationId}/templates/{id}'],
            ['method' => 'patch', 'version' => '2023-02-21', 'url' => "{$this->baseUrl}/emails/templates/{$templateId}", 'payload' => $basePayload, 'source' => 'PATCH /emails/templates/{id}'],
            ['method' => 'put', 'version' => '2023-02-21', 'url' => "{$this->baseUrl}/emails/templates/{$templateId}", 'payload' => $basePayload, 'source' => 'PUT /emails/templates/{id}'],
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
        $body = $this->conversationMessageBody($item);
        $direction = $this->conversationScalar($item['direction'] ?? $item['messageDirection'] ?? $item['directionType'] ?? $item['source'] ?? '');
        $from = $this->conversationScalar($item['from'] ?? $item['emailFrom'] ?? $item['sender'] ?? data_get($item, 'sender.email') ?? data_get($item, 'from.email') ?? '');
        $to = $this->conversationScalar($item['to'] ?? $item['emailTo'] ?? $item['receiver'] ?? data_get($item, 'to.email') ?? '');
        $fromName = $this->conversationScalar($item['fromName'] ?? $item['senderName'] ?? data_get($item, 'sender.name') ?? data_get($item, 'from.name') ?? '');

        return [
            'id' => (string) ($item['id'] ?? $item['_id'] ?? $item['messageId'] ?? ''),
            'direction' => $direction,
            'type' => $this->conversationScalar($item['type'] ?? $item['messageType'] ?? 'TYPE_EMAIL'),
            'subject' => $this->conversationScalar($item['subject'] ?? $item['emailSubject'] ?? data_get($item, 'email.subject') ?? ''),
            'body' => $body,
            'status' => $this->conversationScalar($item['status'] ?? ''),
            'from' => $from,
            'from_name' => $fromName ?: $from,
            'to' => $to,
            'attachments' => $this->extractConversationAttachments($item),
            'created_at' => $item['dateAdded'] ?? $item['createdAt'] ?? $item['created_at'] ?? null,
        ];
    }

    protected function conversationMessageBody(array $item): string
    {
        foreach ([
            $item['html'] ?? null,
            $item['body'] ?? null,
            $item['emailMessage'] ?? null,
            data_get($item, 'email.html'),
            data_get($item, 'email.body'),
            data_get($item, 'message.html'),
            data_get($item, 'message.body'),
            $item['message'] ?? null,
            $item['text'] ?? null,
            data_get($item, 'message.text'),
        ] as $candidate) {
            $value = $this->conversationHtmlValue($candidate);
            if (trim(strip_tags($value)) !== '' || str_contains(strtolower($value), '<img')) {
                return $value;
            }
        }

        return '';
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
            data_get($item, 'email.attachments'),
            data_get($item, 'message.attachments'),
            data_get($item, 'body.attachments'),
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

        $url = $this->conversationScalar($value['url'] ?? $value['link'] ?? $value['mediaUrl'] ?? $value['fileUrl'] ?? $value['downloadUrl'] ?? $value['thumbnailUrl'] ?? $value['src'] ?? '');
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
}