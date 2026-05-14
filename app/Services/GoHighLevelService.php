<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoHighLevelService
{
    protected string $baseUrl = 'https://services.leadconnectorhq.com';

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
     * Upsert a GHL contact and return a normalized result array.
     *
     * This intentionally supports two use cases:
     * - syncing the authenticated player/contact
     * - creating a new contact for a referral using override email/phone fields
     */
    public function upsertContact(User $user, array $attributes = [], array $customFields = [], string $source = 'PlyrCard Locker Room'): array
    {
        if (! $this->enabled()) {
            return [
                'ok' => false,
                'skipped' => true,
                'message' => 'GHL is not configured.',
                'response' => null,
            ];
        }

        $email = $attributes['email'] ?? $user->email ?? null;
        $phone = $attributes['phone'] ?? $user->phone ?? null;

        $payload = array_filter([
            'locationId' => config('services.ghl.location_id'),
            'firstName' => $attributes['firstName'] ?? $user->first_name ?? null,
            'lastName' => $attributes['lastName'] ?? $user->last_name ?? null,
            'name' => $attributes['name'] ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
            'email' => $email,
            'phone' => $phone,
            'address1' => $attributes['address1'] ?? null,
            'city' => $attributes['city'] ?? null,
            'state' => $attributes['state'] ?? null,
            'postalCode' => $attributes['postalCode'] ?? null,
            'country' => $attributes['country'] ?? null,
            'companyName' => $attributes['companyName'] ?? null,
            'source' => $source,
        ], fn ($value) => ! is_null($value) && $value !== '');

        if (! empty($customFields)) {
            $payload['customFields'] = $customFields;
        }

        try {
            $response = Http::withHeaders([
                    'Version' => '2021-07-28',
                    'Accept' => 'application/json',
                ])
                ->withToken(config('services.ghl.token'))
                ->timeout(20)
                ->post($this->baseUrl . '/contacts/upsert', $payload);

            $responseData = $response->json() ?: ['body' => $response->body()];
            $contactId = data_get($responseData, 'contact.id')
                ?? data_get($responseData, 'id')
                ?? data_get($responseData, 'contactId');

            if ($response->failed()) {
                Log::error('GHL contact upsert failed.', [
                    'user_id' => $user->id,
                    'status' => $response->status(),
                    'body' => $responseData,
                    'payload' => $payload,
                ]);
            }

            // Only write ghl_contact_id back onto the user when the upserted contact is the user's own email.
            if ($response->successful() && $contactId && $email && strtolower(trim($email)) === strtolower(trim((string) $user->email)) && ! $user->ghl_contact_id) {
                $user->forceFill([
                    'ghl_contact_id' => $contactId,
                ])->saveQuietly();
            }

            return [
                'ok' => $response->successful(),
                'skipped' => false,
                'status' => $response->status(),
                'response' => $responseData,
                'contact_id' => $contactId,
            ];
        } catch (Throwable $e) {
            Log::error('GHL contact upsert exception.', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return [
                'ok' => false,
                'skipped' => false,
                'message' => $e->getMessage(),
                'response' => null,
            ];
        }
    }

    public function addContactNote(?string $contactId, string $body): array
    {
        if (! $this->enabled() || blank($contactId)) {
            return [
                'ok' => false,
                'skipped' => true,
                'message' => 'GHL note skipped.',
            ];
        }

        try {
            $response = Http::withHeaders([
                    'Version' => '2021-07-28',
                    'Accept' => 'application/json',
                ])
                ->withToken(config('services.ghl.token'))
                ->timeout(20)
                ->post($this->baseUrl . '/contacts/' . $contactId . '/notes', [
                    'body' => $body,
                ]);

            $responseData = $response->json() ?: ['body' => $response->body()];

            if ($response->failed()) {
                Log::error('GHL contact note failed.', [
                    'contact_id' => $contactId,
                    'status' => $response->status(),
                    'body' => $responseData,
                ]);
            }

            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'response' => $responseData,
            ];
        } catch (Throwable $e) {
            Log::error('GHL contact note exception.', [
                'contact_id' => $contactId,
                'message' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => $e->getMessage(),
            ];
        }
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
        if (! $email || ! $this->enabled()) {
            return null;
        }

        $email = trim($email);

        $response = Http::withHeaders([
                'Version' => '2021-07-28',
            ])
            ->withToken(config('services.ghl.token'))
            ->acceptJson()
            ->get($this->baseUrl . '/contacts/search/duplicate', [
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