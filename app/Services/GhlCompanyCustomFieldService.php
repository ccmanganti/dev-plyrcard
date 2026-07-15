<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class GhlCompanyCustomFieldService
{
    public const FIELD_NAME = 'PLYRCARD Lists';
    public const FIELD_SLUG = 'plyrcard_lists';

    public function resolveListField(User $user, bool $forceRefresh = false): array
    {
        $cacheKey = $this->cacheKey($user);

        if (! $forceRefresh) {
            $cached = Cache::get($cacheKey);
            if ($this->validField($cached)) {
                return ['success' => true, 'field' => $cached];
            }
        }

        $lock = Cache::lock($cacheKey . ':lock', 30);

        try {
            $lock->block(8);

            if (! $forceRefresh) {
                $cached = Cache::get($cacheKey);
                if ($this->validField($cached)) {
                    return ['success' => true, 'field' => $cached];
                }
            }

            $field = collect($this->fetchBusinessFields($user))
                ->first(fn ($row): bool => is_array($row) && $this->isPlyrcardListField($row));

            if (! is_array($field)) {
                $created = $this->createBusinessListField($user);
                if (! ($created['success'] ?? false)) {
                    return $created;
                }

                $field = $created['field'];

                // The Custom Fields API can return the field before the Objects
                // record mapper has indexed it. Re-read the Business field list
                // until the created field is visible through the read endpoint.
                $visible = $this->waitUntilFieldIsVisible($user, 10);
                if (is_array($visible)) {
                    $field = array_replace($field, $visible);
                }
            }

            if (! $this->validField($field)) {
                return [
                    'success' => false,
                    'error' => 'GHL returned PLYRCARD Lists without a usable field ID or field key.',
                ];
            }

            Cache::put($cacheKey, $field, now()->addDays(30));

            return ['success' => true, 'field' => $field];
        } catch (Throwable $exception) {
            report($exception);

            return ['success' => false, 'error' => $exception->getMessage()];
        } finally {
            optional($lock)->release();
        }
    }

    public function fieldMetadata(User $user, bool $forceRefresh = false): array
    {
        $result = $this->resolveListField($user, $forceRefresh);

        if (! ($result['success'] ?? false)) {
            throw new RuntimeException($result['error'] ?? 'Unable to resolve PLYRCARD Lists custom field.');
        }

        $field = (array) $result['field'];
        $id = trim((string) ($field['id'] ?? $field['_id'] ?? $field['fieldId'] ?? $field['field_id'] ?? ''));
        $key = trim((string) ($field['fieldKey'] ?? $field['field_key'] ?? $field['key'] ?? ''));

        if ($id === '' && $key === '') {
            throw new RuntimeException('GHL returned the custom field without an ID or field key.');
        }

        $mappedKey = $this->resolveBusinessSchemaMappedKey(
            user: $user,
            field: $field,
            forceRefresh: $forceRefresh,
        );

        if ($mappedKey === '') {
            throw new RuntimeException(
                'PLYRCARD Lists exists, but it is not yet available in the GHL Business object schema.'
            );
        }

        return [
            'id' => $id,
            'fieldKey' => $key,
            'mappedKey' => $mappedKey,
            'raw' => $field,
        ];
    }

    public function fieldKey(User $user, bool $forceRefresh = false): string
    {
        $metadata = $this->fieldMetadata($user, $forceRefresh);

        if ($metadata['fieldKey'] === '') {
            throw new RuntimeException('GHL returned the custom field without a field key.');
        }

        return $metadata['fieldKey'];
    }

    protected function resolveBusinessSchemaMappedKey(
        User $user,
        array $field,
        bool $forceRefresh = false,
    ): string {
        $cacheKey = $this->cacheKey($user) . ':mapped-key';

        if (! $forceRefresh) {
            $cached = trim((string) Cache::get($cacheKey, ''));
            if ($cached !== '') {
                return $cached;
            }
        }

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            if ($attempt > 1) {
                usleep(min(1_500_000, 200_000 * $attempt));
            }

            $response = $this->client($user)->get(
                $this->baseUrl() . '/objects/business',
                ['locationId' => $this->locationId($user)],
            );

            if (! $response->successful()) {
                continue;
            }

            $mappedKey = $this->findMappedKeyInSchema(
                $response->json(),
                $field,
            );

            if ($mappedKey !== '') {
                Cache::put($cacheKey, $mappedKey, now()->addDays(30));

                return $mappedKey;
            }
        }

        return '';
    }

    protected function findMappedKeyInSchema(mixed $schema, array $field): string
    {
        $fieldId = strtolower(trim((string) (
            $field['id'] ?? $field['_id'] ?? $field['fieldId'] ?? $field['field_id'] ?? ''
        )));
        $fieldKey = strtolower(trim((string) (
            $field['fieldKey'] ?? $field['field_key'] ?? $field['key'] ?? ''
        )));
        $fieldName = strtolower(trim((string) ($field['name'] ?? self::FIELD_NAME)));

        $walk = function (mixed $node, ?string $parentKey = null) use (&$walk, $fieldId, $fieldKey, $fieldName): string {
            if (! is_array($node)) {
                return '';
            }

            $nodeId = strtolower(trim((string) (
                $node['id'] ?? $node['_id'] ?? $node['fieldId'] ?? $node['field_id'] ?? ''
            )));
            $nodeKey = strtolower(trim((string) (
                $node['fieldKey'] ?? $node['field_key'] ?? $node['key'] ?? $node['propertyKey'] ?? ''
            )));
            $nodeName = strtolower(trim((string) (
                $node['name'] ?? $node['label'] ?? $node['displayName'] ?? ''
            )));

            $matches = ($fieldId !== '' && $nodeId === $fieldId)
                || ($fieldKey !== '' && $nodeKey === $fieldKey)
                || ($nodeName !== '' && $nodeName === $fieldName)
                || str_contains(str_replace([' ', '-', '.'], '_', $nodeKey), self::FIELD_SLUG);

            if ($matches) {
                foreach ([$node['propertyKey'] ?? null, $node['fieldKey'] ?? null, $node['field_key'] ?? null, $node['key'] ?? null, $parentKey] as $candidate) {
                    $candidate = trim((string) $candidate);
                    if ($candidate !== '' && $candidate !== 'customFields' && $candidate !== 'properties') {
                        return $candidate;
                    }
                }
            }

            foreach ($node as $key => $value) {
                $found = $walk($value, is_string($key) ? $key : $parentKey);
                if ($found !== '') {
                    return $found;
                }
            }

            return '';
        };

        return $walk($schema);
    }

    public function forget(User $user): void
    {
        Cache::forget($this->cacheKey($user));
    }

    protected function waitUntilFieldIsVisible(User $user, int $attempts = 10): ?array
    {
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            if ($attempt > 1) {
                usleep(min(1_500_000, 250_000 * $attempt));
            }

            try {
                $field = collect($this->fetchBusinessFields($user))
                    ->first(fn ($row): bool => is_array($row) && $this->isPlyrcardListField($row));

                if (is_array($field)) {
                    return $field;
                }
            } catch (Throwable) {
                // Keep waiting; the create call already succeeded.
            }
        }

        return null;
    }

    protected function fetchBusinessFields(User $user): array
    {
        $response = $this->client($user)->get(
            $this->baseUrl() . '/custom-fields/object-key/business',
            ['locationId' => $this->locationId($user)],
        );

        if (! $response->successful()) {
            throw new RuntimeException(
                'Unable to load GHL Business custom fields (HTTP '
                . $response->status() . '): ' . $response->body()
            );
        }

        $json = $response->json();

        return collect($json['fields'] ?? $json['customFields'] ?? $json['data'] ?? $json ?? [])
            ->filter(fn ($row): bool => is_array($row))
            ->values()
            ->all();
    }

    protected function createBusinessListField(User $user): array
    {
        $locationId = $this->locationId($user);
        $folder = $this->resolveOrCreateFolder($user);

        if (! ($folder['success'] ?? false)) {
            return $folder;
        }

        $parentId = trim((string) ($folder['id'] ?? ''));
        if ($parentId === '') {
            return [
                'success' => false,
                'error' => 'GHL returned no folder ID for the PLYRCARD Business custom-field folder.',
            ];
        }

        $response = $this->client($user)->post($this->baseUrl() . '/custom-fields/', [
            'locationId' => $locationId,
            'name' => self::FIELD_NAME,
            'description' => 'Stores PLYRCARD school favorite and list membership keys as JSON.',
            'placeholder' => '["dream","target"]',
            'showInForms' => false,
            'dataType' => 'LARGE_TEXT',
            'fieldKey' => 'business.' . self::FIELD_SLUG,
            'objectKey' => 'business',
            'parentId' => $parentId,
        ]);

        if (! $response->successful()) {
            return [
                'success' => false,
                'status' => $response->status(),
                'error' => 'Unable to create PLYRCARD Lists Business field (HTTP '
                    . $response->status() . '): ' . $response->body(),
            ];
        }

        $field = $response->json('field')
            ?? $response->json('customField')
            ?? $response->json('data.field')
            ?? $response->json();

        return is_array($field)
            ? ['success' => true, 'field' => $field]
            : ['success' => false, 'error' => 'GHL created the field but returned no field metadata.'];
    }

    protected function resolveOrCreateFolder(User $user): array
    {
        $response = $this->client($user)->get(
            $this->baseUrl() . '/custom-fields/object-key/business',
            ['locationId' => $this->locationId($user)],
        );

        if ($response->successful()) {
            $folder = collect($response->json('folders') ?? [])
                ->first(fn ($row): bool => is_array($row)
                    && strtolower(trim((string) ($row['name'] ?? ''))) === 'plyrcard');

            if (is_array($folder) && filled($folder['id'] ?? null)) {
                return ['success' => true, 'id' => (string) $folder['id']];
            }
        }

        $create = $this->client($user)->post($this->baseUrl() . '/custom-fields/folder', [
            'objectKey' => 'business',
            'locationId' => $this->locationId($user),
            'name' => 'PLYRCARD',
        ]);

        if (! $create->successful()) {
            return [
                'success' => false,
                'status' => $create->status(),
                'error' => 'Unable to create the PLYRCARD Business custom-field folder (HTTP '
                    . $create->status() . '): ' . $create->body(),
            ];
        }

        $id = trim((string) (
            $create->json('id')
            ?? $create->json('folder.id')
            ?? $create->json('data.id')
            ?? ''
        ));

        return $id !== ''
            ? ['success' => true, 'id' => $id]
            : ['success' => false, 'error' => 'GHL created the folder but returned no folder ID.'];
    }

    protected function validField(mixed $field): bool
    {
        if (! is_array($field)) {
            return false;
        }

        return filled($field['id'] ?? $field['_id'] ?? $field['fieldId'] ?? null)
            || filled($field['fieldKey'] ?? $field['field_key'] ?? $field['key'] ?? null);
    }

    protected function isPlyrcardListField(array $field): bool
    {
        $values = collect([
            $field['name'] ?? null,
            $field['fieldKey'] ?? null,
            $field['field_key'] ?? null,
            $field['key'] ?? null,
            $field['slug'] ?? null,
        ])->map(fn ($value): string => strtolower(trim((string) $value)));

        return $values->contains(fn (string $value): bool =>
            $value === strtolower(self::FIELD_NAME)
            || str_contains(str_replace([' ', '-', '.'], '_', $value), self::FIELD_SLUG)
        );
    }

    protected function cacheKey(User $user): string
    {
        return 'ghl:business-custom-field:plyrcard-lists:' . $this->locationId($user);
    }

    protected function client(User $user)
    {
        return Http::withToken($this->token($user))
            ->acceptJson()
            ->withHeaders(['Version' => 'v3'])
            ->timeout(25)
            ->retry(2, 350, throw: false);
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('ghl.base_url', 'https://services.leadconnectorhq.com'), '/');
    }

    protected function token(User $user): string
    {
        $token = trim((string) ($user->ghl_api_key ?? config('ghl.api_key')));
        if ($token === '') {
            throw new RuntimeException('Missing GHL API token.');
        }

        return $token;
    }

    protected function locationId(User $user): string
    {
        $id = trim((string) ($user->ghl_location_id ?? config('ghl.location_id')));
        if ($id === '') {
            throw new RuntimeException('Missing GHL location ID.');
        }

        return $id;
    }
}
