<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoHighLevelService
{
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
            ->put("https://services.leadconnectorhq.com/contacts/{$contactId}", [
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
            ->get('https://services.leadconnectorhq.com/contacts/search/duplicate', [
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