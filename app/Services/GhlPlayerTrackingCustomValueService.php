<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GhlPlayerTrackingCustomValueService
{
    /**
     * Create/update the four PLYRCARD tracking custom values inside the player's
     * own GoHighLevel sub-account.
     *
     * This never deletes unrelated GHL custom values.
     */
    public function sync(User $user): array
    {
        $locationId = trim((string) $user->ghl_location_id);
        $apiKey = trim((string) $user->ghl_api_key);

        if ($locationId === '' || $apiKey === '') {
            return [
                'success' => false,
                'reason' => 'missing_ghl_credentials',
                'created' => [],
                'updated' => [],
                'unchanged' => [],
                'errors' => [],
            ];
        }

        $website = $user->websites()
            ->where('is_active', true)
            ->latest('updated_at')
            ->first();

        if (! $website) {
            Log::info('PLYRCARD tracking custom values skipped because the player has no active website.', [
                'user_id' => $user->getKey(),
                'location_id' => $locationId,
            ]);

            return [
                'success' => false,
                'reason' => 'missing_active_website',
                'created' => [],
                'updated' => [],
                'unchanged' => [],
                'errors' => [],
            ];
        }

        $values = $this->trackingValues($website);
        if ($values === []) {
            return [
                'success' => false,
                'reason' => 'website_url_unavailable',
                'created' => [],
                'updated' => [],
                'unchanged' => [],
                'errors' => [],
            ];
        }

        $client = $this->client($apiKey);
        $endpoint = $this->baseUrl()
            . '/locations/' . rawurlencode($locationId)
            . '/customValues';

        $listResponse = $client->get($endpoint);

        if ($listResponse->failed()) {
            Log::warning('PLYRCARD could not read GHL custom values.', [
                'user_id' => $user->getKey(),
                'location_id' => $locationId,
                'status' => $listResponse->status(),
            ]);

            return [
                'success' => false,
                'reason' => 'custom_values_list_failed',
                'status' => $listResponse->status(),
                'created' => [],
                'updated' => [],
                'unchanged' => [],
                'errors' => [],
                'values' => $values,
            ];
        }

        $existing = $listResponse->json('customValues');
        $existing = is_array($existing) ? array_values($existing) : [];

        $result = [
            'success' => true,
            'reason' => null,
            'created' => [],
            'updated' => [],
            'unchanged' => [],
            'errors' => [],
            'values' => $values,
        ];

        foreach ($values as $definition) {
            $name = $definition['name'];
            $expectedKey = $definition['key'];
            $value = $definition['value'];

            $current = $this->findExistingCustomValue($existing, $name, $expectedKey);
            $currentId = trim((string) ($current['id'] ?? $current['_id'] ?? ''));
            $currentName = trim((string) ($current['name'] ?? ''));
            $currentValue = (string) ($current['value'] ?? '');

            if ($current && $currentId !== '' && $currentName === $name && $currentValue === $value) {
                $result['unchanged'][] = $name;
                continue;
            }

            $payload = [
                'name' => $name,
                'value' => $value,
            ];

            $response = $current && $currentId !== ''
                ? $client->put($endpoint . '/' . rawurlencode($currentId), $payload)
                : $client->post($endpoint, $payload);

            if ($response->failed()) {
                $result['success'] = false;
                $result['errors'][$name] = [
                    'status' => $response->status(),
                    'operation' => ($current && $currentId !== '') ? 'update' : 'create',
                ];

                Log::warning('PLYRCARD GHL tracking custom value sync failed.', [
                    'user_id' => $user->getKey(),
                    'location_id' => $locationId,
                    'custom_value' => $name,
                    'status' => $response->status(),
                    'operation' => ($current && $currentId !== '') ? 'update' : 'create',
                ]);

                continue;
            }

            if ($current && $currentId !== '') {
                $result['updated'][] = $name;
            } else {
                $result['created'][] = $name;

                $created = $response->json('customValue');
                if (is_array($created)) {
                    $existing[] = $created;
                }
            }
        }

        Log::info('PLYRCARD GHL tracking custom values synchronized.', [
            'user_id' => $user->getKey(),
            'location_id' => $locationId,
            'created' => $result['created'],
            'updated' => $result['updated'],
            'unchanged' => $result['unchanged'],
            'error_names' => array_keys($result['errors']),
        ]);

        return $result;
    }

    /**
     * The names intentionally match the GHL Custom Values shown in Settings:
     * Player Profile, Player Instagram, Player YouTube and Player X.
     */
    public function trackingValues(Website $website): array
    {
        $profileBase = $this->websiteBaseUrl($website);

        if ($profileBase === '') {
            return [];
        }

        $common = [
            'utm_source' => 'ghl',
            'utm_medium' => 'email',
            'utm_campaign' => 'plyrcard_recruiting',
            'rc_contact_id' => '{{contact.id}}',
            'rc_email' => '{{contact.email}}',
            'rc_website_id' => (string) $website->getKey(),
            'rc_notify' => '1',
            'rc_external' => '1',
        ];

        return [
            [
                'name' => 'Player Profile',
                'key' => 'custom_values.player_profile',
                'value' => $this->appendQuery($profileBase, $common),
            ],
            [
                'name' => 'Player Instagram',
                'key' => 'custom_values.player_instagram',
                'value' => $this->appendQuery($profileBase . '/out/instagram', $common),
            ],
            [
                'name' => 'Player YouTube',
                'key' => 'custom_values.player_youtube',
                'value' => $this->appendQuery($profileBase . '/out/youtube', $common),
            ],
            [
                'name' => 'Player X',
                'key' => 'custom_values.player_x',
                'value' => $this->appendQuery($profileBase . '/out/x', $common),
            ],
        ];
    }

    protected function websiteBaseUrl(Website $website): string
    {
        $domain = trim((string) $website->domain);

        if ($domain !== '') {
            if (! preg_match('~^https?://~i', $domain)) {
                $domain = 'https://' . ltrim($domain, '/');
            }

            return rtrim($domain, '/');
        }

        $slug = trim((string) $website->slug);
        if ($slug === '') {
            return '';
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl === '') {
            return '';
        }

        return $appUrl . '/' . rawurlencode($slug);
    }

    /**
     * Build query parameters without URL-encoding HighLevel merge tokens.
     * {{contact.id}} and {{contact.email}} must remain literal so HighLevel can
     * resolve them for each recipient when the custom value is used in an email.
     */
    protected function appendQuery(string $url, array $parameters): string
    {
        $fragment = '';
        if (str_contains($url, '#')) {
            [$url, $fragmentValue] = explode('#', $url, 2);
            $fragment = '#' . $fragmentValue;
        }

        $pairs = [];
        foreach ($parameters as $key => $value) {
            $value = (string) $value;
            $encodedValue = str_contains($value, '{{') && str_contains($value, '}}')
                ? $value
                : rawurlencode($value);

            $pairs[] = rawurlencode((string) $key) . '=' . $encodedValue;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . implode('&', $pairs) . $fragment;
    }

    protected function findExistingCustomValue(array $rows, string $name, string $expectedKey): ?array
    {
        $expectedKey = $this->normalizeFieldKey($expectedKey);

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rowName = trim((string) ($row['name'] ?? ''));
            $rowKey = $this->normalizeFieldKey((string) ($row['fieldKey'] ?? $row['field_key'] ?? ''));

            if ($rowKey !== '' && $rowKey === $expectedKey) {
                return $row;
            }

            if ($rowName !== '' && strcasecmp($rowName, $name) === 0) {
                return $row;
            }
        }

        return null;
    }

    protected function normalizeFieldKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['{{', '}}', ' '], '', $value);

        return $value;
    }

    protected function client(string $apiKey): PendingRequest
    {
        return Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'Version' => trim((string) config('ghl.version', '2021-07-28')) ?: '2021-07-28',
            ])
            ->timeout((int) config('ghl.timeout', 20));
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('ghl.base_url', 'https://services.leadconnectorhq.com'), '/');
    }
}
