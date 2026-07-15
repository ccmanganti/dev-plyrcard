<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class CompanySchoolMembershipService
{
    public function replaceListKeys(User $user, string $businessId, array $listKeys): array
    {
        return $this->replaceMembershipKeys($user, $businessId, $listKeys);
    }

    public function replaceListKeysBulk(User $user, array $schools): array
    {
        return $this->replaceMembershipKeysBulk($user, $schools);
    }

    public function replaceMembershipKeys(User $user, string $businessId, array $membershipKeys): array
    {
        $value = json_encode($this->normalizeKeys($membershipKeys), JSON_UNESCAPED_SLASHES);

        try {
            $field = app(GhlCompanyCustomFieldService::class)->fieldMetadata($user);
        } catch (Throwable $exception) {
            return ['success' => false, 'error' => $exception->getMessage()];
        }

        return $this->updateRecord($user, $businessId, $field, $value ?: '[]');
    }

    public function replaceMembershipKeysBulk(User $user, array $schools): array
    {
        try {
            $field = app(GhlCompanyCustomFieldService::class)->fieldMetadata($user);
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'updated' => 0,
                'failed' => count($schools),
                'error' => $exception->getMessage(),
            ];
        }

        $rows = collect($schools)
            ->filter(fn ($row): bool => is_array($row) && filled($row['business_id'] ?? null))
            ->values();

        $updated = 0;
        $failed = 0;
        $errors = [];

        // Functionality first: use the same validated request path for bulk and
        // single actions. A bounded pause avoids hammering the Business Records
        // mapper while newly-created fields are still propagating.
        foreach ($rows as $index => $row) {
            $keys = $row['membership_keys'] ?? $row['list_keys'] ?? [];
            $value = json_encode($this->normalizeKeys((array) $keys), JSON_UNESCAPED_SLASHES) ?: '[]';

            $result = $this->updateRecord(
                $user,
                (string) $row['business_id'],
                $field,
                $value,
            );

            if ($result['success'] ?? false) {
                $updated++;
            } else {
                $failed++;
                $errors[] = 'School ' . (string) $row['business_id'] . ': '
                    . ($result['error'] ?? 'unknown GHL error');
            }

            if ($index < $rows->count() - 1) {
                usleep(125000);
            }
        }

        return [
            'success' => $failed === 0,
            'partial_success' => $updated > 0 && $failed > 0,
            'updated' => $updated,
            'failed' => $failed,
            'error' => $failed > 0 ? collect($errors)->take(3)->implode(' | ') : null,
        ];
    }

    protected function updateRecord(
        User $user,
        string $businessId,
        array $field,
        string $value,
    ): array {
        $businessId = trim($businessId);
        if ($businessId === '') {
            return ['success' => false, 'error' => 'Missing GHL school/company ID.'];
        }

        try {
            $client = Http::withToken($this->token($user))
                ->acceptJson()
                ->withHeaders(['Version' => 'v3'])
                ->timeout(30);

            $response = null;

            for ($attempt = 1; $attempt <= 6; $attempt++) {
                if ($attempt > 1) {
                    usleep(min(1_500_000, 250_000 * $attempt));
                    $field = app(GhlCompanyCustomFieldService::class)
                        ->fieldMetadata($user, true);
                }

                $response = $client->put(
                    $this->recordUrl($businessId, $user),
                    $this->propertiesPayload($field, $value),
                );

                if ($response->successful()) {
                    return $this->successResponse($response);
                }

                if (! $this->isMappedFieldPropagationError($response)) {
                    break;
                }
            }

            return [
                'success' => false,
                'status' => $response?->status(),
                'error' => 'GHL school/company update failed (HTTP '
                    . ($response?->status() ?? 0) . '): '
                    . ($response?->body() ?? 'No response'),
            ];
        } catch (Throwable $exception) {
            report($exception);

            return ['success' => false, 'error' => $exception->getMessage()];
        }
    }

    protected function propertiesPayload(array $field, string $value): array
    {
        $mappedKey = trim((string) ($field['mappedKey'] ?? ''));

        if ($mappedKey === '') {
            throw new \RuntimeException('Missing mapped Business schema key for PLYRCARD Lists.');
        }

        return [
            'properties' => [
                $mappedKey => $value,
            ],
        ];
    }

    protected function isMappedFieldPropagationError(Response $response): bool
    {
        if (! in_array($response->status(), [400, 404, 422], true)) {
            return false;
        }

        $body = strtolower($response->body());

        return str_contains($body, "couldn't validate the mapped field")
            || str_contains($body, 'could not validate the mapped field')
            || str_contains($body, 'mapped field')
            || str_contains($body, 'unknown field');
    }

    protected function successResponse(Response $response): array
    {
        return [
            'success' => true,
            'record' => $response->json('record') ?? $response->json(),
        ];
    }

    protected function normalizeKeys(array $keys): array
    {
        return collect($keys)
            ->map(fn ($key): string => strtolower(trim((string) $key)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function recordUrl(string $businessId, User $user): string
    {
        return rtrim((string) config('ghl.base_url', 'https://services.leadconnectorhq.com'), '/')
            . '/objects/business/records/' . rawurlencode($businessId)
            . '?locationId=' . rawurlencode($this->locationId($user));
    }

    protected function token(User $user): string
    {
        $token = trim((string) ($user->ghl_api_key ?? config('ghl.api_key')));
        if ($token === '') {
            throw new \RuntimeException('Missing GHL API token.');
        }

        return $token;
    }

    protected function locationId(User $user): string
    {
        $id = trim((string) ($user->ghl_location_id ?? config('ghl.location_id')));
        if ($id === '') {
            throw new \RuntimeException('Missing GHL location ID.');
        }

        return $id;
    }
}
