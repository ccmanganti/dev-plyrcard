<?php

namespace App\Services;

use App\Models\Coach;
use App\Models\School;
use App\Models\SchoolGhlSyncTarget;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CoachGhlGateway
{
    /** @var array<string, array<string, array<string, mixed>>> */
    protected array $businessMaps = [];

    public function syncCoach(
        Coach $coach,
        User $credentialUser,
        string $locationId,
        string $apiKeyHash,
    ): array {
        $token = trim((string) $credentialUser->ghl_api_key);
        $locationId = trim($locationId);

        if ($token === '' || $locationId === '') {
            throw new RuntimeException('Missing GHL API key or location ID.');
        }

        $coach->loadMissing('school');
        $email = Str::lower(trim((string) $coach->email));
        if ($email === '') {
            throw new RuntimeException('Coach has no email address.');
        }

        $schoolResult = $coach->school
            ? $this->syncSchool($coach->school, $credentialUser, $locationId, $apiKeyHash)
            : [
                'action' => 'unchanged',
                'business_id' => null,
                'mapping_id' => null,
            ];

        $businessId = $schoolResult['business_id'] ?? null;
        $schoolName = trim((string) ($coach->school?->name ?? ''));

        $payload = array_filter([
            'locationId' => $locationId,
            'firstName' => trim((string) $coach->first_name),
            'lastName' => trim((string) $coach->last_name),
            'name' => trim((string) $coach->display_name),
            'email' => $email,
            'phone' => trim((string) $coach->phone) ?: null,
            'companyName' => $schoolName ?: null,
            'city' => trim((string) $coach->city) ?: null,
            'state' => trim((string) $coach->state) ?: null,
            'country' => trim((string) $coach->country) ?: null,
            'website' => trim((string) $coach->website_url) ?: null,
            'source' => 'PLYRCARD Coach Database',
            'tags' => array_values(array_filter([
                'coach-database',
                trim((string) $coach->sport) ?: null,
                trim((string) $coach->division) ?: null,
            ])),
            // Explicitly prevent the upsert endpoint from creating another contact
            // when duplicate contacts are allowed in the GHL location.
            'createNewIfDuplicateAllowed' => false,
        ], static fn ($value): bool => $value !== null && $value !== '');

        $lockKey = 'coach-ghl-email-lock:' . hash('sha256', $token . '|' . $locationId . '|' . $email);

        return Cache::lock($lockKey, 60)->block(15, function () use (
            $locationId,
            $token,
            $email,
            $payload,
            $businessId,
            $schoolResult,
        ): array {
            $existing = $this->findContactByEmail($locationId, $token, $email);

            if ($existing) {
                $contactId = $this->contactId($existing);
                if ($contactId === '') {
                    throw new RuntimeException('GHL returned a matching contact without an ID.');
                }

                $contactAction = 'unchanged';
                if ($this->contactNeedsUpdate($existing, $payload, $businessId)) {
                    $response = $this->contactClient($token)->put(
                        $this->baseUrl() . '/contacts/' . rawurlencode($contactId),
                        Arr::except($payload, ['locationId', 'createNewIfDuplicateAllowed']),
                    );

                    if (! $response->successful()) {
                        throw new RuntimeException(
                            'GHL contact update failed (HTTP ' . $response->status() . '): ' .
                            Str::limit($response->body(), 500)
                        );
                    }
                    $contactAction = 'updated';
                }

                $associationChanged = $this->ensureBusinessAssociation(
                    $locationId,
                    $token,
                    $contactId,
                    $businessId,
                    $existing,
                );

                if ($associationChanged && $contactAction === 'unchanged') {
                    $contactAction = 'updated';
                }

                return [
                    'action' => $contactAction,
                    'contact_id' => $contactId,
                    'business_id' => $businessId,
                    'matched_by' => 'email',
                    'school_action' => $schoolResult['action'] ?? 'unchanged',
                    'school_mapping_id' => $schoolResult['mapping_id'] ?? null,
                ];
            }

            $response = $this->contactClient($token)->post(
                $this->baseUrl() . '/contacts/upsert',
                $payload,
            );

            // A second worker or an existing duplicate can race the create attempt.
            // Resolve it by email instead of attempting another create.
            if (! $response->successful()) {
                $raced = $this->findContactByEmail($locationId, $token, $email);
                if (! $raced) {
                    throw new RuntimeException(
                        'GHL contact upsert failed (HTTP ' . $response->status() . '): ' .
                        Str::limit($response->body(), 500)
                    );
                }

                $contactId = $this->contactId($raced);
                $this->ensureBusinessAssociation($locationId, $token, $contactId, $businessId, $raced);

                return [
                    'action' => 'updated',
                    'contact_id' => $contactId,
                    'business_id' => $businessId,
                    'matched_by' => 'email_after_conflict',
                    'school_action' => $schoolResult['action'] ?? 'unchanged',
                    'school_mapping_id' => $schoolResult['mapping_id'] ?? null,
                ];
            }

            $contact = $response->json('contact') ?? $response->json('data.contact') ?? $response->json();
            $contactId = is_array($contact) ? $this->contactId($contact) : '';
            $contactId = $contactId !== '' ? $contactId : trim((string) $response->json('id'));

            if ($contactId === '') {
                throw new RuntimeException('GHL upserted the contact but returned no contact ID.');
            }

            $this->ensureBusinessAssociation($locationId, $token, $contactId, $businessId, is_array($contact) ? $contact : []);

            return [
                'action' => (bool) ($response->json('new') ?? false) ? 'created' : 'updated',
                'contact_id' => $contactId,
                'business_id' => $businessId,
                'matched_by' => 'email_upsert',
                'school_action' => $schoolResult['action'] ?? 'unchanged',
                'school_mapping_id' => $schoolResult['mapping_id'] ?? null,
            ];
        });
    }

    public function syncSchool(
        School $school,
        User $credentialUser,
        string $locationId,
        string $apiKeyHash,
    ): array {
        $token = trim((string) $credentialUser->ghl_api_key);
        $locationId = trim($locationId);
        $name = trim((string) $school->name);
        $normalizedName = $this->normalizeSchool($name);

        if ($token === '' || $locationId === '') {
            throw new RuntimeException('Missing GHL credentials while synchronizing a school.');
        }
        if ($normalizedName === '') {
            throw new RuntimeException('School has no name.');
        }

        $mapping = SchoolGhlSyncTarget::query()->updateOrCreate(
            [
                'school_id' => $school->getKey(),
                'api_key_hash' => $apiKeyHash,
                'location_id' => $locationId,
            ],
            [
                'representative_user_id' => $credentialUser->getKey(),
                'normalized_name' => $normalizedName,
            ],
        );

        // Skip a remote read when this exact local school version was already synced.
        if (
            $mapping->status === 'synced'
            && filled($mapping->ghl_business_id)
            && $mapping->synced_at
            && $school->updated_at
            && $mapping->synced_at->greaterThanOrEqualTo($school->updated_at)
        ) {
            return [
                'action' => 'unchanged',
                'business_id' => (string) $mapping->ghl_business_id,
                'mapping_id' => $mapping->getKey(),
            ];
        }

        $lockKey = 'coach-ghl-school-lock:' . hash('sha256', $token . '|' . $locationId . '|' . $normalizedName);

        return Cache::lock($lockKey, 90)->block(20, function () use (
            $school,
            $credentialUser,
            $locationId,
            $apiKeyHash,
            $token,
            $normalizedName,
        ): array {
            $mapping = SchoolGhlSyncTarget::query()->updateOrCreate(
                [
                    'school_id' => $school->getKey(),
                    'api_key_hash' => $apiKeyHash,
                    'location_id' => $locationId,
                ],
                [
                    'representative_user_id' => $credentialUser->getKey(),
                    'normalized_name' => $normalizedName,
                    'status' => 'processing',
                    'checked_at' => now(),
                    'last_error' => null,
                ],
            );

            try {
                $existing = null;
                if (filled($mapping->ghl_business_id)) {
                    $existing = $this->getBusinessById($token, (string) $mapping->ghl_business_id);
                }
                $existing ??= $this->findBusinessByName($locationId, $token, (string) $school->name);

                $payload = $this->schoolPayload($school, $locationId);
                $action = 'unchanged';

                if ($existing) {
                    $businessId = $this->businessId($existing);
                    if ($businessId === '') {
                        throw new RuntimeException('GHL returned a matching school without a business ID.');
                    }

                    if ($this->businessNeedsUpdate($existing, $payload)) {
                        $response = $this->businessClient($token)->put(
                            $this->baseUrl() . '/businesses/' . rawurlencode($businessId),
                            Arr::except($payload, ['locationId']),
                        );

                        if (! $response->successful()) {
                            throw new RuntimeException(
                                'GHL school update failed (HTTP ' . $response->status() . '): ' .
                                Str::limit($response->body(), 500)
                            );
                        }
                        $action = 'updated';
                    }
                } else {
                    $response = $this->businessClient($token)->post(
                        $this->baseUrl() . '/businesses/',
                        $payload,
                    );

                    if (! $response->successful()) {
                        // Refresh once in case another request created the same normalized school.
                        $this->forgetBusinessMap($locationId, $token);
                        $raced = $this->findBusinessByName($locationId, $token, (string) $school->name);
                        if (! $raced) {
                            throw new RuntimeException(
                                'GHL school creation failed (HTTP ' . $response->status() . '): ' .
                                Str::limit($response->body(), 500)
                            );
                        }
                        $businessId = $this->businessId($raced);
                        $action = 'unchanged';
                    } else {
                        $business = $response->json('business')
                            ?? $response->json('record')
                            ?? $response->json('data.business')
                            ?? $response->json();
                        $businessId = is_array($business) ? $this->businessId($business) : '';
                        if ($businessId === '') {
                            throw new RuntimeException('GHL created the school but returned no business ID.');
                        }
                        $action = 'created';
                    }
                }

                $mapping->forceFill([
                    'ghl_business_id' => $businessId,
                    'status' => 'synced',
                    'last_action' => $action,
                    'last_error' => null,
                    'checked_at' => now(),
                    'synced_at' => now(),
                ])->save();

                // Compatibility fields for older screens. Per-location IDs remain in
                // school_ghl_sync_targets and are the authoritative mappings.
                $school->forceFill([
                    'ghl_business_id' => $businessId,
                    'ghl_synced_at' => now(),
                ])->saveQuietly();

                $this->rememberBusiness($locationId, $token, [
                    'id' => $businessId,
                    'name' => $school->name,
                    ...Arr::except($payload, ['locationId']),
                ]);

                return [
                    'action' => $action,
                    'business_id' => $businessId,
                    'mapping_id' => $mapping->getKey(),
                ];
            } catch (Throwable $exception) {
                $mapping->forceFill([
                    'status' => 'failed',
                    'last_error' => $exception->getMessage(),
                    'checked_at' => now(),
                ])->save();
                throw $exception;
            }
        });
    }

    protected function schoolPayload(School $school, string $locationId): array
    {
        return array_filter([
            'locationId' => $locationId,
            'name' => trim((string) $school->name),
            'website' => trim((string) $school->website_url) ?: null,
            'address' => trim((string) $school->street) ?: null,
            'city' => trim((string) $school->city) ?: null,
            'state' => trim((string) $school->state) ?: null,
            'postalCode' => trim((string) $school->zipcode) ?: null,
            'country' => 'United States',
            'description' => 'Synchronized from PLYRCARD Coach Database',
        ], static fn ($value): bool => $value !== null && $value !== '');
    }

    protected function findContactByEmail(string $locationId, string $token, string $email): ?array
    {
        $response = $this->contactClient($token)->get($this->baseUrl() . '/contacts/search/duplicate', [
            'locationId' => $locationId,
            'email' => $email,
        ]);

        if ($response->status() === 404) {
            return null;
        }
        if (! $response->successful()) {
            throw new RuntimeException(
                'GHL contact lookup failed (HTTP ' . $response->status() . '): ' .
                Str::limit($response->body(), 500)
            );
        }

        $rows = collect($response->json('contacts') ?? []);
        if ($rows->isEmpty() && is_array($response->json('contact'))) {
            $rows = collect([$response->json('contact')]);
        }

        return $rows->first(fn ($row): bool => is_array($row)
            && Str::lower(trim((string) ($row['email'] ?? $row['emailLowerCase'] ?? ''))) === $email);
    }

    protected function getBusinessById(string $token, string $businessId): ?array
    {
        $response = $this->businessClient($token)->get(
            $this->baseUrl() . '/businesses/' . rawurlencode($businessId)
        );

        if ($response->status() === 404) {
            return null;
        }
        if (! $response->successful()) {
            return null;
        }

        $business = $response->json('business') ?? $response->json('record') ?? $response->json();
        return is_array($business) ? $business : null;
    }

    protected function findBusinessByName(string $locationId, string $token, string $schoolName): ?array
    {
        $map = $this->businessMap($locationId, $token);
        return $map[$this->normalizeSchool($schoolName)] ?? null;
    }

    /** @return array<string, array<string, mixed>> */
    protected function businessMap(string $locationId, string $token): array
    {
        $key = hash('sha256', $token . '|' . $locationId);
        if (array_key_exists($key, $this->businessMaps)) {
            return $this->businessMaps[$key];
        }

        $cacheKey = 'coach-ghl-business-map-v2:' . $key;
        return $this->businessMaps[$key] = Cache::remember(
            $cacheKey,
            now()->addMinutes(20),
            function () use ($locationId, $token): array {
                $skip = 0;
                $map = [];

                for ($page = 0; $page < 200; $page++) {
                    $response = $this->businessClient($token)->get($this->baseUrl() . '/businesses/', [
                        'locationId' => $locationId,
                        'limit' => 100,
                        'skip' => $skip,
                    ]);

                    if (! $response->successful()) {
                        throw new RuntimeException(
                            'GHL school lookup failed (HTTP ' . $response->status() . '): ' .
                            Str::limit($response->body(), 500)
                        );
                    }

                    $rows = collect(
                        $response->json('businesses')
                        ?? $response->json('records')
                        ?? $response->json('data')
                        ?? []
                    )->filter(fn ($row): bool => is_array($row))->values();

                    foreach ($rows as $row) {
                        $name = trim((string) ($row['name'] ?? $row['businessName'] ?? $row['companyName'] ?? ''));
                        if ($name !== '' && $this->businessId($row) !== '') {
                            // First exact normalized match wins. This avoids switching IDs
                            // when legacy duplicates already exist in GHL.
                            $map[$this->normalizeSchool($name)] ??= $row;
                        }
                    }

                    if ($rows->count() < 100) {
                        break;
                    }
                    $skip += $rows->count();
                }

                return $map;
            },
        );
    }

    protected function rememberBusiness(string $locationId, string $token, array $business): void
    {
        $key = hash('sha256', $token . '|' . $locationId);
        $name = trim((string) ($business['name'] ?? ''));
        if ($name === '') {
            return;
        }

        $map = $this->businessMaps[$key] ?? $this->businessMap($locationId, $token);
        $map[$this->normalizeSchool($name)] = $business;
        $this->businessMaps[$key] = $map;
        Cache::put('coach-ghl-business-map-v2:' . $key, $map, now()->addMinutes(20));
    }

    protected function forgetBusinessMap(string $locationId, string $token): void
    {
        $key = hash('sha256', $token . '|' . $locationId);
        unset($this->businessMaps[$key]);
        Cache::forget('coach-ghl-business-map-v2:' . $key);
    }

    protected function ensureBusinessAssociation(
        string $locationId,
        string $token,
        string $contactId,
        ?string $businessId,
        array $contact,
    ): bool {
        $current = trim((string) (
            $contact['businessId']
            ?? $contact['business_id']
            ?? $contact['companyId']
            ?? $contact['company_id']
            ?? ''
        ));
        $desired = trim((string) $businessId);

        if ($current === $desired) {
            return false;
        }

        $response = $this->contactClient($token)->post(
            $this->baseUrl() . '/contacts/bulk/business',
            [
                'locationId' => $locationId,
                'businessId' => $desired !== '' ? $desired : null,
                'ids' => [(string) $contactId],
            ],
        );

        if (! $response->successful()) {
            throw new RuntimeException(
                'GHL coach-school association failed (HTTP ' . $response->status() . '): ' .
                Str::limit($response->body(), 500)
            );
        }

        return true;
    }

    protected function contactNeedsUpdate(array $contact, array $payload, ?string $businessId): bool
    {
        $pairs = [
            'firstName' => ['firstName', 'first_name'],
            'lastName' => ['lastName', 'last_name'],
            'email' => ['email', 'emailLowerCase'],
            'phone' => ['phone'],
            'companyName' => ['companyName', 'company_name', 'businessName'],
            'city' => ['city'],
            'state' => ['state'],
            'country' => ['country'],
            'website' => ['website', 'websiteUrl'],
        ];

        foreach ($pairs as $payloadKey => $contactKeys) {
            if (! array_key_exists($payloadKey, $payload)) {
                continue;
            }
            $current = '';
            foreach ($contactKeys as $key) {
                if (array_key_exists($key, $contact)) {
                    $current = trim((string) $contact[$key]);
                    break;
                }
            }
            if ($this->normalizedValue($current) !== $this->normalizedValue((string) $payload[$payloadKey])) {
                return true;
            }
        }

        $currentBusinessId = trim((string) (
            $contact['businessId']
            ?? $contact['business_id']
            ?? $contact['companyId']
            ?? $contact['company_id']
            ?? ''
        ));

        return $currentBusinessId !== trim((string) $businessId);
    }

    protected function businessNeedsUpdate(array $business, array $payload): bool
    {
        $pairs = [
            'name' => ['name', 'businessName', 'companyName'],
            'website' => ['website'],
            'address' => ['address', 'address1'],
            'city' => ['city'],
            'state' => ['state'],
            'postalCode' => ['postalCode', 'postal_code', 'zipCode'],
            'country' => ['country'],
        ];

        foreach ($pairs as $payloadKey => $businessKeys) {
            if (! array_key_exists($payloadKey, $payload)) {
                continue;
            }
            $current = '';
            foreach ($businessKeys as $key) {
                if (array_key_exists($key, $business)) {
                    $current = trim((string) $business[$key]);
                    break;
                }
            }
            if ($this->normalizedValue($current) !== $this->normalizedValue((string) $payload[$payloadKey])) {
                return true;
            }
        }

        return false;
    }

    protected function contactId(array $contact): string
    {
        return trim((string) ($contact['id'] ?? $contact['_id'] ?? $contact['contactId'] ?? ''));
    }

    protected function businessId(array $business): string
    {
        return trim((string) ($business['id'] ?? $business['_id'] ?? $business['businessId'] ?? ''));
    }

    protected function normalizeSchool(string $value): string
    {
        return Str::lower(trim((string) preg_replace('/\s+/', ' ', $value)));
    }

    protected function normalizedValue(string $value): string
    {
        return Str::lower(trim((string) preg_replace('/\s+/', ' ', $value)));
    }

    protected function contactClient(string $token): PendingRequest
    {
        return Http::withHeaders(['Version' => (string) config('ghl.version', '2021-07-28')])
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(12)
            ->timeout(35)
            ->retry(
                [1000, 2000, 4000, 8000],
                fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && (($exception->response?->status() ?? 0) === 429
                            || ($exception->response?->status() ?? 0) >= 500)),
                throw: false,
            );
    }

    protected function businessClient(string $token): PendingRequest
    {
        return Http::withHeaders(['Version' => (string) config('ghl.businesses_version', 'v3')])
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(12)
            ->timeout(35)
            ->retry(
                [1000, 2000, 4000, 8000],
                fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && (($exception->response?->status() ?? 0) === 429
                            || ($exception->response?->status() ?? 0) >= 500)),
                throw: false,
            );
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('ghl.base_url', 'https://services.leadconnectorhq.com'), '/');
    }
}